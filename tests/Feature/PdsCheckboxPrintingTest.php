<?php

namespace Tests\Feature;

use App\Services\Xlsx\CheckboxFlattener;
use App\Services\Xlsx\TemplateFiller;
use App\Services\XlsxToPdfService;
use Tests\TestCase;

/**
 * The PDS's tick boxes, printed the way Excel prints them.
 *
 * LibreOffice draws a ticked form-control check box as a crossed box and cuts
 * captions short ("Widow..."), so for printing every check box is redrawn as a
 * plain square with a check mark and its full caption, and the control itself
 * is marked non-printing. These pin that down, and that the filler ticks the
 * box it was asked to and no other.
 */
class PdsCheckboxPrintingTest extends TestCase
{
    private string $work;

    protected function setUp(): void
    {
        parent::setUp();

        $this->work = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pds-checkbox-' . uniqid();
        mkdir($this->work, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->work . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->work);

        parent::tearDown();
    }

    private function filled(callable $fill): string
    {
        $path = $this->work . DIRECTORY_SEPARATOR . 'filled.xlsx';
        $filler = new TemplateFiller(resource_path('templates/CS-Form-212-2026.xlsx'), $path);
        $fill($filler);

        return $filler->save();
    }

    public function test_every_check_box_on_the_form_is_redrawn_for_printing(): void
    {
        $path = $this->filled(function (TemplateFiller $f) {
            $f->setCheckbox('C1', 'Female', true);
            $f->setCheckbox('C4', 'YES', true, 4103);
        });

        $zip = new \ZipArchive();
        $zip->open($path);
        $redrawn = (new CheckboxFlattener())->flatten($zip);
        $zip->close();

        // 11 on page 1 and 24 on page 4.
        $this->assertSame(35, $redrawn);

        $zip->open($path);
        $page1 = $zip->getFromName('xl/drawings/drawing1.xml');
        $page4 = $zip->getFromName('xl/drawings/drawing2.xml');
        $vml1 = $zip->getFromName('xl/drawings/vmlDrawing1.vml');
        $zip->close();

        // One check mark per ticked box, and only there.
        $this->assertSame(1, substr_count($page1, 'name="Check mark"'));
        $this->assertSame(1, substr_count($page4, 'name="Check mark"'));
        $this->assertSame(11, substr_count($page1, 'name="Check box square"'));

        // The captions are carried over whole, in the control's own font.
        $this->assertStringContainsString('<a:t>Widowed</a:t>', $page1);
        $this->assertStringContainsString('<a:t>by birth</a:t>', $page1);
        $this->assertStringContainsString('typeface="Tahoma"', $page1);
        $this->assertStringContainsString('sz="800"', $page1);

        // The original controls no longer print — no crossed boxes underneath.
        $this->assertSame(11, substr_count($vml1, '<x:PrintObject>False</x:PrintObject>'));
    }

    public function test_redrawing_happens_on_the_conversion_copy_only(): void
    {
        $path = $this->filled(fn (TemplateFiller $f) => $f->setCheckbox('C1', 'Male', true));
        $before = hash_file('sha256', $path);

        $service = app(XlsxToPdfService::class);
        $prepare = new \ReflectionMethod($service, 'prepareWorkbook');

        $copy = $this->work . DIRECTORY_SEPARATOR . 'copy.xlsx';
        copy($path, $copy);
        $prepare->invoke($service, $copy, true);

        // The copy is prepared …
        $zip = new \ZipArchive();
        $zip->open($copy);
        $this->assertSame(1, substr_count($zip->getFromName('xl/drawings/drawing1.xml'), 'name="Check mark"'));
        $zip->close();

        // … and the stored workbook keeps its real, clickable controls.
        $this->assertSame($before, hash_file('sha256', $path));
    }

    public function test_every_pds_page_is_printed_with_the_same_margins(): void
    {
        $copy = $this->work . DIRECTORY_SEPARATOR . 'pds.xlsx';
        copy(resource_path('templates/CS-Form-212-2026.xlsx'), $copy);

        $service = app(XlsxToPdfService::class);
        (new \ReflectionMethod($service, 'prepareWorkbook'))->invoke($service, $copy, true);

        $zip = new \ZipArchive();
        $zip->open($copy);

        foreach (range(1, 4) as $sheet) {
            $xml = $zip->getFromName("xl/worksheets/sheet{$sheet}.xml");

            // No per-page custom view with its own scale and margins …
            $this->assertStringNotContainsString('<customSheetViews>', $xml, "sheet{$sheet}");
            // … but one width-fit, the same margins, centred.
            $this->assertMatchesRegularExpression('/<pageSetup\b[^>]*fitToWidth="1" fitToHeight="0"/', $xml, "sheet{$sheet}");
            $this->assertStringContainsString('<pageMargins left="0.25" right="0.25"', $xml, "sheet{$sheet}");
            $this->assertStringContainsString('horizontalCentered="1"', $xml, "sheet{$sheet}");
        }

        $zip->close();
    }

    public function test_other_forms_keep_their_own_page_setup(): void
    {
        $copy = $this->work . DIRECTORY_SEPARATOR . 'leave.xlsx';
        copy(resource_path('templates/leave-form-template.xlsx'), $copy);

        $zip = new \ZipArchive();
        $zip->open($copy);
        preg_match('/<pageMargins\b[^>]*\/>/', $zip->getFromName('xl/worksheets/sheet1.xml'), $before);
        $zip->close();

        $service = app(XlsxToPdfService::class);
        (new \ReflectionMethod($service, 'prepareWorkbook'))->invoke($service, $copy, true);

        $zip->open($copy);
        $this->assertStringContainsString($before[0], $zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();
    }

    public function test_an_ambiguous_caption_is_refused_rather_than_guessed(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('More than one check box');

        $this->filled(fn (TemplateFiller $f) => $f->setCheckbox('C4', 'YES', true));
    }

    public function test_a_control_id_with_the_wrong_caption_is_refused(): void
    {
        $this->expectException(\RuntimeException::class);

        // 4098 is question 34a's NO box, not a YES.
        $this->filled(fn (TemplateFiller $f) => $f->setCheckbox('C4', 'YES', true, 4098));
    }

    public function test_a_workbook_without_check_boxes_is_left_alone(): void
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $path = $this->work . DIRECTORY_SEPARATOR . 'plain.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);
        $before = hash_file('sha256', $path);

        $zip = new \ZipArchive();
        $zip->open($path);
        $this->assertSame(0, (new CheckboxFlattener())->flatten($zip));
        $zip->close();

        $this->assertSame($before, hash_file('sha256', $path));
    }
}
