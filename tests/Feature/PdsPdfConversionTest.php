<?php

namespace Tests\Feature;

use App\Services\XlsxToPdfService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The PDS converted without LibreOffice, checked against the real CS Form 212.
 *
 * The form is four printable sheets plus a hidden lookup sheet, each with its
 * own margins, print area and print scale. Ignoring any of those turned a
 * four-page form into twenty-four pages of differently-spaced output.
 */
class PdsPdfConversionTest extends TestCase
{
    private const A4_PORTRAIT = '595.3 x 841.9';

    protected function setUp(): void
    {
        parent::setUp();
        config(['pdf.renderer' => 'php']);

        if (! is_file($this->form())) {
            $this->markTestSkipped('The CS Form 212 template is not present.');
        }
    }

    private function form(): string
    {
        return storage_path('app/pds-template/CS-Form-212.xlsx');
    }

    private function pageBoxes(string $pdf): array
    {
        preg_match_all(
            '/MediaBox\s*\[\s*([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s*\]/',
            file_get_contents($pdf),
            $m,
        );

        $boxes = [];
        foreach ($m[3] as $i => $w) {
            $boxes[] = round((float) $w, 1) . ' x ' . round((float) $m[4][$i], 1);
        }

        return array_values(array_unique($boxes));
    }

    private function pageCount(string $pdf): int
    {
        return preg_match_all('/\/Type\s*\/Page[^s]/', file_get_contents($pdf));
    }

    private function convert(): string
    {
        // Uncached: these assert what conversion produces, not what a previous
        // run left behind.
        return app(XlsxToPdfService::class)->convert($this->form(), true, false);
    }

    public function test_the_pds_converts_to_one_a4_page_per_sheet(): void
    {
        $pdf = $this->convert();

        // Four printable sheets, each set to fit one page. Anything more means
        // the form is being split across sheets of paper it was not designed for.
        $this->assertSame(4, $this->pageCount($pdf));
        $this->assertSame([self::A4_PORTRAIT], $this->pageBoxes($pdf));
    }

    public function test_the_hidden_lookup_sheet_is_not_printed(): void
    {
        $book = IOFactory::createReader('Xlsx')->load($this->form());

        $hidden = collect($book->getAllSheets())
            ->reject(fn ($sheet) => $sheet->getSheetState() === 'visible');

        $this->assertNotEmpty($hidden, 'The fixture no longer has a hidden sheet.');

        $method = new ReflectionMethod(XlsxToPdfService::class, 'printableSheets');
        $method->setAccessible(true);

        $printable = $method->invoke(app(XlsxToPdfService::class), $book);

        // Excel does not print hidden sheets; nor should this. Otherwise a
        // page of dropdown values lands in the middle of someone's PDS.
        $this->assertCount(4, $printable);

        $book->disconnectWorksheets();
    }

    public function test_each_sheet_is_cut_down_to_its_print_area(): void
    {
        $book = IOFactory::createReader('Xlsx')->load($this->form());

        $before = $book->getSheet(0)->getHighestRow();
        $this->assertGreaterThan(200, $before, 'The fixture no longer has trailing rows.');

        $method = new ReflectionMethod(XlsxToPdfService::class, 'trimToPrintAreas');
        $method->setAccessible(true);
        $method->invoke(app(XlsxToPdfService::class), $book);

        // A1:N61 is what the sheet declares; the rest is scratch space.
        $this->assertSame(61, $book->getSheet(0)->getHighestRow());
        $this->assertSame('N', $book->getSheet(0)->getHighestColumn());

        $book->disconnectWorksheets();
    }

    public function test_a_print_area_that_starts_below_row_one_is_honoured(): void
    {
        $book = IOFactory::createReader('Xlsx')->load($this->form());

        // C2's print area is A2:M48 — the first row is not part of it.
        $this->assertSame('A2:M48', str_replace('$', '', $book->getSheet(1)->getPageSetup()->getPrintArea()));

        $method = new ReflectionMethod(XlsxToPdfService::class, 'trimToPrintAreas');
        $method->setAccessible(true);
        $method->invoke(app(XlsxToPdfService::class), $book);

        $this->assertSame(47, $book->getSheet(1)->getHighestRow());
        $this->assertSame('M', $book->getSheet(1)->getHighestColumn());

        $book->disconnectWorksheets();
    }

    public function test_each_sheet_keeps_its_own_margins(): void
    {
        $book = IOFactory::createReader('Xlsx')->load($this->form());

        $margins = collect($book->getAllSheets())
            ->filter(fn ($sheet) => $sheet->getSheetState() === 'visible')
            ->map(fn ($sheet) => round($sheet->getPageMargins()->getTop() * 25.4, 1));

        // The sheets genuinely differ — 9.1mm on one, 4.0mm on another — so a
        // single margin for the whole document cannot be right for all of them.
        $this->assertGreaterThan(1, $margins->unique()->count());

        $method = new ReflectionMethod(XlsxToPdfService::class, 'workbookHtml');
        $method->setAccessible(true);
        $html = $method->invoke(app(XlsxToPdfService::class), $book, 'portrait');

        // Each sheet carries its own padding on top of the shared page box.
        $this->assertGreaterThanOrEqual(4, preg_match_all('/class="xlsx-sheet" style="padding:/', $html));

        $book->disconnectWorksheets();
    }

    public function test_the_forms_own_fonts_and_rules_survive(): void
    {
        $book = IOFactory::createReader('Xlsx')->load($this->form());

        $method = new ReflectionMethod(XlsxToPdfService::class, 'workbookHtml');
        $method->setAccessible(true);
        $html = $method->invoke(app(XlsxToPdfService::class), $book, 'portrait');

        // The form is set in Arial and Arial Narrow, ruled throughout, with
        // shaded section headings. All of it comes from the uploaded file.
        $this->assertStringContainsString('Arial', $html);
        $this->assertGreaterThan(1000, preg_match_all('/font-family:/i', $html));
        $this->assertGreaterThan(1000, preg_match_all('/border-(top|bottom|left|right):/i', $html));
        $this->assertGreaterThan(100, preg_match_all('/background-color:/i', $html));

        // Column widths, or every column renders the same width.
        $this->assertGreaterThan(0, preg_match_all('/<col style="[^"]*width/i', $html));

        $book->disconnectWorksheets();
    }

    public function test_the_forms_wording_is_carried_over(): void
    {
        $book = IOFactory::createReader('Xlsx')->load($this->form());

        $method = new ReflectionMethod(XlsxToPdfService::class, 'workbookHtml');
        $method->setAccessible(true);
        $text = preg_replace('/\s+/', ' ',
            html_entity_decode(strip_tags($method->invoke(app(XlsxToPdfService::class), $book, 'portrait'))));

        foreach ([
            'PERSONAL DATA SHEET',
            'CIVIL SERVICE ELIGIBILITY',
            'WORK EXPERIENCE',
            'VOLUNTARY WORK',
            'LEARNING AND DEVELOPMENT',
            'REFERENCES',
        ] as $heading) {
            $this->assertStringContainsString($heading, $text, "“{$heading}” was lost.");
        }

        $book->disconnectWorksheets();
    }

    public function test_conversion_finishes_well_inside_a_request(): void
    {
        $started = microtime(true);
        $this->convert();
        $elapsed = microtime(true) - $started;

        // Shared hosting commonly caps a request at 30s, and the first person
        // to open a PDS pays this cost before the cache is warm.
        $this->assertLessThan(25, $elapsed,
            "Conversion took {$elapsed}s, which risks a timeout on shared hosting.");
    }
}
