<?php

namespace Tests\Feature;

use App\Services\XlsxToPdfService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Without LibreOffice the export must still be the campus form on A4.
 *
 * Fidelity is checked on the generated HTML rather than the PDF's text: the
 * embedded subset fonts hex-encode their strings, so reading words back out
 * of the PDF is unreliable and would make these tests lie.
 */
class PdfA4FidelityTest extends TestCase
{
    private const A4_PORTRAIT = '595.3 x 841.9';
    private const A4_LANDSCAPE = '841.9 x 595.3';

    protected function setUp(): void
    {
        parent::setUp();
        config(['pdf.renderer' => 'php']);
    }

    /** A wide ledger-shaped workbook, supplied as US Letter like the real one. */
    private function ledger(string $orientation = PageSetup::ORIENTATION_LANDSCAPE): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('BALANCE');

        foreach (range('A', 'N') as $i => $column) {
            $sheet->getColumnDimension($column)->setWidth($i === 0 ? 22 : 11);
        }

        $sheet->mergeCells('A1:N1');
        $sheet->setCellValue('A1', 'EMPLOYEE LEAVE LEDGER CARD');
        $sheet->setCellValue('A2', 'NAME:');
        $sheet->setCellValue('B2', 'DELA CRUZ, JUAN P.');

        $sheet->fromArray([
            'PERIOD', 'VL EARNED', 'VL USED', 'VL W/O PAY', 'VL BAL',
            'SL EARNED', 'SL USED', 'SL W/O PAY', 'SL BAL', 'SERVICE',
            'TOTAL', 'DATE', 'BY', 'REMARKS',
        ], null, 'A4');

        $sheet->getStyle('A4:N4')->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9D9D9');

        for ($row = 0; $row < 20; $row++) {
            $sheet->fromArray(
                ['Jan ' . (2000 + $row), '1.25', '0.00', '0.00', '1.25', '1.25',
                 '0.00', '0.00', '1.25', '0.00', '2.50', '01/31', 'HR', 'Monthly accrual'],
                null,
                'A' . (5 + $row),
            );
        }

        $sheet->getStyle('A4:N24')->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // The client's templates are US Letter — the whole reason A4 is forced.
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER);
        $sheet->getPageSetup()->setOrientation($orientation);

        $path = tempnam(sys_get_temp_dir(), 'a4') . '.xlsx';
        (new XlsxWriter($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    /** Every distinct page box in the PDF, as "width x height" in points. */
    private function pageBoxes(string $pdf): array
    {
        preg_match_all(
            '/MediaBox\s*\[\s*([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s*\]/',
            file_get_contents($pdf),
            $matches,
        );

        $boxes = [];
        foreach ($matches[3] as $i => $width) {
            $boxes[] = round((float) $width, 1) . ' x ' . round((float) $matches[4][$i], 1);
        }

        return array_values(array_unique($boxes));
    }

    private function htmlFor(string $xlsx): string
    {
        $book = IOFactory::createReader('Xlsx')->load($xlsx);

        $method = new ReflectionMethod(XlsxToPdfService::class, 'workbookHtml');
        $method->setAccessible(true);

        $html = $method->invoke(
            app(XlsxToPdfService::class),
            $book,
            $book->getSheet(0)->getPageSetup()->getOrientation(),
        );

        $book->disconnectWorksheets();

        return $html;
    }

    // ------------------------------------------------------------------
    // A4
    // ------------------------------------------------------------------

    public function test_a_letter_sized_template_comes_out_a4(): void
    {
        $pdf = app(XlsxToPdfService::class)->convert($this->ledger(), true, false);

        $this->assertSame([self::A4_LANDSCAPE], $this->pageBoxes($pdf));
    }

    public function test_a_portrait_template_stays_portrait(): void
    {
        // The PDS is portrait. Forcing landscape prints it on its side.
        $pdf = app(XlsxToPdfService::class)
            ->convert($this->ledger(PageSetup::ORIENTATION_PORTRAIT), true, false);

        $this->assertSame([self::A4_PORTRAIT], $this->pageBoxes($pdf));
    }

    public function test_a_landscape_template_stays_landscape(): void
    {
        $pdf = app(XlsxToPdfService::class)
            ->convert($this->ledger(PageSetup::ORIENTATION_LANDSCAPE), true, false);

        $this->assertSame([self::A4_LANDSCAPE], $this->pageBoxes($pdf));
    }

    public function test_every_page_of_a_long_card_is_a4(): void
    {
        // A card that spills onto a second page must not change paper midway.
        $pdf = app(XlsxToPdfService::class)->convert($this->ledger(), true, false);

        $this->assertCount(1, $this->pageBoxes($pdf),
            'The document mixes more than one page size.');
    }

    // ------------------------------------------------------------------
    // Matching the template
    // ------------------------------------------------------------------

    public function test_no_column_is_dropped_on_the_way_to_a4(): void
    {
        $html = $this->htmlFor($this->ledger());
        $text = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html)));

        // The right-hand columns are the ones a Letter-sized page crops.
        foreach ([
            'EMPLOYEE LEAVE LEDGER CARD', 'DELA CRUZ, JUAN P.',
            'PERIOD', 'VL BAL', 'SL BAL', 'REMARKS', 'Monthly accrual',
            'Jan 2000', 'Jan 2019',
        ] as $content) {
            $this->assertStringContainsString($content, $text,
                "“{$content}” did not survive conversion.");
        }
    }

    public function test_the_table_is_held_to_the_printable_width(): void
    {
        $html = $this->htmlFor($this->ledger());

        // Excel's "fit to width: 1 page", expressed in CSS. Without it a wide
        // sheet runs off the page and the overflow is silently clipped.
        $this->assertStringContainsString('table-layout: fixed', $html);
        $this->assertStringContainsString('width: 100%', $html);
    }

    public function test_the_page_rule_names_a4_and_the_right_orientation(): void
    {
        $this->assertStringContainsString('size: A4 landscape', $this->htmlFor($this->ledger()));

        $this->assertStringContainsString(
            'size: A4 portrait',
            $this->htmlFor($this->ledger(PageSetup::ORIENTATION_PORTRAIT)),
        );
    }

    public function test_the_forms_own_layout_is_preserved(): void
    {
        $html = $this->htmlFor($this->ledger());

        // Column proportions, merged headings and ruled cells are what make
        // it the official form rather than a table of the same numbers.
        $this->assertGreaterThanOrEqual(14, preg_match_all('/<col[^>]*width/i', $html),
            'Column widths were not carried over.');

        $this->assertMatchesRegularExpression('/colspan="1[0-9]"/', $html,
            'The merged title row was flattened.');

        $this->assertMatchesRegularExpression('/border(-\w+)?:\s*\d/i', $html,
            'Cell borders were dropped.');

        $this->assertMatchesRegularExpression('/background(-color)?:\s*#?D9D9D9/i', $html,
            'Header shading was dropped.');
    }

    public function test_a_repeating_header_survives_a_page_break(): void
    {
        $html = $this->htmlFor($this->ledger());

        $this->assertStringContainsString('table-header-group', $html);
        $this->assertStringContainsString('page-break-inside: avoid', $html);
    }

    // ------------------------------------------------------------------
    // Long text
    // ------------------------------------------------------------------

    /** A ledger-shaped sheet with one very long reason in the last column. */
    private function ledgerWithLongReason(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('BALANCE');

        foreach (range('A', 'F') as $i => $column) {
            $sheet->getColumnDimension($column)->setWidth($column === 'F' ? 28 : 12);
        }

        $sheet->setCellValue('A1', 'EMPLOYEE LEAVE LEDGER CARD');
        $sheet->fromArray(['PERIOD', 'EARNED', 'USED', 'BALANCE', 'DATE', 'REMARKS'], null, 'A3');
        $sheet->fromArray(['Jan 2026', '1.25', '0.00', '1.25', '01/31', 'Monthly accrual'], null, 'A4');
        $sheet->fromArray([
            'Feb 2026', '1.25', '3.00', '-0.50', '02/28',
            'Vacation leave to attend the Regional Training Workshop on Records '
            . 'Management and Digital Archiving held at the Provincial Capitol, '
            . 'Vigan City, as endorsed by the Office of the Campus Director.',
        ], null, 'A5');

        $sheet->getStyle('A3:F5')->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER);
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);

        $path = tempnam(sys_get_temp_dir(), 'long') . '.xlsx';
        (new XlsxWriter($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    public function test_a_long_reason_is_kept_in_full(): void
    {
        $html = $this->htmlFor($this->ledgerWithLongReason());
        $text = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html)));

        // Nothing may be truncated to make a line fit its column.
        $this->assertStringContainsString('Regional Training Workshop', $text);
        $this->assertStringContainsString('Office of the Campus Director.', $text);
    }

    public function test_a_long_reason_wraps_inside_its_cell(): void
    {
        $html = $this->htmlFor($this->ledgerWithLongReason());

        // The cell has to grow to hold the text rather than let it run across
        // the neighbouring columns or off the edge of the page.
        $this->assertStringContainsString('overflow-wrap: break-word', $html);
        $this->assertStringNotContainsString('white-space: nowrap', $html);
    }

    public function test_a_card_that_outgrows_a_page_is_not_shrunk_to_illegibility(): void
    {
        $method = new ReflectionMethod(XlsxToPdfService::class, 'fittedScale');
        $method->setAccessible(true);

        $book = IOFactory::createReader('Xlsx')->load($this->ledger());
        $sheet = $book->getSheet(0);

        // Pretend the card must fit one page, as the real ledger template asks.
        $sheet->getPageSetup()->setFitToHeight(1);
        $sheet->getPageSetup()->setScale(72);

        $writer = new ReflectionMethod(XlsxToPdfService::class, 'sheetWriter');
        $writer->setAccessible(true);
        $body = $writer->invoke(app(XlsxToPdfService::class), $book, 0)->generateSheetData();

        $scale = $method->invoke(
            app(XlsxToPdfService::class),
            $sheet,
            $body,
            PageSetup::ORIENTATION_LANDSCAPE,
            ['top' => 3.0, 'right' => 0.0, 'bottom' => 3.0, 'left' => 0.0],
            'padding: 0;',
        );

        // A running record grows; squeezing years of entries onto one sheet
        // would make it unreadable. It prints at the size the form was drawn
        // for and runs onto another page instead.
        $this->assertGreaterThanOrEqual(42, $scale,
            'The card was shrunk past the point of being readable.');
        $this->assertLessThanOrEqual(72, $scale);

        $book->disconnectWorksheets();
    }
}
