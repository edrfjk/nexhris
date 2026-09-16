<?php

namespace Tests\Feature;

use App\Services\Xlsx\SheetPainter;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

/**
 * The painter, on its own.
 *
 * TemplateFidelityTest checks that the campus forms come out right. These
 * check the rules underneath that, on workbooks built here rather than on the
 * published templates, so a failure says which rule broke instead of only that
 * the leave form looks wrong.
 *
 * Everything is asserted on the painted markup. The geometry is the point: a
 * cell that lands at the wrong offset is a form that no longer matches the one
 * the employee filled in, and that is visible in the HTML long before anyone
 * opens the PDF.
 */
class SheetPainterTest extends TestCase
{
    /** Paints a workbook built in memory. */
    private function paint(callable $build, ?string $path = null): string
    {
        $book = new Spreadsheet();
        $build($book->getActiveSheet(), $book);

        $html = (new SheetPainter())->paintWorkbook($book, [0], $path);
        $book->disconnectWorksheets();

        return $html;
    }

    /** Every "left:NNpt" in the painted markup, in order. */
    private function offsets(string $html, string $property = 'left'): array
    {
        preg_match_all('/' . $property . ':([\d.]+)pt/', $html, $matches);

        return array_map('floatval', $matches[1]);
    }

    // ------------------------------------------------------------------
    // Geometry
    // ------------------------------------------------------------------

    /**
     * A column's width has to come from the workbook, not from its contents.
     *
     * This is the whole reason the painter exists. The HTML writer it replaced
     * sized columns to fit their text, so a long label widened its column and
     * pushed everything to the right of it out of place.
     */
    public function test_a_column_keeps_its_width_whatever_it_holds(): void
    {
        $narrow = $this->paint(function ($sheet) {
            $sheet->getColumnDimension('A')->setWidth(10);
            $sheet->getColumnDimension('B')->setWidth(10);
            $sheet->setCellValue('A1', 'x');
            $sheet->setCellValue('B1', 'y');
        });

        $wide = $this->paint(function ($sheet) {
            $sheet->getColumnDimension('A')->setWidth(10);
            $sheet->getColumnDimension('B')->setWidth(10);
            $sheet->setCellValue('A1', str_repeat('a very long label ', 12));
            $sheet->setCellValue('B1', 'y');
        });

        // B starts at the same offset in both, because A is still 10 wide.
        $this->assertSame(
            max($this->offsets($narrow)),
            max($this->offsets($wide)),
            'a long label moved the column beside it',
        );
    }

    public function test_a_hidden_column_takes_no_space(): void
    {
        $html = $this->paint(function ($sheet) {
            $sheet->getColumnDimension('A')->setWidth(20);
            $sheet->getColumnDimension('A')->setVisible(false);
            $sheet->setCellValue('B1', 'shown');
        });

        // The cell layer, not the page wrapper, which carries the margin.
        $this->assertStringContainsString(
            '<div class="x" style="left:0.00pt',
            $html,
            'a hidden column still occupied its width',
        );
    }

    public function test_a_merged_range_is_drawn_once_at_its_full_size(): void
    {
        $html = $this->paint(function ($sheet) {
            foreach (['A', 'B', 'C'] as $column) {
                $sheet->getColumnDimension($column)->setWidth(10);
            }

            $sheet->mergeCells('A1:C1');
            $sheet->setCellValue('A1', 'one wide heading');
        });

        $this->assertSame(1, substr_count($html, 'one wide heading'), 'the merged cell was drawn more than once');

        $widths = $this->offsets($html, 'width');

        // Three columns of 10 units are about 67pt each, so the merge spans
        // roughly 200pt — far wider than any single column.
        $this->assertGreaterThan(150, max($widths), 'the merge was not drawn at its full width');
    }

    // ------------------------------------------------------------------
    // Text
    // ------------------------------------------------------------------

    /**
     * Excel lets an unwrapped label run over the empty cells beside it, and
     * the campus forms depend on it — "OFFICE/DEPARTMENT" sits in a column
     * narrower than the word.
     */
    public function test_unwrapped_text_is_not_clipped_to_its_cell(): void
    {
        $html = $this->paint(function ($sheet) {
            $sheet->getColumnDimension('A')->setWidth(3);
            $sheet->setCellValue('A1', 'OFFICE/DEPARTMENT');
        });

        $this->assertStringContainsString('OFFICE/DEPARTMENT', $html);
        $this->assertStringContainsString('white-space:nowrap', $html, 'the label was allowed to wrap');
        $this->assertStringContainsString('class="x"', $html, 'text was drawn in the clipped layer');
    }

    public function test_a_wrapped_cell_wraps_instead(): void
    {
        $html = $this->paint(function ($sheet) {
            $sheet->getColumnDimension('A')->setWidth(6);
            $sheet->setCellValue('A1', 'a label long enough to need two lines');
            $sheet->getStyle('A1')->getAlignment()->setWrapText(true);
        });

        $this->assertStringContainsString('word-wrap:break-word', $html);
        $this->assertStringNotContainsString('white-space:nowrap', $html);
    }

    /**
     * Fills are painted before text so a later row cannot erase the end of a
     * label overflowing from the row above it.
     */
    public function test_fills_are_painted_underneath_every_label(): void
    {
        $html = $this->paint(function ($sheet) {
            $sheet->getColumnDimension('A')->setWidth(4);
            $sheet->setCellValue('A1', 'a label that runs on');
            $sheet->getStyle('A2')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('C0C0C0');
        });

        $fill = strpos($html, 'background:#C0C0C0');
        $label = strpos($html, 'a label that runs on');

        $this->assertNotFalse($fill);
        $this->assertNotFalse($label);
        $this->assertLessThan($label, $fill, 'a grey fill was painted over the text');
    }

    /**
     * Each run of a rich-text cell keeps its own size. On CS Form No. 6 every
     * leave type is a 10pt name followed by a 6pt citation; drawn at one size
     * the citation is too long and pushes into the next column.
     */
    public function test_rich_text_runs_keep_their_own_size(): void
    {
        $html = $this->paint(function ($sheet) {
            $rich = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
            $rich->createTextRun('Vacation Leave')->getFont()->setSize(10);
            $rich->createTextRun(' (Sec. 51, Rule XVI)')->getFont()->setSize(6);

            $sheet->getCell('A1')->setValue($rich);
        });

        $this->assertStringContainsString('Vacation Leave', $html);
        $this->assertStringContainsString('Sec. 51, Rule XVI', $html);
        preg_match_all('/font-size:([\d.]+)em/', $html, $sizes);

        $this->assertCount(2, $sizes[1], 'the two runs were not styled separately');
        $this->assertLessThan(
            (float) $sizes[1][0],
            (float) $sizes[1][1],
            'the citation was not set smaller than the leave type',
        );
    }

    public function test_numbers_are_shown_the_way_the_sheet_formats_them(): void
    {
        $html = $this->paint(function ($sheet) {
            $sheet->setCellValue('A1', 1.5);
            $sheet->getStyle('A1')->getNumberFormat()->setFormatCode('0.000');
        });

        $this->assertStringContainsString('1.500', $html, 'the number format was ignored');
    }

    // ------------------------------------------------------------------
    // Borders
    // ------------------------------------------------------------------

    /**
     * The line between two cells belongs to one of them, not both.
     *
     * CS Form 212 stores every vertical rule as the right border of the cell
     * to its left, so a renderer that draws only each cell's own four sides
     * draws the form with no left edges and the grid falls apart.
     */
    public function test_an_edge_is_drawn_when_only_the_neighbour_declares_it(): void
    {
        $html = $this->paint(function ($sheet) {
            $sheet->setCellValue('A1', 'left cell');
            $sheet->setCellValue('B1', 'right cell');

            // Only A1 says there is a line here. B1 says nothing.
            $sheet->getStyle('A1')->getBorders()->getRight()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        });

        $this->assertStringContainsString('border-right:', $html, 'the owner of the edge lost it');
        $this->assertStringContainsString('border-left:', $html, 'the neighbour did not inherit the edge');
    }

    public function test_a_merged_range_takes_its_far_edges_from_the_far_cells(): void
    {
        $html = $this->paint(function ($sheet) {
            $sheet->mergeCells('A1:C1');
            $sheet->setCellValue('A1', 'merged');

            // The right edge of the range lives on its last cell, not the one
            // the merge is anchored to.
            $sheet->getStyle('C1')->getBorders()->getRight()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        });

        $this->assertStringContainsString('border-right:', $html, 'the far edge of the merge was not drawn');
    }

    // ------------------------------------------------------------------
    // Style isolation
    // ------------------------------------------------------------------

    /**
     * Worksheet::getStyle() hands back a supervisor bound to the sheet's
     * current selection, not an independent object, so reading a neighbour's
     * border re-points a style already held. That made every field on the form
     * take a neighbour's alignment: centred values printed hard against the
     * rule, left-aligned labels printed right.
     *
     * The neighbours here are deliberately given the opposite alignment, so if
     * the values are ever read late again this fails immediately.
     */
    public function test_a_cell_keeps_its_own_alignment_while_neighbours_are_read(): void
    {
        $html = $this->paint(function ($sheet) {
            foreach (['A', 'B', 'C'] as $column) {
                $sheet->getColumnDimension($column)->setWidth(12);
            }

            $sheet->setCellValue('A1', 'ragged left');
            $sheet->setCellValue('B1', 'centred');
            $sheet->setCellValue('C1', 'ragged right');

            $sheet->getStyle('A1')->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle('B1')->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('C1')->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

            // Borders on the neighbours force the composing lookups that
            // used to corrupt the style being drawn.
            $sheet->getStyle('A1:C1')->getBorders()->getAllBorders()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        });

        foreach ([['centred', 'center'], ['ragged right', 'right']] as [$text, $expected]) {
            $at = strpos($html, '>' . $text . '<');
            $this->assertNotFalse($at, "{$text} was not drawn");

            $style = substr($html, max(0, $at - 400), 400);

            $this->assertStringContainsString(
                "text-align:{$expected};",
                $style,
                "{$text} was drawn with a neighbour's alignment",
            );
        }
    }

    // ------------------------------------------------------------------
    // Vertical placement
    // ------------------------------------------------------------------

    /**
     * Dompdf ignores vertical-align on a table-cell — every variant lays the
     * text against the top of the box — so the offset is arithmetic instead.
     * Without it a form of vertically centred fields prints with every value
     * sitting on the rule above it.
     */
    public function test_a_centred_cell_is_pushed_down_by_half_the_spare_room(): void
    {
        $html = $this->paint(function ($sheet) {
            $sheet->getRowDimension(1)->setRowHeight(60);
            $sheet->setCellValue('A1', 'middle');
            $sheet->getStyle('A1')->getAlignment()
                ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        });

        $this->assertMatchesRegularExpression(
            '/padding-top:\d+\.\d+pt/',
            $html,
            'a vertically centred cell was left against the top of its box',
        );
    }

    public function test_a_top_aligned_cell_is_not_pushed_down(): void
    {
        $html = $this->paint(function ($sheet) {
            $sheet->getRowDimension(1)->setRowHeight(60);
            $sheet->setCellValue('A1', 'top');
            $sheet->getStyle('A1')->getAlignment()
                ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
        });

        $this->assertStringNotContainsString('padding-top:', $html);
    }

    // ------------------------------------------------------------------
    // Paper
    // ------------------------------------------------------------------

    public function test_a_sheet_wider_than_the_paper_is_scaled_down_not_cropped(): void
    {
        $html = $this->paint(function ($sheet) {
            foreach (range('A', 'T') as $column) {
                $sheet->getColumnDimension($column)->setWidth(20);
                $sheet->setCellValue($column . '1', 'x');
            }
        });

        // A4 is 595pt wide; nothing may be placed beyond it.
        foreach ($this->offsets($html) as $offset) {
            $this->assertLessThan(596, $offset, 'content was placed off the page');
        }
    }

    public function test_a_landscape_sheet_gets_a_landscape_page(): void
    {
        $html = $this->paint(function ($sheet) {
            $sheet->getPageSetup()->setOrientation(
                \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE
            );
            $sheet->setCellValue('A1', 'wide');
        });

        $this->assertStringContainsString('width:841.89pt', $html);
        $this->assertStringContainsString('height:595.28pt', $html);
    }

    public function test_only_the_print_area_is_drawn(): void
    {
        $html = $this->paint(function ($sheet) {
            $sheet->setCellValue('A1', 'inside the print area');
            $sheet->setCellValue('E9', 'a working note nobody prints');
            $sheet->getPageSetup()->setPrintArea('A1:B2');
        });

        $this->assertStringContainsString('inside the print area', $html);
        $this->assertStringNotContainsString('a working note nobody prints', $html);
    }

    // ------------------------------------------------------------------
    // Without the package
    // ------------------------------------------------------------------

    /**
     * The grid still paints when the .xlsx path is not supplied. Shapes and
     * form controls cannot, since they are read from the package, but losing
     * them should not cost the rest of the form.
     */
    public function test_the_grid_paints_without_the_workbook_file(): void
    {
        $html = $this->paint(fn ($sheet) => $sheet->setCellValue('A1', 'still drawn'), null);

        $this->assertStringContainsString('still drawn', $html);
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
    }

    public function test_every_printable_sheet_becomes_its_own_page(): void
    {
        $path = resource_path('templates/CS-Form-212-2026.xlsx');

        if (! is_file($path)) {
            $this->markTestSkipped('The CS Form 212 template is not bundled.');
        }

        $book = IOFactory::createReader('Xlsx')->load($path);
        $sheets = range(0, $book->getSheetCount() - 1);

        $html = (new SheetPainter())->paintWorkbook($book, $sheets, $path);
        $book->disconnectWorksheets();

        $this->assertSame(
            count($sheets),
            substr_count($html, 'class="pg"'),
            'the workbook did not produce one page per sheet',
        );
    }
}
