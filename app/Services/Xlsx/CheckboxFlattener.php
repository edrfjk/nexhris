<?php

namespace App\Services\Xlsx;

/**
 * Redraws a workbook's legacy check boxes as ordinary drawing shapes, for
 * printing through LibreOffice.
 *
 * LibreOffice prints a ticked form-control check box as a crossed box (☒)
 * and wraps or clips its caption ("Widow..."), where Excel — and the form as
 * the campus knows it — shows a tick (✓) and the whole word. The control's
 * own look cannot be changed, so each check box is marked non-printing and
 * replaced on paper by what Excel draws: a flat square, a check mark when
 * ticked, and the caption in the control's own font.
 *
 * This runs on the throwaway copy made for conversion. The stored workbook
 * keeps its real, clickable controls.
 */
class CheckboxFlattener
{
    private const EMU_PER_POINT = 12700;

    private const EMU_PER_PIXEL = 9525;

    /** Excel's flat check box square, in points. */
    private const BOX = 9.0;

    /** Space between the square and its caption, in points. */
    private const GAP = 2.25;


    /** @return int how many check boxes were redrawn */
    public function flatten(\ZipArchive $zip): int
    {
        $count = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                $count += $this->flattenSheet($zip, $name);
            }
        }

        return $count;
    }

    private function flattenSheet(\ZipArchive $zip, string $sheetPath): int
    {
        $rels = $this->relationships($zip, $sheetPath);
        $vmlPath = $drawingPath = null;

        foreach ($rels as $rel) {
            if (str_ends_with($rel['type'], '/vmlDrawing')) {
                $vmlPath = $rel['path'];
            } elseif (str_ends_with($rel['type'], '/drawing')) {
                $drawingPath = $rel['path'];
            }
        }

        // Without a DrawingML part there is nowhere to draw the replacement;
        // leaving the control alone beats removing it from the page.
        if (! $vmlPath || ! $drawingPath) {
            return 0;
        }

        $vml = $zip->getFromName($vmlPath);
        $drawing = $zip->getFromName($drawingPath);

        if ($vml === false || $drawing === false || ! str_contains($vml, 'ObjectType="Checkbox"')) {
            return 0;
        }

        $ticked = $this->tickedInControlProperties($zip, $sheetPath, $rels);
        $shapes = '';
        $nextId = $this->maxShapeId($drawing) + 1;
        $count = 0;

        $vml = preg_replace_callback('#<v:shape\b([^>]*)>(.*?)</v:shape>#s', function ($m) use ($ticked, &$shapes, &$nextId, &$count) {
            [$whole, $attributes, $body] = $m;

            if (! str_contains($body, 'ObjectType="Checkbox"') || str_contains($body, '<x:PrintObject>False')) {
                return $whole;
            }

            $anchor = $this->anchor($body);
            $size = $this->size($attributes);

            if (! $anchor || ! $size) {
                return $whole;
            }

            preg_match('#\b(?:o:spid|id)="_x0000_s(\d+)"#', $attributes, $id);

            $checked = str_contains($body, '<x:Checked>1</x:Checked>')
                || in_array($id[1] ?? '', $ticked, true);

            [$caption, $face, $points] = $this->caption($body);

            $shapes .= $this->drawCheckbox($anchor, $size, $checked, $caption, $face, $points, $nextId);
            $nextId += 4;
            $count++;

            return $whole === '' ? $whole : str_replace(
                '</x:ClientData>',
                '<x:PrintObject>False</x:PrintObject></x:ClientData>',
                $whole,
            );
        }, $vml);

        if ($count === 0) {
            return 0;
        }

        $drawing = preg_replace('#</xdr:wsDr>\s*$#', $shapes . '</xdr:wsDr>', $drawing, 1);

        $zip->addFromString($vmlPath, $vml);
        $zip->addFromString($drawingPath, $drawing);

        return $count;
    }

    /**
     * The control's top-left cell and offset. VML gives "col, dx, row, dy, …"
     * with the offsets in pixels.
     *
     * @return array{col: int, dx: int, row: int, dy: int}|null
     */
    private function anchor(string $body): ?array
    {
        if (! preg_match('#<x:Anchor>\s*([\d\s,]+)</x:Anchor>#', $body, $m)) {
            return null;
        }

        $parts = array_map('intval', preg_split('#\s*,\s*#', trim($m[1])));

        if (count($parts) < 8) {
            return null;
        }

        return ['col' => $parts[0], 'dx' => $parts[1], 'row' => $parts[2], 'dy' => $parts[3]];
    }

    /** @return array{w: float, h: float}|null in points */
    private function size(string $attributes): ?array
    {
        if (! preg_match("#style=(['\"])(.*?)\\1#s", $attributes, $style)) {
            return null;
        }

        $css = preg_replace('#\s+#', '', $style[2]);

        if (! preg_match('#(?:^|;)width:([\d.]+)pt#', $css, $w) || ! preg_match('#(?:^|;)height:([\d.]+)pt#', $css, $h)) {
            return null;
        }

        return ['w' => (float) $w[1], 'h' => (float) $h[1]];
    }

    /** @return array{0: string, 1: string, 2: float} caption, font face, size in points */
    private function caption(string $body): array
    {
        if (! preg_match('#<v:textbox\b[^>]*>(.*?)</v:textbox>#s', $body, $box)) {
            return ['', 'Tahoma', 8.0];
        }

        $face = preg_match('#face="([^"]+)"#', $box[1], $f) ? $f[1] : 'Tahoma';
        // VML font sizes are in twentieths of a point.
        $points = preg_match('#<font\b[^>]*\bsize="(\d+)"#', $box[1], $s) ? ((int) $s[1]) / 20 : 8.0;

        // VML captions are HTML: a newline in the markup is only the file's
        // own line wrapping ("by\r\n   birth" reads "by birth"). Only <br>
        // is a real break.
        $html = preg_replace('#<br\s*/?>#i', "\x1E", $box[1]);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = preg_replace('#[\s\x{A0}]+#u', ' ', $text);
        $lines = array_filter(array_map('trim', explode("\x1E", $text)), fn ($line) => $line !== '');

        return [implode("\n", $lines), $face, $points ?: 8.0];
    }

    /**
     * Square, optional check mark and caption, grouped and anchored where
     * the control was.
     */
    private function drawCheckbox(array $anchor, array $size, bool $checked, string $caption, string $face, float $points, int $id): string
    {
        $emu = fn (float $pt) => (int) round($pt * self::EMU_PER_POINT);

        $w = $emu($size['w']);
        $h = $emu($size['h']);
        $box = $emu(self::BOX);
        $boxTop = max(0, (int) round(($h - $box) / 2));
        $textLeft = $box + $emu(self::GAP);

        $shapes = $this->shape(
            $id + 1, 'Check box square', 0, $boxTop, $box, $box,
            '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:solidFill><a:srgbClr val="FFFFFF"/></a:solidFill>'
            . '<a:ln w="9525"><a:solidFill><a:srgbClr val="000000"/></a:solidFill></a:ln>',
        );

        if ($checked) {
            // Drawn as a path rather than a ✓ character, so it looks the same
            // whatever fonts the server has.
            $path = '<a:custGeom><a:avLst/><a:gdLst/><a:ahLst/><a:cxnLst/><a:rect l="0" t="0" r="r" b="b"/>'
                . '<a:pathLst><a:path w="100" h="100" fill="none">'
                . '<a:moveTo><a:pt x="18" y="52"/></a:moveTo>'
                . '<a:lnTo><a:pt x="40" y="76"/></a:lnTo>'
                . '<a:lnTo><a:pt x="84" y="22"/></a:lnTo>'
                . '</a:path></a:pathLst></a:custGeom><a:noFill/>'
                . '<a:ln w="15875" cap="rnd"><a:solidFill><a:srgbClr val="000000"/></a:solidFill><a:round/></a:ln>';

            $shapes .= $this->shape($id + 2, 'Check mark', 0, $boxTop, $box, $box, $path);
        }

        if ($caption !== '') {
            $paragraphs = '';

            foreach (explode("\n", $caption) as $line) {
                $paragraphs .= '<a:p><a:pPr algn="l"/><a:r><a:rPr lang="en-US" sz="' . (int) round($points * 100) . '">'
                    . '<a:solidFill><a:srgbClr val="000000"/></a:solidFill>'
                    . '<a:latin typeface="' . htmlspecialchars($face, ENT_XML1) . '"/><a:cs typeface="' . htmlspecialchars($face, ENT_XML1) . '"/>'
                    . '</a:rPr><a:t>' . htmlspecialchars($line, ENT_XML1) . '</a:t></a:r></a:p>';
            }

            $shapes .= '<xdr:sp macro="" textlink=""><xdr:nvSpPr><xdr:cNvPr id="' . ($id + 3) . '" name="Check box caption"/><xdr:cNvSpPr txBox="1"/></xdr:nvSpPr>'
                . '<xdr:spPr><a:xfrm><a:off x="' . $textLeft . '" y="0"/><a:ext cx="' . max(1, $w - $textLeft) . '" cy="' . $h . '"/></a:xfrm>'
                . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:noFill/><a:ln><a:noFill/></a:ln></xdr:spPr>'
                // Wrapped within the control, as Excel does.
                . '<xdr:txBody><a:bodyPr wrap="square" lIns="0" tIns="0" rIns="0" bIns="0" anchor="ctr" vertOverflow="overflow" horzOverflow="overflow"><a:noAutofit/></a:bodyPr><a:lstStyle/>'
                . $paragraphs . '</xdr:txBody></xdr:sp>';
        }

        return '<xdr:oneCellAnchor>'
            . '<xdr:from><xdr:col>' . $anchor['col'] . '</xdr:col><xdr:colOff>' . ($anchor['dx'] * self::EMU_PER_PIXEL) . '</xdr:colOff>'
            . '<xdr:row>' . $anchor['row'] . '</xdr:row><xdr:rowOff>' . ($anchor['dy'] * self::EMU_PER_PIXEL) . '</xdr:rowOff></xdr:from>'
            . '<xdr:ext cx="' . $w . '" cy="' . $h . '"/>'
            . '<xdr:grpSp><xdr:nvGrpSpPr><xdr:cNvPr id="' . $id . '" name="Check box"/><xdr:cNvGrpSpPr/></xdr:nvGrpSpPr>'
            . '<xdr:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $w . '" cy="' . $h . '"/>'
            . '<a:chOff x="0" y="0"/><a:chExt cx="' . $w . '" cy="' . $h . '"/></a:xfrm></xdr:grpSpPr>'
            . $shapes
            . '</xdr:grpSp><xdr:clientData fPrintsWithSheet="1"/></xdr:oneCellAnchor>';
    }

    private function shape(int $id, string $name, int $x, int $y, int $cx, int $cy, string $geometryAndStyle): string
    {
        return '<xdr:sp macro="" textlink=""><xdr:nvSpPr><xdr:cNvPr id="' . $id . '" name="' . $name . '"/><xdr:cNvSpPr/></xdr:nvSpPr>'
            . '<xdr:spPr><a:xfrm><a:off x="' . $x . '" y="' . $y . '"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm>'
            . $geometryAndStyle . '</xdr:spPr></xdr:sp>';
    }

    private function maxShapeId(string $drawing): int
    {
        preg_match_all('#<xdr:cNvPr\b[^>]*\bid="(\d+)"#', $drawing, $m);

        return $m[1] ? max(array_map('intval', $m[1])) : 1;
    }

    /** Shape ids whose control-properties part records a tick. */
    private function tickedInControlProperties(\ZipArchive $zip, string $sheetPath, array $rels): array
    {
        $sheet = $zip->getFromName($sheetPath);

        if ($sheet === false) {
            return [];
        }

        preg_match_all('#<control\b([^>]*)>#', $sheet, $controls);
        $ticked = [];

        foreach ($controls[1] as $attributes) {
            if (! preg_match('#shapeId="(\d+)"#', $attributes, $shapeId)
                || ! preg_match('#r:id="([^"]+)"#', $attributes, $rid)) {
                continue;
            }

            $props = isset($rels[$rid[1]]) ? $zip->getFromName($rels[$rid[1]]['path']) : false;

            if ($props !== false && preg_match('#\bchecked="(Checked|1)"#', $props)) {
                $ticked[] = $shapeId[1];
            }
        }

        return $ticked;
    }

    /** @return array<string, array{type: string, path: string}> */
    private function relationships(\ZipArchive $zip, string $partPath): array
    {
        $xml = $zip->getFromName(dirname($partPath) . '/_rels/' . basename($partPath) . '.rels');

        if ($xml === false) {
            return [];
        }

        preg_match_all('#<Relationship\b[^>]*>#', $xml, $tags);
        $out = [];

        foreach ($tags[0] as $tag) {
            if (preg_match('#\bId="([^"]*)"#', $tag, $id)
                && preg_match('#\bType="([^"]*)"#', $tag, $type)
                && preg_match('#\bTarget="([^"]*)"#', $tag, $target)) {
                $out[$id[1]] = ['type' => $type[1], 'path' => $this->resolve(dirname($partPath), $target[1])];
            }
        }

        return $out;
    }

    private function resolve(string $base, string $target): string
    {
        if (str_starts_with($target, '/')) {
            return ltrim($target, '/');
        }

        $parts = [];

        foreach (explode('/', $base . '/' . $target) as $segment) {
            if ($segment === '..') {
                array_pop($parts);
            } elseif ($segment !== '' && $segment !== '.') {
                $parts[] = $segment;
            }
        }

        return implode('/', $parts);
    }
}
