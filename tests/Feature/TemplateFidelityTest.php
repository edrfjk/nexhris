<?php

namespace Tests\Feature;

use App\Services\Xlsx\SheetPainter;
use App\Services\XlsxToPdfService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;
use ZipArchive;

/**
 * The converter must not damage the form on its way to the PDF.
 *
 * The campus templates are built almost entirely out of things that live
 * outside the cell grid: the "Stamp of Date of Receipt" box, the HRMO and
 * Campus Director name blocks and all 25 leave-type checkboxes are drawing
 * shapes and legacy form controls, and the whole second page of CS Form No. 6
 * is an embedded Word document rendered as a metafile.
 *
 * Forcing A4 used to mean loading the workbook with PhpSpreadsheet and saving
 * it again. Its writer round-trips none of that, so every one of those parts
 * was destroyed before the renderer ever opened the file — the PDF came back
 * missing the stamp box, both names, and the entire instructions page, and it
 * looked like a converter bug rather than what it was.
 *
 * The page setup is now edited in place inside the package. These tests pin
 * that: the paper size changes, and nothing else does.
 */
class TemplateFidelityTest extends TestCase
{
    /** Runs the real pre-conversion step over a copy and hands back the path. */
    private function prepared(string $source, bool $forceA4 = true): string
    {
        $copy = tempnam(sys_get_temp_dir(), 'fid') . '.xlsx';
        copy($source, $copy);

        $service = app(XlsxToPdfService::class);
        $method = new \ReflectionMethod($service, 'prepareWorkbook');
        $method->setAccessible(true);
        $method->invoke($service, $copy, $forceA4);

        return $copy;
    }

    private function entry(string $path, string $name): string|false
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true, "cannot open {$path}");

        $contents = $zip->getFromName($name);
        $zip->close();

        return $contents;
    }

    /**
     * The published workbook, found on disk rather than through the model.
     *
     * These tests are about the real campus template, and RefreshDatabase
     * empties the table the model would look in while leaving the file where
     * it is.
     */
    private function templateContaining(string $folder, string $part, string $marker): string
    {
        foreach (glob(storage_path("app/public/{$folder}/*.xlsx")) ?: [] as $path) {
            $zip = new ZipArchive();

            if ($zip->open($path) !== true) {
                continue;
            }

            $contents = (string) $zip->getFromName($part);
            $zip->close();

            if (str_contains($contents, $marker)) {
                return $path;
            }
        }

        $this->markTestSkipped("No published workbook in {$folder} carries {$marker}.");
    }

    private function leaveTemplatePath(): string
    {
        return $this->templateContaining(
            'leave-form-templates',
            'xl/drawings/drawing1.xml',
            'Stamp of Date of Receipt',
        );
    }

    // ------------------------------------------------------------------
    // What must survive
    // ------------------------------------------------------------------

    public function test_the_name_blocks_and_stamp_box_survive_preparation(): void
    {
        $prepared = $this->prepared($this->leaveTemplatePath());

        $drawing = (string) $this->entry($prepared, 'xl/drawings/drawing1.xml');

        foreach (['Stamp of Date of Receipt', 'GACUTAN', 'RAMIREZ'] as $expected) {
            $this->assertStringContainsString(
                $expected,
                $drawing,
                "preparing the workbook dropped \"{$expected}\" from the form",
            );
        }
    }

    public function test_the_leave_type_checkboxes_survive_preparation(): void
    {
        $prepared = $this->prepared($this->leaveTemplatePath());

        $this->assertSame(
            25,
            preg_match_all(
                '/ObjectType="Checkbox"/',
                (string) $this->entry($prepared, 'xl/drawings/vmlDrawing1.vml'),
            ),
            'the leave-type checkboxes were lost',
        );
    }

    public function test_the_second_page_survives_preparation(): void
    {
        $prepared = $this->prepared($this->leaveTemplatePath());

        // Page 2 of CS Form No. 6 is an embedded Word document, drawn from a
        // metafile. Both parts have to still be there or the page renders blank.
        $this->assertNotFalse(
            $this->entry($prepared, 'xl/embeddings/Microsoft_Word_Document.docx'),
            'the instructions page was dropped from the workbook',
        );

        $this->assertNotFalse(
            $this->entry($prepared, 'xl/media/image3.emf'),
            'the instructions page has no rendering left',
        );
    }

    // ------------------------------------------------------------------
    // What must change
    // ------------------------------------------------------------------

    public function test_every_sheet_is_switched_to_a4(): void
    {
        $prepared = $this->prepared($this->templateContaining(
            'pds-templates',
            'xl/worksheets/sheet1.xml',
            '<customSheetView',
        ));

        $zip = new ZipArchive();
        $zip->open($prepared);

        $checked = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if (! preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                continue;
            }

            preg_match_all('/<pageSetup[^>]*>/', (string) $zip->getFromIndex($i), $found);

            foreach ($found[0] as $setup) {
                $checked++;

                // Every one, not just the first: each sheet carries a second
                // pageSetup inside a <customSheetView>, and that was the one
                // the renderer actually applied — CS Form 212 kept printing
                // US Letter with A4 set right beside it.
                $this->assertStringContainsString('paperSize="9"', $setup);

                // The r:id points at a printerSettings blob whose Windows
                // DEVMODE carries its own paper size and wins over the
                // attribute, so the reference has to go too.
                $this->assertStringNotContainsString('r:id=', $setup);
            }
        }

        $zip->close();

        $this->assertGreaterThan(4, $checked, 'no page setups were examined');
    }

    public function test_shape_text_is_not_clipped_away(): void
    {
        $prepared = $this->prepared($this->leaveTemplatePath());

        $drawing = (string) $this->entry($prepared, 'xl/drawings/drawing1.xml');

        // The Campus Director's name sits in a box too short for its own text
        // once DrawingML's default insets come off. Excel lets it spill;
        // LibreOffice honours the clip and prints nothing at all.
        $this->assertStringNotContainsString('vertOverflow="clip"', $drawing);
        $this->assertStringContainsString('vertOverflow="overflow"', $drawing);
    }

    public function test_leaving_the_paper_size_alone_still_repairs_the_text(): void
    {
        $prepared = $this->prepared($this->leaveTemplatePath(), forceA4: false);

        $sheet = (string) $this->entry($prepared, 'xl/worksheets/sheet1.xml');
        $drawing = (string) $this->entry($prepared, 'xl/drawings/drawing1.xml');

        // The clip repair is about legibility, not paper, so it applies either way.
        $this->assertStringNotContainsString('vertOverflow="clip"', $drawing);
        $this->assertStringContainsString('r:id=', $sheet, 'the page setup should be untouched');
    }

    // ------------------------------------------------------------------
    // What happens on a host with no LibreOffice
    // ------------------------------------------------------------------

    /**
     * Shared hosting draws this form now, and must not downgrade it.
     *
     * This used to assert the opposite. The pure-PHP path was PhpSpreadsheet's
     * HTML writer, which emits cells and nothing else, so CS Form No. 6 came
     * back without its tick boxes, its signature blocks or its second page and
     * the honest thing was to hand over the workbook instead.
     *
     * The painter draws the shapes, the form controls and the embedded second
     * page, so the form converts. If this test ever fails, the renderer has
     * regressed to something that cannot reproduce the campus's own form.
     */
    public function test_the_leave_form_converts_on_a_host_with_no_libreoffice(): void
    {
        config(['pdf.renderer' => 'php']);

        $response = app(XlsxToPdfService::class)
            ->stream($this->leaveTemplatePath(), 'Leave Form Template v1.pdf');

        $this->assertSame(
            'application/pdf',
            $response->headers->get('Content-Type'),
            'the leave form should convert in pure PHP, not fall back to the workbook',
        );

        $this->assertStringEndsWith(
            '.pdf"',
            (string) $response->headers->get('Content-Disposition'),
        );
    }

    /**
     * The three things the old renderer silently dropped.
     *
     * Asserted on the painted markup rather than the PDF bytes because that is
     * where a regression is legible: a missing tick box is a missing div, not
     * a slightly smaller file.
     */
    public function test_the_painted_form_keeps_the_parts_that_are_not_cells(): void
    {
        $path = $this->leaveTemplatePath();

        $book = IOFactory::createReader('Xlsx')->load($path);
        $html = (new SheetPainter())->paintWorkbook($book, [0, 1], $path);
        $book->disconnectWorksheets();

        // Every leave type has a tick box, and they are form controls rather
        // than characters in a cell.
        $this->assertGreaterThanOrEqual(
            20,
            substr_count($html, 'border:0.6pt solid #000'),
            'the leave-type tick boxes are missing',
        );

        // The stamp box and both signature blocks are drawing shapes.
        foreach (['Stamp of Date of Receipt', 'GACUTAN', 'RAMIREZ'] as $expected) {
            $this->assertStringContainsString($expected, $html, "{$expected} is missing from the painted form");
        }

        // Page two is a Word document embedded in an otherwise empty sheet.
        $this->assertStringContainsString('INSTRUCTIONS', $html, 'the second page did not render');
        $this->assertStringContainsString('Vacation leave', $html);
        $this->assertStringContainsString('Adoption Leave', $html, 'the second page was cut short');
    }

    /**
     * An embedding this cannot open is still a reason to hand over the file.
     *
     * A .docx embedding is a package the painter reads. An OLE object saved as
     * a compound binary is not, and a page that would print blank is worse
     * than a workbook the reader can open themselves.
     */
    public function test_a_workbook_with_an_unreadable_embedding_is_served_as_a_workbook(): void
    {
        config(['pdf.renderer' => 'php']);

        $copy = tempnam(sys_get_temp_dir(), 'ole') . '.xlsx';
        copy($this->leaveTemplatePath(), $copy);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($copy) === true);

        $renamed = false;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if (str_starts_with($name, 'xl/embeddings/') && str_ends_with(strtolower($name), '.docx')) {
                $zip->renameIndex($i, 'xl/embeddings/oleObject1.bin');
                $renamed = true;
                break;
            }
        }

        $zip->close();

        if (! $renamed) {
            $this->markTestSkipped('The published template carries no embedded document.');
        }

        $response = app(XlsxToPdfService::class)->stream($copy, 'Leave Form Template v1.pdf');

        @unlink($copy);

        $this->assertStringContainsString(
            'spreadsheetml',
            (string) $response->headers->get('Content-Type'),
            'an embedding that cannot be read should downgrade to the workbook',
        );
    }

    /** A workbook whose content is in cells still converts, and should. */
    public function test_a_grid_based_form_is_still_converted_on_shared_hosting(): void
    {
        config(['pdf.renderer' => 'php']);

        $response = app(XlsxToPdfService::class)->stream(
            $this->templateContaining('pds-templates', 'xl/worksheets/sheet1.xml', '<customSheetView'),
            'Personal Data Sheet Template v1.pdf',
        );

        $this->assertSame(
            'application/pdf',
            $response->headers->get('Content-Type'),
            'CS Form 212 converts well in pure PHP and should not be downgraded',
        );
    }

    /**
     * A shape that says it has no fill must not be painted.
     *
     * The fill was read from the whole anchor rather than the shape's own
     * properties, so it matched the theme references Excel writes into
     * <xdr:style>. Those are accent colours, which resolved to black, and the
     * Personal Data Sheet printed a black bar across the corner where the form
     * number belongs — on top of the number.
     *
     * Nothing on either campus form is filled black, so finding a black shape
     * means the fill is being read too widely again.
     */
    public function test_no_shape_is_painted_black(): void
    {
        $path = $this->leaveTemplatePath();

        $book = IOFactory::createReader('Xlsx')->load($path);
        $html = (new SheetPainter())->paintWorkbook($book, [0, 1], $path);
        $book->disconnectWorksheets();

        $this->assertStringNotContainsString(
            'background:#000000',
            $html,
            'a shape with no fill was painted black',
        );

        // The corner text itself must still be there — the bar used to cover it.
        $this->assertStringContainsString('Stamp of Date of Receipt', $html);
    }

    // ------------------------------------------------------------------
    // End to end
    // ------------------------------------------------------------------

    /**
     * Run against the pure-PHP renderer specifically, because that is what the
     * server has. This used to skip unless LibreOffice was installed, which
     * meant the only path that ever shipped was the one never tested.
     */
    public function test_both_pages_of_the_leave_form_are_rendered_on_a4(): void
    {
        config(['pdf.renderer' => 'php']);

        $service = app(XlsxToPdfService::class);

        $pdf = file_get_contents($service->convert($this->leaveTemplatePath(), true, false));

        $this->assertSame(
            2,
            preg_match_all('#/Type\s*/Page[^s]#', $pdf),
            'the form should be its two published pages',
        );

        preg_match_all(
            '/MediaBox\s*\[\s*[\d.]+\s+[\d.]+\s+([\d.]+)\s+([\d.]+)/',
            $pdf,
            $boxes,
        );

        foreach ($boxes[1] as $i => $width) {
            $this->assertEqualsWithDelta(595.3, (float) $width, 1.0, 'page is not A4 wide');
            $this->assertEqualsWithDelta(841.9, (float) $boxes[2][$i], 1.0, 'page is not A4 tall');
        }
    }
}
