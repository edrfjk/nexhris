<?php

namespace App\Services\Xlsx;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Drawing;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Style;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Paints a workbook as HTML that reproduces the sheet's real geometry.
 *
 * The reason this exists rather than PhpSpreadsheet's own HTML writer: that
 * writer emits a <table>, and a table cannot hold Excel's grid still. Column
 * widths become suggestions the moment text is too long for them, so a form
 * laid out to the millimetre reflows — labels wrap into towers, sections slide
 * onto the next page, and the printed sheet stops matching the workbook the
 * employee filled in.
 *
 * So nothing here is laid out by the browser. Every cell is measured from the
 * workbook — column widths in Excel's own units, row heights in points, merged
 * ranges, borders, fills, fonts — converted to points, and placed at an
 * absolute offset. What Dompdf receives is a drawing, not a document, which is
 * why it comes out the same every time.
 *
 * Units are points throughout, because that is what a PDF measures in. Excel
 * gives column widths in character units and row heights already in points.
 *
 * Scaling is applied to the coordinates rather than through a CSS transform:
 * Dompdf's transform support is partial, and a form that silently fails to
 * scale prints off the edge of the paper.
 */
class SheetPainter
{
    /** A4 in points. */
    private const PAGE_WIDTH = 595.28;
    private const PAGE_HEIGHT = 841.89;

    private const LANDSCAPE_WIDTH = 841.89;
    private const LANDSCAPE_HEIGHT = 595.28;

    /**
     * Margins narrower than this are treated as printer bleed rather than a
     * design decision — the campus templates carry margins of zero on some
     * sheets, which no printer can honour.
     */
    private const MARGIN_FLOOR = 14.17;   // 5mm

    /** Pixels are 96/inch, points 72/inch. */
    private const PX_TO_PT = 0.75;

    /** Excel's own ratio of line box to font size, near enough for layout. */
    private const LINE_HEIGHT = 1.18;

    /**
     * The gutter Excel keeps between a cell's text and its edges: two pixels
     * each side. Small, but it is the whole reason "2." and "FIRST NAME" read
     * as one label across two cells rather than running together.
     */
    private const CELL_PADDING = 2 * self::PX_TO_PT;

    public function __construct(
        private readonly DrawingPainter $drawings = new DrawingPainter(),
        private readonly OverlayPainter $overlays = new OverlayPainter(),
    ) {
    }

    /**
     * The whole workbook as one HTML document, one page per printable sheet.
     *
     * A page per sheet is right for these forms rather than a guess: both
     * campus templates set fitToPage, which is Excel being told this sheet is
     * one page. The renderer honours that instead of paginating by height.
     */
    public function paintWorkbook(Spreadsheet $book, array $sheetIndexes, ?string $xlsxPath = null): string
    {
        // Shapes and form controls are read from the package itself, because
        // PhpSpreadsheet does not model either. Without the file we can still
        // draw the grid — just not the parts of the form that sit over it.
        $zip = null;

        if ($xlsxPath && is_file($xlsxPath)) {
            $zip = new \ZipArchive();

            if ($zip->open($xlsxPath) !== true) {
                $zip = null;
            }
        }

        $pages = [];

        foreach ($sheetIndexes as $index) {
            $pages[] = $this->paintSheet($book->getSheet($index), $zip, $index);
        }

        $zip?->close();

        $body = implode("\n", $pages);

        return <<<HTML
        <!DOCTYPE html>
        <html><head><meta charset="utf-8">
        <style>
          @page { margin: 0; }
          html, body { margin: 0; padding: 0; }
          .pg { position: relative; overflow: hidden; page-break-after: always; }
          .pg:last-child { page-break-after: auto; }
          .c  { position: absolute; overflow: hidden; }
          .x  { position: absolute; overflow: visible; box-sizing: border-box; }
        </style>
        </head><body>
        {$body}
        </body></html>
        HTML;
    }

    private function paintSheet(Worksheet $sheet, ?\ZipArchive $zip, int $sheetIndex): string
    {
        [$firstCol, $firstRow, $lastCol, $lastRow] = $this->printRange($sheet);

        $cols = $this->columnOffsets($sheet, $firstCol, $lastCol);
        $rows = $this->rowOffsets($sheet, $firstRow, $lastRow);

        $contentWidth = $cols['total'];
        $contentHeight = $rows['total'];

        $landscape = $sheet->getPageSetup()->getOrientation() === PageSetup::ORIENTATION_LANDSCAPE;

        $pageWidth = $landscape ? self::LANDSCAPE_WIDTH : self::PAGE_WIDTH;
        $pageHeight = $landscape ? self::LANDSCAPE_HEIGHT : self::PAGE_HEIGHT;

        $margins = $this->margins($sheet);

        $printableWidth = $pageWidth - $margins['left'] - $margins['right'];
        $printableHeight = $pageHeight - $margins['top'] - $margins['bottom'];

        // Never enlarge. A form drawn smaller than the page is a form with
        // room to spare, not one that wants stretching.
        $scale = min(1.0, $printableWidth / max(1, $contentWidth), $printableHeight / max(1, $contentHeight));

        $merges = $this->mergeMap($sheet);

        // Two passes, because Excel composites that way: every fill and
        // border first, then every piece of text over the top. Painted cell by
        // cell instead, a later row's grey fill lands on top of the text
        // overflowing from the row above it and erases the end of the label.
        $boxes = [];
        $texts = [];

        foreach ($rows['index'] as $row) {
            foreach ($cols['index'] as $col) {
                $ref = $col . $row;

                // Covered by a merge that starts elsewhere.
                if (($merges['covered'][$ref] ?? false) === true) {
                    continue;
                }

                [$spanCols, $spanRows] = $merges['spans'][$ref] ?? [1, 1];

                [$box, $text] = $this->paintCell(
                    $sheet, $col, $row, $spanCols, $spanRows, $cols, $rows, $scale
                );

                if ($box !== '') {
                    $boxes[] = $box;
                }

                if ($text !== '') {
                    $texts[] = $text;
                }
            }
        }

        $painted = implode('', $boxes) . implode('', $texts);

        $overlay = $this->drawings->paint($sheet, $cols, $rows, $scale);

        if ($zip) {
            $overlay .= $this->overlays->paint($zip, $sheetIndex, $cols, $rows, $scale);

            // A sheet with no cell content at all is not an empty sheet; on
            // CS Form No. 6 it is page two, whose entire content is a Word
            // document embedded as an OLE object.
            if ($painted === '') {
                $embedded = $this->overlays->embedded(
                    $zip,
                    $sheetIndex,
                    $printableWidth / max(0.0001, $scale),
                    $printableHeight / max(0.0001, $scale),
                    $scale,
                );

                if ($embedded !== null) {
                    $overlay .= $embedded;
                }
            }
        }

        return sprintf(
            '<div class="pg" style="width:%.2fpt;height:%.2fpt;">'
            . '<div style="position:absolute;left:%.2fpt;top:%.2fpt;">%s%s</div></div>',
            $pageWidth,
            $pageHeight,
            $margins['left'],
            $margins['top'],
            $painted,
            $overlay,
        );
    }

    // ------------------------------------------------------------------
    // Geometry
    // ------------------------------------------------------------------

    /** The sheet's print area, or everything it holds if none is set. */
    private function printRange(Worksheet $sheet): array
    {
        $area = $sheet->getPageSetup()->getPrintArea();

        if ($area) {
            // Only the first block; a multi-block print area is not something
            // the campus forms use.
            $area = explode(',', $area)[0];
            [$start, $end] = array_pad(explode(':', str_replace('$', '', $area)), 2, null);

            $from = Coordinate::coordinateFromString($start);
            $to = $end ? Coordinate::coordinateFromString($end) : $from;

            return [$from[0], (int) $from[1], $to[0], (int) $to[1]];
        }

        return ['A', 1, $sheet->getHighestColumn(), $sheet->getHighestRow()];
    }

    /**
     * Left edge and width of every column in the range, in points.
     *
     * Excel stores a column width as a count of digit-widths in the workbook's
     * default font, so it cannot be converted without knowing that font.
     */
    private function columnOffsets(Worksheet $sheet, string $firstCol, string $lastCol): array
    {
        $font = $sheet->getParent()->getDefaultStyle()->getFont();

        $defaultWidth = $sheet->getDefaultColumnDimension()->getWidth();

        if ($defaultWidth < 0) {
            $defaultWidth = 8.43;
        }

        $dimensions = $sheet->getColumnDimensions();

        $x = 0.0;
        $left = [];
        $width = [];
        $index = [];

        $first = Coordinate::columnIndexFromString($firstCol);
        $last = Coordinate::columnIndexFromString($lastCol);

        for ($i = $first; $i <= $last; $i++) {
            $col = Coordinate::stringFromColumnIndex($i);
            $dim = $dimensions[$col] ?? null;

            if ($dim && ! $dim->getVisible()) {
                $points = 0.0;
            } else {
                $units = ($dim && $dim->getWidth() >= 0) ? $dim->getWidth() : $defaultWidth;
                $points = Drawing::cellDimensionToPixels($units, $font) * self::PX_TO_PT;
            }

            $left[$col] = $x;
            $width[$col] = $points;
            $index[] = $col;
            $x += $points;
        }

        return ['left' => $left, 'width' => $width, 'index' => $index, 'total' => $x];
    }

    /** Top edge and height of every row in the range, in points. */
    private function rowOffsets(Worksheet $sheet, int $firstRow, int $lastRow): array
    {
        $defaultHeight = $sheet->getDefaultRowDimension()->getRowHeight();

        if ($defaultHeight < 0) {
            // Excel's own default for a 10pt font.
            $defaultHeight = 12.75;
        }

        $dimensions = $sheet->getRowDimensions();

        $y = 0.0;
        $top = [];
        $height = [];
        $index = [];

        for ($r = $firstRow; $r <= $lastRow; $r++) {
            $dim = $dimensions[$r] ?? null;

            if ($dim && ! $dim->getVisible()) {
                $points = 0.0;
            } else {
                $points = ($dim && $dim->getRowHeight() >= 0) ? $dim->getRowHeight() : $defaultHeight;
            }

            $top[$r] = $y;
            $height[$r] = $points;
            $index[] = $r;
            $y += $points;
        }

        return ['top' => $top, 'height' => $height, 'index' => $index, 'total' => $y];
    }

    /**
     * Which cells begin a merge and how far it runs, and which are covered by
     * one. A covered cell is not drawn at all — its borders belong to the
     * range that swallowed it.
     */
    private function mergeMap(Worksheet $sheet): array
    {
        $spans = [];
        $covered = [];

        foreach ($sheet->getMergeCells() as $range) {
            [$start, $end] = array_pad(explode(':', $range), 2, null);

            if (! $end) {
                continue;
            }

            [$startCol, $startRow] = Coordinate::coordinateFromString($start);
            [$endCol, $endRow] = Coordinate::coordinateFromString($end);

            $c1 = Coordinate::columnIndexFromString($startCol);
            $c2 = Coordinate::columnIndexFromString($endCol);

            $spans[$start] = [$c2 - $c1 + 1, (int) $endRow - (int) $startRow + 1];

            for ($i = $c1; $i <= $c2; $i++) {
                for ($r = (int) $startRow; $r <= (int) $endRow; $r++) {
                    $ref = Coordinate::stringFromColumnIndex($i) . $r;

                    if ($ref !== $start) {
                        $covered[$ref] = true;
                    }
                }
            }
        }

        return ['spans' => $spans, 'covered' => $covered];
    }

    /** Page margins in points, floored so nothing prints into the bleed. */
    private function margins(Worksheet $sheet): array
    {
        $m = $sheet->getPageMargins();

        return [
            'top' => max(self::MARGIN_FLOOR, $m->getTop() * 72),
            'right' => max(self::MARGIN_FLOOR, $m->getRight() * 72),
            'bottom' => max(self::MARGIN_FLOOR, $m->getBottom() * 72),
            'left' => max(self::MARGIN_FLOOR, $m->getLeft() * 72),
        ];
    }

    // ------------------------------------------------------------------
    // Cells
    // ------------------------------------------------------------------

    private function paintCell(
        Worksheet $sheet,
        string $col,
        int $row,
        int $spanCols,
        int $spanRows,
        array $cols,
        array $rows,
        float $scale,
    ): array {
        $ref = $col . $row;

        $width = 0.0;
        $start = Coordinate::columnIndexFromString($col);

        for ($i = $start; $i < $start + $spanCols; $i++) {
            $width += $cols['width'][Coordinate::stringFromColumnIndex($i)] ?? 0.0;
        }

        $height = 0.0;

        for ($r = $row; $r < $row + $spanRows; $r++) {
            $height += $rows['height'][$r] ?? 0.0;
        }

        if ($width <= 0 || $height <= 0) {
            return ['', ''];
        }

        // Read once, into plain values, before anything else touches a style.
        // snapshot() explains why holding the object instead is a trap.
        $cell = $this->snapshot($sheet, $ref);

        $text = $this->cellText($sheet, $ref, $cell);
        $box = $this->boxCss($sheet, $cell, $col, $row, $spanCols, $spanRows);

        // A cell with neither content nor decoration is the paper showing
        // through. Drawing it would multiply the page by thousands of divs.
        if ($text === '' && $box === '') {
            return ['', ''];
        }

        $position = sprintf(
            'left:%.2fpt;top:%.2fpt;width:%.2fpt;height:%.2fpt;',
            $cols['left'][$col] * $scale,
            $rows['top'][$row] * $scale,
            $width * $scale,
            $height * $scale,
        );

        $boxDiv = $box === ''
            ? ''
            : '<div class="c" style="' . $position . $box . '"></div>';

        if ($text === '') {
            return [$boxDiv, ''];
        }

        // Excel does not clip unwrapped text, it lets it run over the cells
        // beside it: left-aligned text spills right, right-aligned spills
        // left, centred spills both ways. The campus forms depend on it — the
        // label "OFFICE/DEPARTMENT" sits in a column 16pt wide.
        //
        // So the text box is the cell exactly, and overflow does the rest.
        // Computing a wider box instead would move the anchor, and centred
        // text would drift off the centre of its own cell.
        return [
            $boxDiv,
            '<div class="x" style="' . $position . '">'
            . '<div style="' . $this->textCss($cell, $scale, $width * $scale, $height * $scale) . '">'
            . $text
            . '</div></div>',
        ];
    }

    /**
     * Everything this cell's style says, as plain values.
     *
     * Worksheet::getStyle() does not hand back an independent object. It
     * returns a supervisor bound to the sheet's current selection, so the next
     * call to getStyle() for any other cell silently re-points the one already
     * held. Composing a border from the neighbours therefore rewrote the style
     * of the cell being drawn, and every field on the form came out with a
     * neighbour's alignment — centred values printed hard against the rule,
     * left-aligned labels printed right.
     *
     * Reading the values out immediately is what makes the rest of this class
     * safe to write in the obvious way.
     */
    private function snapshot(Worksheet $sheet, string $ref): array
    {
        $exists = $sheet->cellExists($ref);
        $value = $exists ? $sheet->getCell($ref)->getValue() : null;

        $style = $sheet->getStyle($ref);

        $font = $style->getFont();
        $align = $style->getAlignment();
        $fill = $style->getFill();

        $fillType = $fill->getFillType();
        $underline = $font->getUnderline();

        return [
            'font' => $font->getName(),
            'size' => (float) ($font->getSize() ?: 10),
            'bold' => (bool) $font->getBold(),
            'italic' => (bool) $font->getItalic(),
            'underline' => $underline && $underline !== Font::UNDERLINE_NONE,
            'colour' => (string) $font->getColor()->getRGB(),
            'horizontal' => (string) $align->getHorizontal(),
            'vertical' => (string) $align->getVertical(),
            'wrap' => (bool) $align->getWrapText(),
            'indent' => (int) $align->getIndent(),
            'fill' => ($fillType && $fillType !== Fill::FILL_NONE)
                ? (string) $fill->getStartColor()->getRGB()
                : null,
            'numeric' => is_numeric($value) && ! is_string($value),
            'plain' => $value instanceof RichText ? $value->getPlainText() : (string) $value,
        ];
    }

    /**
     * Borders and fill — everything that makes the cell visible when empty.
     *
     * The line between two cells belongs to one of them, not both, and which
     * one is Excel's business. On CS Form 212 every vertical rule is stored as
     * the right border of the cell to its left, so a renderer that draws only
     * each cell's own four sides draws a form with no left edges at all — the
     * grid falls apart into loose horizontal lines.
     *
     * So each edge is composed the way Excel composites it: this cell's own
     * border if it has one, otherwise the facing border of the neighbour
     * across that edge. For a merged range the edge is taken from the cells
     * along that side of the range, not from the anchor alone.
     */
    private function boxCss(
        Worksheet $sheet,
        array $cell,
        string $col,
        int $row,
        int $spanCols,
        int $spanRows,
    ): string {
        $first = Coordinate::columnIndexFromString($col);
        $lastCol = $first + $spanCols - 1;
        $lastRow = $row + $spanRows - 1;

        $edges = ['top' => null, 'right' => null, 'bottom' => null, 'left' => null];

        for ($i = $first; $i <= $lastCol; $i++) {
            $edges['top'] ??= $this->border($sheet, $i, $row, 'top')
                ?? $this->border($sheet, $i, $row - 1, 'bottom');

            $edges['bottom'] ??= $this->border($sheet, $i, $lastRow, 'bottom')
                ?? $this->border($sheet, $i, $lastRow + 1, 'top');
        }

        for ($r = $row; $r <= $lastRow; $r++) {
            $edges['left'] ??= $this->border($sheet, $first, $r, 'left')
                ?? $this->border($sheet, $first - 1, $r, 'right');

            $edges['right'] ??= $this->border($sheet, $lastCol, $r, 'right')
                ?? $this->border($sheet, $lastCol + 1, $r, 'left');
        }

        $css = '';

        foreach ($edges as $side => $border) {
            if ($border && ($rule = $this->borderCss($border))) {
                $css .= "border-{$side}:{$rule};";
            }
        }

        // White is the paper. Painting it would cover the shapes and controls
        // drawn underneath, and on these forms that is the seal and the boxes.
        if ($cell['fill'] !== null && strtoupper($cell['fill']) !== 'FFFFFF') {
            $css .= 'background:#' . $cell['fill'] . ';';
        }

        return $css;
    }

    /**
     * One side of one cell as plain values, or null when nothing is drawn.
     *
     * Styles are only read for cells the workbook actually stores. Asking for
     * a cell that is not there would create it, and on a sheet whose print
     * area is far smaller than its used range that is thousands of objects for
     * borders that do not exist.
     *
     * @return array{style: string, rgb: string}|null
     */
    private function border(Worksheet $sheet, int $columnIndex, int $row, string $side): ?array
    {
        if ($columnIndex < 1 || $row < 1) {
            return null;
        }

        $ref = Coordinate::stringFromColumnIndex($columnIndex) . $row;

        if (! $sheet->cellExists($ref)) {
            return null;
        }

        $borders = $sheet->getStyle($ref)->getBorders();

        $border = match ($side) {
            'top' => $borders->getTop(),
            'right' => $borders->getRight(),
            'bottom' => $borders->getBottom(),
            'left' => $borders->getLeft(),
        };

        $line = $border->getBorderStyle();

        if (! $line || $line === Border::BORDER_NONE) {
            return null;
        }

        return ['style' => $line, 'rgb' => (string) ($border->getColor()->getRGB() ?: '000000')];
    }

    /** @param  array{style: string, rgb: string}  $border */
    private function borderCss(array $border): ?string
    {
        [$width, $kind] = match ($border['style']) {
            Border::BORDER_HAIR => [0.4, 'solid'],
            Border::BORDER_THIN => [0.6, 'solid'],
            Border::BORDER_MEDIUM => [1.4, 'solid'],
            Border::BORDER_THICK => [2.2, 'solid'],
            Border::BORDER_DOUBLE => [2.0, 'double'],
            Border::BORDER_DOTTED => [0.6, 'dotted'],
            Border::BORDER_DASHED,
            Border::BORDER_DASHDOT,
            Border::BORDER_DASHDOTDOT => [0.6, 'dashed'],
            Border::BORDER_MEDIUMDASHED,
            Border::BORDER_MEDIUMDASHDOT,
            Border::BORDER_MEDIUMDASHDOTDOT => [1.4, 'dashed'],
            Border::BORDER_SLANTDASHDOT => [1.0, 'dashed'],
            default => [0.6, 'solid'],
        };

        return sprintf('%.2fpt %s #%s', $width, $kind, $border['rgb'] ?: '000000');
    }

    /**
     * A rich-text cell, run by run.
     *
     * Not cosmetic. Every leave type on CS Form No. 6 is one cell holding a
     * 10pt name followed by its 6pt citation — "Vacation Leave" then
     * "(Sec. 51, Rule XVI, Omnibus Rules Implementing E.O. No. 292)". Drawn at
     * one size the citation is half again too long and runs into the next
     * column, which is how a form that fits becomes a form that does not.
     */
    private function richText(RichText $rich, float $baseSize): string
    {
        $html = '';

        foreach ($rich->getRichTextElements() as $element) {
            $text = nl2br(htmlspecialchars($element->getText(), ENT_QUOTES));

            if ($text === '') {
                continue;
            }

            $font = method_exists($element, 'getFont') ? $element->getFont() : null;

            if (! $font) {
                $html .= $text;

                continue;
            }

            $css = '';

            if ($size = $font->getSize()) {
                // Relative to the cell's own size, so page scaling carries
                // through to the runs without being applied twice.
                $css .= sprintf('font-size:%.3fem;', $size / max(1, $baseSize));
            }

            if ($font->getBold()) {
                $css .= 'font-weight:bold;';
            }

            if ($font->getItalic()) {
                $css .= 'font-style:italic;';
            }

            $underline = $font->getUnderline();

            if ($underline && $underline !== Font::UNDERLINE_NONE) {
                $css .= 'text-decoration:underline;';
            }

            $rgb = $font->getColor()?->getRGB();

            if ($rgb && strtoupper($rgb) !== '000000') {
                $css .= "color:#{$rgb};";
            }

            $html .= $css === '' ? $text : '<span style="' . $css . '">' . $text . '</span>';
        }

        return $html;
    }

    /** The cell as the sheet displays it, number format applied. */
    private function cellText(Worksheet $sheet, string $ref, array $cell): string
    {
        if (! $sheet->cellExists($ref)) {
            return '';
        }

        $value = $sheet->getCell($ref)->getValue();

        if ($value === null || $value === '') {
            return '';
        }

        if ($value instanceof RichText) {
            return $this->richText($value, $cell['size']);
        }

        try {
            $shown = (string) $sheet->getCell($ref)->getFormattedValue();
        } catch (\Throwable) {
            $shown = (string) $value;
        }

        if (trim($shown) === '') {
            return '';
        }

        return nl2br(htmlspecialchars($shown, ENT_QUOTES));
    }

    /**
     * Font, colour and alignment for the text inside the cell.
     *
     * Vertical placement is computed here rather than left to CSS. Dompdf
     * ignores vertical-align on a table-cell — every variant of it lays the
     * text against the top of the box — so a form full of vertically centred
     * fields printed with every value sitting on the rule above it. The offset
     * is worked out from the box height and the line height instead, which is
     * arithmetic Dompdf cannot get wrong.
     */
    private function textCss(array $cell, float $scale, float $boxWidth, float $boxHeight): string
    {
        $css = sprintf(
            'font-family:%s;font-size:%.2fpt;',
            $this->fontStack($cell['font']),
            $cell['size'] * $scale,
        );

        if ($cell['bold']) {
            $css .= 'font-weight:bold;';
        }

        if ($cell['italic']) {
            $css .= 'font-style:italic;';
        }

        if ($cell['underline']) {
            $css .= 'text-decoration:underline;';
        }

        if ($cell['colour'] !== '' && strtoupper($cell['colour']) !== '000000') {
            $css .= 'color:#' . $cell['colour'] . ';';
        }

        $horizontal = $cell['horizontal'];

        if ($horizontal === '' || $horizontal === Alignment::HORIZONTAL_GENERAL) {
            // Excel's own rule: numbers right, everything else left.
            $horizontal = $cell['numeric']
                ? Alignment::HORIZONTAL_RIGHT
                : Alignment::HORIZONTAL_LEFT;
        }

        $css .= 'text-align:' . match ($horizontal) {
            Alignment::HORIZONTAL_RIGHT => 'right',
            Alignment::HORIZONTAL_CENTER,
            Alignment::HORIZONTAL_CENTER_CONTINUOUS => 'center',
            Alignment::HORIZONTAL_JUSTIFY => 'justify',
            default => 'left',
        } . ';';

        $css .= 'vertical-align:' . match ($cell['vertical']) {
            Alignment::VERTICAL_TOP => 'top',
            Alignment::VERTICAL_CENTER => 'middle',
            default => 'bottom',
        } . ';';

        // Excel only wraps when told to. Without this a long label breaks into
        // a tower and pushes the whole form out of shape.
        $css .= $cell['wrap'] ? 'word-wrap:break-word;' : 'white-space:nowrap;';

        $size = $cell['size'] * $scale;
        $lineHeight = $size * self::LINE_HEIGHT;

        $css .= sprintf('line-height:%.2fpt;', $lineHeight);

        $lines = 1;

        if ($cell['wrap'] && $boxWidth > 0) {
            // Only an estimate — the real line count is Dompdf's to decide —
            // but it is the difference between a two-line label centred and
            // the same label pushed half out of its box.
            $perLine = max(4, (int) floor($boxWidth / ($size * 0.5)));
            $lines = max(1, (int) ceil(mb_strlen(strip_tags($cell['plain'] ?? '')) / $perLine));
        }

        $content = $lines * $lineHeight;

        $offset = match ($cell['vertical']) {
            Alignment::VERTICAL_TOP => 0.0,
            Alignment::VERTICAL_CENTER => ($boxHeight - $content) / 2,
            default => $boxHeight - $content,
        };

        if ($offset > 0.01) {
            $css .= sprintf('padding-top:%.2fpt;', $offset);
        }

        // Excel's gutter, plus whatever indent the cell asks for on top.
        $gutter = self::CELL_PADDING * $scale;

        $css .= sprintf(
            'padding-left:%.2fpt;padding-right:%.2fpt;',
            $gutter + ($cell['indent'] * 7 * $scale),
            $gutter,
        );

        return $css;
    }

    /** Dompdf resolves these to its built-in faces. */
    private function fontStack(?string $name): string
    {
        return match (strtolower((string) $name)) {
            'calibri', 'arial', 'helvetica', 'verdana', 'tahoma', 'segoe ui' => 'Helvetica, Arial, sans-serif',
            'times new roman', 'cambria', 'georgia', 'garamond' => '"Times New Roman", Times, serif',
            'courier new', 'consolas' => '"Courier New", Courier, monospace',
            default => 'Helvetica, Arial, sans-serif',
        };
    }
}
