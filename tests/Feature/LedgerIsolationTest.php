<?php

namespace Tests\Feature;

use App\Services\XlsxToPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;

/**
 * Converted documents belong to one person.
 *
 * The PDF cache was keyed on file content alone. Two people's workbooks are
 * often byte-identical — a PDS filled from the same blank template, a ledger
 * copied from the same master — so whoever converted first was served to
 * everybody else. The key now carries the owner as well.
 */
class LedgerIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! app(XlsxToPdfService::class)->canRender()) {
            $this->markTestSkipped('No PDF renderer is available.');
        }
    }

    /** Two identical workbooks, as two people filling the same blank form. */
    private function workbook(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('FORM');
        $sheet->setCellValue('A1', 'PERSONAL DATA SHEET');

        $path = tempnam(sys_get_temp_dir(), 'iso') . '.xlsx';
        (new XlsxWriter($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    public function test_identical_workbooks_do_not_share_a_cached_pdf(): void
    {
        $converter = app(XlsxToPdfService::class);

        // Copied rather than written twice: an .xlsx carries a creation
        // timestamp, so two written a second apart are not byte-identical
        // and the fixture would stop reproducing the case it exists for.
        $first = $this->workbook();
        $second = tempnam(sys_get_temp_dir(), 'iso') . '.xlsx';
        copy($first, $second);

        // The condition the old key could not survive.
        $this->assertSame(
            hash_file('sha256', $first),
            hash_file('sha256', $second),
            'The fixture no longer reproduces the identical-workbook case.',
        );

        $this->assertNotSame(
            $converter->convert($first, true, true, 'owner:1'),
            $converter->convert($second, true, true, 'owner:2'),
            "Two people's documents resolved to one cached PDF.",
        );
    }

    public function test_the_cache_still_reuses_one_owners_unchanged_document(): void
    {
        $converter = app(XlsxToPdfService::class);
        $workbook = $this->workbook();

        // Isolating owners must not cost the caching that keeps this quick.
        $this->assertSame(
            $converter->convert($workbook, true, true, 'owner:1'),
            $converter->convert($workbook, true, true, 'owner:1'),
        );
    }

    public function test_editing_a_document_produces_a_fresh_pdf(): void
    {
        $converter = app(XlsxToPdfService::class);
        $workbook = $this->workbook();

        $before = $converter->convert($workbook, true, true, 'owner:1');

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx')->load($workbook);
        $spreadsheet->getActiveSheet()->setCellValue('A2', 'Edited');
        (new XlsxWriter($spreadsheet))->save($workbook);
        $spreadsheet->disconnectWorksheets();

        // Content still drives invalidation, so a change is picked up.
        $this->assertNotSame($before, $converter->convert($workbook, true, true, 'owner:1'));
    }
}
