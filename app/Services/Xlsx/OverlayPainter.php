<?php

namespace App\Services\Xlsx;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Everything on a sheet that is not a cell: shapes, and form controls.
 *
 * PhpSpreadsheet reads cells, styles and pictures, but it does not model
 * drawing shapes or legacy form controls at all, so this reads them out of the
 * .xlsx package directly. On CS Form No. 6 that is not a detail — the stamp
 * box, the rule above every signature, the three signatories' names and all
 * 26 tick boxes are shapes and controls. A renderer that skips them produces a
 * leave form nobody can sign.
 *
 * Positions come as a cell reference plus an offset in EMU (English Metric
 * Units, 914400 to the inch), which is converted against the same column and
 * row tables the cells were painted with, so a shape lands where the grid puts
 * it no matter how the sheet is scaled to fit the paper.
 */
class OverlayPainter
{
    /** English Metric Units per point. */
    private const EMU_PER_POINT = 12700;

    /** Hundredths of a point, which is how DrawingML gives font sizes. */
    private const SZ_PER_POINT = 100;

    public function paint(
        \ZipArchive $zip,
        int $sheetIndex,
        array $cols,
        array $rows,
        float $scale,
    ): string {
        $sheetPath = $this->sheetPath($zip, $sheetIndex);

        if (! $sheetPath) {
            return '';
        }

        $rels = $this->relationships($zip, $sheetPath);

        return $this->shapes($zip, $sheetPath, $rels, $cols, $rows, $scale)
            . $this->checkboxes($zip, $sheetPath, $rels, $cols, $rows, $scale);
    }

    /**
     * A sheet whose content is an embedded document rather than cells.
     *
     * Returns null unless there is one, so the caller can tell "nothing to
     * draw" from "drew nothing".
     */
    public function embedded(
        \ZipArchive $zip,
        int $sheetIndex,
        float $width,
        float $height,
        float $scale,
    ): ?string {
        $sheetPath = $this->sheetPath($zip, $sheetIndex);

        if (! $sheetPath) {
            return null;
        }

        return (new EmbeddedDocPainter())->paint(
            $zip,
            $sheetPath,
            $this->relationships($zip, $sheetPath),
            $width,
            $height,
            $scale,
        );
    }

    // ------------------------------------------------------------------
    // Shapes
    // ------------------------------------------------------------------

    private function shapes(
        \ZipArchive $zip,
        string $sheetPath,
        array $rels,
        array $cols,
        array $rows,
        float $scale,
    ): string {
        $drawing = null;

        foreach ($rels as $rel) {
            if (str_ends_with($rel['type'], '/drawing')) {
                $drawing = $this->resolve($sheetPath, $rel['target']);
            }
        }

        if (! $drawing || ($xml = $zip->getFromName($drawing)) === false) {
            return '';
        }

        $out = '';

        // Both anchor kinds place their shape from a cell plus an offset; a
        // one-cell anchor states an extent instead of a second corner.
        preg_match_all(
            '#<xdr:(twoCellAnchor|oneCellAnchor)\b.*?</xdr:\1>#s',
            $xml,
            $anchors,
        );

        foreach ($anchors[0] as $anchor) {
            // Every form control also appears here as a hidden placeholder
            // shape. Drawing it would double every checkbox.
            if (! str_contains($anchor, '<xdr:sp') || str_contains($anchor, 'hidden="1"')) {
                continue;
            }

            $box = $this->anchorBox($anchor, $cols, $rows);

            if (! $box) {
                continue;
            }

            $out .= $this->shape($anchor, $box, $scale);
        }

        return $out;
    }

    /** One shape: its outline, its fill, and any text it carries. */
    private function shape(string $anchor, array $box, float $scale): string
    {
        $css = sprintf(
            'position:absolute;left:%.2fpt;top:%.2fpt;width:%.2fpt;height:%.2fpt;',
            $box['x'] * $scale,
            $box['y'] * $scale,
            max(0.0, $box['w']) * $scale,
            max(0.0, $box['h']) * $scale,
        );

        // Only the shape's own properties describe how it is painted. Reading
        // wider than this matched the theme references in <xdr:style>, whose
        // accent colours resolved to black, and a form that should show its
        // number in the corner printed a black bar over it instead.
        $properties = preg_match('#<xdr:spPr\b[^>]*>(.*?)</xdr:spPr>#s', $anchor, $spPr)
            ? $spPr[1]
            : '';

        if ($properties === '') {
            return '<div style="' . $css . '"></div>';
        }

        // <a:ln> is the outline; <a:noFill> inside it says draw nothing.
        if (preg_match('#<a:ln\b([^>]*)>(.*?)</a:ln>#s', $properties, $line)) {
            if (! str_contains($line[2], '<a:noFill/>')) {
                $width = 0.75;

                if (preg_match('#\bw="(\d+)"#', $line[1], $w)) {
                    $width = max(0.4, (int) $w[1] / self::EMU_PER_POINT);
                }

                $dashed = preg_match('#<a:prstDash val="(sys)?[Dd]ash#', $line[2]) === 1;

                $css .= sprintf('border:%.2fpt %s #%s;',
                    $width * $scale,
                    $dashed ? 'dashed' : 'solid',
                    $this->colour($line[2]) ?? '000000');
            }
        }

        // The outline is described inside the same block, so it has to come
        // out before the fill is read or a bordered shape takes its border's
        // colour as its background.
        $body = preg_replace('#<a:ln\b.*?</a:ln>#s', '', $properties) ?? $properties;

        if (! str_contains($body, '<a:noFill/>')
            && preg_match('#<a:solidFill>(.*?)</a:solidFill>#s', $body, $fill)) {
            $rgb = $this->colour($fill[1]);

            // lt1 is the theme's "light 1" — white. Painting it would hide the
            // gridlines and cell borders underneath.
            if ($rgb && strtoupper($rgb) !== 'FFFFFF') {
                $css .= "background:#{$rgb};";
            }
        }

        $text = $this->shapeText($anchor, $scale, max(0.0, $box['h']) * $scale);

        if ($text === '') {
            return '<div style="' . $css . '"></div>';
        }

        return '<div style="' . $css . '"><div style="'
            . $text['css'] . '">' . $text['html'] . '</div></div>';
    }

    /**
     * @return array{css: string, html: string}|string
     */
    private function shapeText(string $anchor, float $scale, float $boxHeight): array|string
    {
        if (! preg_match('#<xdr:txBody>(.*?)</xdr:txBody>#s', $anchor, $body)) {
            return '';
        }

        // A shape's text is paragraphs of runs. Joining every run in the shape
        // into one string loses the paragraph breaks, and the form number in
        // the corner comes out as "CS Form No. 212Revised 2026".
        preg_match_all('#<a:p>(.*?)</a:p>#s', $body[1], $paragraphs);

        $lines = [];

        foreach ($paragraphs[1] ?: [$body[1]] as $paragraph) {
            preg_match_all('#<a:t>(.*?)</a:t>#s', $paragraph, $runs);

            $lines[] = html_entity_decode(implode('', $runs[1]), ENT_QUOTES | ENT_XML1);
        }

        $plain = trim(implode("\n", $lines));

        if ($plain === '') {
            return '';
        }

        $size = 10.0;

        if (preg_match('#<a:rPr[^>]*\bsz="(\d+)"#', $body[1], $sz)) {
            $size = (int) $sz[1] / self::SZ_PER_POINT;
        }

        $css = sprintf('font-family:Helvetica, Arial, sans-serif;font-size:%.2fpt;',
            $size * $scale);

        if (preg_match('#<a:rPr[^>]*\bb="1"#', $body[1])) {
            $css .= 'font-weight:bold;';
        }

        if (preg_match('#<a:rPr[^>]*\bi="1"#', $body[1])) {
            $css .= 'font-style:italic;';
        }

        if (preg_match('#<a:rPr[^>]*\bu="sng"#', $body[1])) {
            $css .= 'text-decoration:underline;';
        }

        $css .= 'text-align:' . (preg_match('#<a:pPr[^>]*algn="ctr"#', $body[1]) ? 'center'
            : (preg_match('#<a:pPr[^>]*algn="r"#', $body[1]) ? 'right' : 'left')) . ';';

        // Dompdf ignores vertical-align on a table-cell, so the offset is
        // arithmetic here for the same reason it is in SheetPainter.
        $line = $size * $scale * 1.18;

        $offset = match (true) {
            (bool) preg_match('#<a:bodyPr[^>]*anchor="ctr"#', $body[1]) => ($boxHeight - $line) / 2,
            (bool) preg_match('#<a:bodyPr[^>]*anchor="b"#', $body[1]) => $boxHeight - $line,
            default => 0.0,
        };

        $css .= sprintf('line-height:%.2fpt;', $line);

        if ($offset > 0.01) {
            $css .= sprintf('padding-top:%.2fpt;', $offset);
        }

        return [
            'css' => $css,
            'html' => nl2br(htmlspecialchars($plain, ENT_QUOTES), false),
        ];
    }

    // ------------------------------------------------------------------
    // Checkboxes
    // ------------------------------------------------------------------

    /**
     * The sheet's tick boxes, read from the legacy VML part.
     *
     * VML rather than the modern <controls> list because only VML has all
     * three things a drawn checkbox needs. It gives the position in points
     * outright, it carries the printed caption, and it says which shapes are
     * checkboxes at all. The modern list is inconsistent between the two
     * campus forms: on CS Form No. 6 it holds all 26 boxes, and on CS Form 212
     * it holds only the dropdowns, so a renderer that trusts it draws the
     * leave form correctly and leaves every box on the Personal Data Sheet
     * unlabelled.
     *
     * Whether a box is ticked still comes from the control properties, which
     * is the only part that records it.
     */
    private function checkboxes(
        \ZipArchive $zip,
        string $sheetPath,
        array $rels,
        array $cols,
        array $rows,
        float $scale,
    ): string {
        $vml = null;

        foreach ($rels as $rel) {
            if (str_ends_with($rel['type'], '/vmlDrawing')) {
                $vml = $this->resolve($sheetPath, $rel['target']);
            }
        }

        if (! $vml || ($xml = $zip->getFromName($vml)) === false) {
            return '';
        }

        $ticked = $this->ticked($zip, $sheetPath, $rels);

        preg_match_all('#<v:shape\b([^>]*)>(.*?)</v:shape>#s', $xml, $shapes, PREG_SET_ORDER);

        $out = '';


        foreach ($shapes as $shape) {
            if (! str_contains($shape[2], 'ObjectType="Checkbox"')) {
                continue;
            }

            $box = $this->vmlBox($shape[1]);

            if (! $box) {
                continue;
            }

            $caption = '';

            if (preg_match('#<v:textbox[^>]*>\s*<div[^>]*>(.*?)</div>#s', $shape[2], $div)) {
                $caption = trim(html_entity_decode(strip_tags($div[1]), ENT_QUOTES | ENT_XML1));
            }

            preg_match('#id="_x0000_s(\d+)"#', $shape[1], $id);

            $checked = str_contains($shape[2], '<x:Checked>1</x:Checked>')
                || in_array($id[1] ?? '', $ticked, true);

            $out .= $this->checkbox($box, $checked, $caption, $scale);
        }

        return $out;
    }

    /**
     * A VML shape's box, in points from the top-left of the sheet.
     *
     * VML states this outright rather than as a cell plus an offset, which is
     * the one convenience in the whole legacy format.
     *
     * @return array{x: float, y: float, w: float, h: float}|null
     */
    private function vmlBox(string $attributes): ?array
    {
        if (! preg_match('#style="([^"]*)"#s', $attributes, $style)
            && ! preg_match("#style='([^']*)'#s", $attributes, $style)) {
            return null;
        }

        $declarations = [];

        foreach (explode(';', preg_replace('#\s+#', '', $style[1])) as $declaration) {
            [$name, $value] = array_pad(explode(':', $declaration, 2), 2, null);

            if ($name !== null && $value !== null) {
                $declarations[$name] = $value;
            }
        }

        $points = static function (?string $value): ?float {
            if ($value === null || ! preg_match('#^(-?[\d.]+)pt$#', $value, $m)) {
                return null;
            }

            return (float) $m[1];
        };

        $x = $points($declarations['margin-left'] ?? null);
        $y = $points($declarations['margin-top'] ?? null);
        $w = $points($declarations['width'] ?? null);
        $h = $points($declarations['height'] ?? null);

        if ($x === null || $y === null || $w === null || $h === null) {
            return null;
        }

        return ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h];
    }

    /**
     * Shape ids whose control properties say the box is ticked.
     *
     * @return list<string>
     */
    private function ticked(\ZipArchive $zip, string $sheetPath, array $rels): array
    {
        if (($sheet = $zip->getFromName($sheetPath)) === false) {
            return [];
        }

        if (! preg_match('#<controls>(.*?)</controls>#s', $sheet, $block)) {
            return [];
        }

        preg_match_all('#<control([^>]*)>#', $block[1], $controls);

        $ticked = [];

        foreach ($controls[1] as $attributes) {
            if (! preg_match('#shapeId="(\d+)"#', $attributes, $shapeId)
                || ! preg_match('#r:id="([^"]+)"#', $attributes, $id)) {
                continue;
            }

            $rel = $rels[$id[1]] ?? null;

            if (! $rel || ! str_ends_with($rel['type'], '/ctrlProp')) {
                continue;
            }

            $props = $zip->getFromName($this->resolve($sheetPath, $rel['target']));

            if ($props !== false
                && (str_contains($props, 'checked="Checked"') || str_contains($props, 'checked="1"'))) {
                $ticked[] = $shapeId[1];
            }
        }

        return $ticked;
    }

    /**
     * Excel draws a checkbox at a fixed size inside its anchor rather than
     * stretching it, and puts it at the left, vertically centred.
     */
    private function checkbox(array $box, bool $checked, string $caption, float $scale): string
    {
        $side = 7.5;

        $left = $box['x'];
        $top = $box['y'] + max(0.0, ($box['h'] - $side) / 2);

        $css = sprintf(
            'position:absolute;left:%.2fpt;top:%.2fpt;width:%.2fpt;height:%.2fpt;'
            . 'border:0.6pt solid #000;',
            $left * $scale,
            $top * $scale,
            $side * $scale,
            $side * $scale,
        );

        $label = '';

        if ($caption !== '') {
            // The caption sits to the right of the box, filling the rest of
            // the control. On CS Form 212 these are the only place the words
            // "Filipino", "Male" and "Single" appear at all — they are not in
            // any cell, so a form drawn without them has unlabelled boxes.
            $label = sprintf(
                '<div style="position:absolute;left:%.2fpt;top:%.2fpt;width:%.2fpt;height:%.2fpt;'
                . 'font-family:Helvetica, Arial, sans-serif;font-size:%.2fpt;line-height:%.2fpt;'
                . 'white-space:nowrap;">%s</div>',
                ($left + $side + 2.5) * $scale,
                $box['y'] * $scale,
                max(0.0, $box['w'] - $side - 2.5) * $scale,
                $box['h'] * $scale,
                8 * $scale,
                $box['h'] * $scale,
                htmlspecialchars($caption, ENT_QUOTES),
            );
        }

        if (! $checked) {
            return '<div style="' . $css . '"></div>' . $label;
        }

        // A tick, not a cross. The campus asked for the mark its own form
        // shows, and Excel draws a check.
        $mark = sprintf(
            'position:absolute;left:%.2fpt;top:%.2fpt;font-family:DejaVu Sans;'
            . 'font-size:%.2fpt;line-height:%.2fpt;color:#000;',
            $left * $scale,
            $top * $scale,
            $side * 1.05 * $scale,
            $side * $scale,
        );

        return '<div style="' . $css . '"></div>'
            . '<div style="' . $mark . '">&#10003;</div>'
            . $label;
    }

    /**
     * Converts a from/to anchor into a box in points.
     *
     * @return array{x: float, y: float, w: float, h: float}|null
     */
    private function anchorBox(string $xml, array $cols, array $rows): ?array
    {
        if (! preg_match('#<(?:xdr:)?from>(.*?)</(?:xdr:)?from>#s', $xml, $from)) {
            return null;
        }

        $start = $this->point($from[1], $cols, $rows);

        if (! $start) {
            return null;
        }

        if (preg_match('#<(?:xdr:)?to>(.*?)</(?:xdr:)?to>#s', $xml, $to)
            && ($end = $this->point($to[1], $cols, $rows))) {
            return [
                'x' => $start[0],
                'y' => $start[1],
                'w' => $end[0] - $start[0],
                'h' => $end[1] - $start[1],
            ];
        }

        // A one-cell anchor gives an extent instead of a second corner.
        if (preg_match('#<xdr:ext[^>]*cx="(\d+)"[^>]*cy="(\d+)"#', $xml, $ext)) {
            return [
                'x' => $start[0],
                'y' => $start[1],
                'w' => (int) $ext[1] / self::EMU_PER_POINT,
                'h' => (int) $ext[2] / self::EMU_PER_POINT,
            ];
        }

        return null;
    }

    /** @return array{0: float, 1: float}|null */
    private function point(string $xml, array $cols, array $rows): ?array
    {
        if (! preg_match('#<(?:xdr:)?col>(\d+)</#', $xml, $c)
            || ! preg_match('#<(?:xdr:)?row>(\d+)</#', $xml, $r)) {
            return null;
        }

        preg_match('#<(?:xdr:)?colOff>(-?\d+)</#', $xml, $co);
        preg_match('#<(?:xdr:)?rowOff>(-?\d+)</#', $xml, $ro);

        // Both are zero-based in the drawing, one-based in the sheet.
        $column = Coordinate::stringFromColumnIndex((int) $c[1] + 1);
        $row = (int) $r[1] + 1;

        if (! isset($cols['left'][$column]) || ! isset($rows['top'][$row])) {
            return null;
        }

        return [
            $cols['left'][$column] + (int) ($co[1] ?? 0) / self::EMU_PER_POINT,
            $rows['top'][$row] + (int) ($ro[1] ?? 0) / self::EMU_PER_POINT,
        ];
    }

    /**
     * The first colour named in a fragment of DrawingML.
     *
     * Theme colours are resolved to the two that matter here: the campus form
     * only ever asks for the theme's light and dark, which are white and
     * black. Reading theme1.xml to learn that would be work for no gain.
     */
    private function colour(string $xml): ?string
    {
        if (preg_match('#<a:srgbClr val="([0-9A-Fa-f]{6})"#', $xml, $rgb)) {
            return strtoupper($rgb[1]);
        }

        if (preg_match('#<a:schemeClr val="([a-z0-9]+)"#', $xml, $scheme)) {
            // Only the theme's light and dark are resolved here, because they
            // are the only two the campus forms ask for. Guessing at an accent
            // colour is what painted a black bar across the top of the sheet,
            // so anything else is left unpainted.
            $base = match ($scheme[1]) {
                'lt1', 'lt2', 'bg1', 'bg2' => 255,
                'dk1', 'dk2', 'tx1', 'tx2' => 0,
                default => null,
            };

            if ($base === null) {
                return null;
            }

            // A shade darkens towards black, a tint lightens towards white,
            // both in thousandths of a percent. The stamp box on CS Form No. 6
            // is the theme's white shaded by half — grey. Without this it is
            // drawn white on white and the box disappears.
            if (preg_match('#<a:shade val="(\d+)"#', $xml, $shade)) {
                $base = (int) round($base * ((int) $shade[1] / 100000));
            } elseif (preg_match('#<a:tint val="(\d+)"#', $xml, $tint)) {
                $factor = (int) $tint[1] / 100000;
                $base = (int) round($base * $factor + 255 * (1 - $factor));
            }

            return str_repeat(sprintf('%02X', max(0, min(255, $base))), 3);
        }

        return null;
    }

    /** Maps a PhpSpreadsheet sheet index onto its part in the package. */
    private function sheetPath(\ZipArchive $zip, int $index): ?string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');

        if ($workbook === false) {
            return null;
        }

        preg_match_all('#<sheet\b[^>]*r:id="([^"]+)"#', $workbook, $sheets);

        $id = $sheets[1][$index] ?? null;

        if (! $id) {
            return null;
        }

        $rels = $this->relationships($zip, 'xl/workbook.xml');

        return isset($rels[$id])
            ? $this->resolve('xl/workbook.xml', $rels[$id]['target'])
            : null;
    }

    /** @return array<string, array{type: string, target: string}> */
    private function relationships(\ZipArchive $zip, string $part): array
    {
        $path = dirname($part) . '/_rels/' . basename($part) . '.rels';
        $xml = $zip->getFromName($path);

        if ($xml === false) {
            return [];
        }

        preg_match_all('#<Relationship\b([^>]*)/>#', $xml, $matches);

        $rels = [];

        foreach ($matches[1] as $attributes) {
            if (preg_match('#Id="([^"]+)"#', $attributes, $id)
                && preg_match('#Type="([^"]+)"#', $attributes, $type)
                && preg_match('#Target="([^"]+)"#', $attributes, $target)) {
                $rels[$id[1]] = ['type' => $type[1], 'target' => $target[1]];
            }
        }

        return $rels;
    }

    /** Resolves a relationship target, which is relative to its own part. */
    private function resolve(string $part, string $target): string
    {
        if (str_starts_with($target, '/')) {
            return ltrim($target, '/');
        }

        $base = dirname($part);
        $path = $base === '.' ? $target : $base . '/' . $target;

        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                array_pop($segments);
            } elseif ($segment !== '.' && $segment !== '') {
                $segments[] = $segment;
            }
        }

        return implode('/', $segments);
    }
}
