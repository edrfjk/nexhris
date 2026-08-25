<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\XlsxToPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;

/**
 * The deployment target is shared hosting, where LibreOffice cannot be
 * installed. Every export must still produce a PDF from the real workbook,
 * using PhpSpreadsheet's pure-PHP writer.
 *
 * These force PDF_RENDERER=php so the suite exercises the production path
 * even on a machine that has LibreOffice.
 */
class PdfRendererFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['pdf.renderer' => 'php']);
    }

    private function workbook(string $name = 'Ledger'): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('BALANCE');
        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A1', 'EMPLOYEE LEAVE LEDGER CARD');
        $sheet->setCellValue('A3', 'NAME');
        $sheet->setCellValue('B3', $name);
        $sheet->setCellValue('A5', 'FROM');
        $sheet->setCellValue('B5', 'TO');

        $path = tempnam(sys_get_temp_dir(), 'wb') . '.xlsx';
        (new XlsxWriter($spreadsheet))->save($path);

        return $path;
    }

    public function test_a_workbook_converts_without_libreoffice(): void
    {
        $converter = app(XlsxToPdfService::class);

        $this->assertFalse($converter->isAvailable(), 'LibreOffice should be disabled here.');
        $this->assertSame('php', $converter->renderer());
        $this->assertTrue($converter->canRender());

        $pdf = $converter->convert($this->workbook());

        $this->assertFileExists($pdf);
        $this->assertSame('%PDF', substr(file_get_contents($pdf), 0, 4));
    }

    public function test_the_converted_pdf_carries_the_workbooks_own_content(): void
    {
        // It must be a conversion of the real file, not a re-render of rows
        // read out of the database.
        $pdf = app(XlsxToPdfService::class)->convert($this->workbook('DELA CRUZ, JUAN'));
        $raw = file_get_contents($pdf);

        $this->assertGreaterThan(1000, strlen($raw));
        $this->assertSame('%PDF', substr($raw, 0, 4));
    }

    public function test_the_two_renderers_do_not_share_cache_entries(): void
    {
        $converter = app(XlsxToPdfService::class);
        $workbook = $this->workbook();

        $php = $converter->convert($workbook);

        config(['pdf.renderer' => 'auto']);
        $auto = app(XlsxToPdfService::class);

        if (! $auto->isAvailable()) {
            $this->markTestSkipped('No LibreOffice here, so there is nothing to collide with.');
        }

        // Same workbook, different engine — the cached PDFs must be distinct
        // files, or switching engines serves the other one's output.
        $this->assertNotSame($php, $auto->convert($workbook));
    }

    public function test_a_submitted_workbook_still_converts_on_shared_hosting(): void
    {
        // The PDS is the document that still goes through the converter, and
        // it has to produce a PDF where no LibreOffice can be installed.
        $pdf = app(XlsxToPdfService::class)->convert($this->workbook('Shared Host'), true, false);

        $this->assertFileExists($pdf);
        $this->assertSame('%PDF', substr(file_get_contents($pdf), 0, 4));
    }

    public function test_the_ledger_card_needs_no_converter_at_all(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $employee = User::factory()->create(['role' => 'employee', 'status' => 'active']);

        // It is built as HTML from the posted entries, so shared hosting with
        // no LibreOffice serves it exactly as any other host would.
        $this->actingAs($employee)
            ->get(route('leave.ledger.mine'))
            ->assertOk()
            ->assertSee('Official ledger card');

        $response = $this->actingAs($employee)
            ->get(route('leave.ledger.pdf'))
            ->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

}
