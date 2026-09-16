<?php

namespace App\Services\Xlsx;

/**
 * Renders a Word document embedded inside a worksheet.
 *
 * Page two of CS Form No. 6 is not a spreadsheet at all. The sheet holds no
 * cells whatsoever — the entire "Instructions and Requirements" page is a Word
 * document embedded as an OLE object, which Excel displays through a Windows
 * metafile it renders once and caches. No pure-PHP library can rasterise that
 * metafile, so the only way to print the page without Excel or LibreOffice is
 * to read the Word document itself and lay it out again.
 *
 * That is what this does. It is not a general Word renderer and does not try
 * to be: it handles what this page is made of — paragraphs, runs with bold,
 * italic and underline, and flat numbered and bulleted lists — set in the two
 * columns the document's own section properties ask for.
 */
class EmbeddedDocPainter
{
    /** Word gives font sizes in half-points. */
    private const HALF_POINTS = 2;

    /** Twentieths of a point, Word's unit for indents and spacing. */
    private const TWIPS_PER_POINT = 20;

    /**
     * The embedded document laid out to fill the given box, or null when the
     * sheet has no embedding to draw.
     */
    public function paint(
        \ZipArchive $zip,
        string $sheetPath,
        array $rels,
        float $width,
        float $height,
        float $scale,
    ): ?string {
        $docx = $this->embedding($zip, $sheetPath, $rels);

        if (! $docx) {
            return null;
        }

        $parts = $this->openDocx($docx);

        if (! $parts) {
            return null;
        }

        $formats = $this->listFormats($parts['numbering'] ?? '');
        $blocks = $this->blocks($parts['document'], $formats);

        if ($blocks === []) {
            return null;
        }

        $blocks = $this->numbered($blocks);

        $columns = $this->columnCount($parts['document']);
        $gap = 12.0;

        if ($columns < 2) {
            return $this->column($blocks, 0, $width, $height, $scale);
        }

        $columnWidth = ($width - $gap) / 2;

        [$left, $right] = $this->balance($blocks, $columnWidth);

        // Excel shows this document scaled down inside its object frame, so
        // the text set at its natural size is taller than the page. Rather
        // than guess the frame, shrink until the longer column fits — the page
        // is a fixed sheet of instructions, so it either fits or it is wrong.
        $tallest = max(
            $this->columnHeight($left, $columnWidth),
            $this->columnHeight($right, $columnWidth),
        );

        $fit = $tallest > $height ? $height / $tallest : 1.0;

        return $this->column($left, 0, $columnWidth, $height, $scale * $fit, $fit)
            . $this->column($right, $columnWidth + $gap, $columnWidth, $height, $scale * $fit, $fit);
    }

    // ------------------------------------------------------------------
    // Reading the package
    // ------------------------------------------------------------------

    /** The embedded .docx bytes, found through the sheet's OLE object. */
    private function embedding(\ZipArchive $zip, string $sheetPath, array $rels): ?string
    {
        foreach ($rels as $rel) {
            if (! str_ends_with($rel['type'], '/oleObject')) {
                continue;
            }

            $target = $rel['target'];

            // Only a real Office file is worth opening; older embeddings are
            // compound binaries this cannot read.
            if (! str_ends_with(strtolower($target), '.docx')) {
                continue;
            }

            $path = $this->resolve($sheetPath, $target);
            $bytes = $zip->getFromName($path);

            if ($bytes !== false && $bytes !== '') {
                return $bytes;
            }
        }

        // Some writers do not relate the embedding to the sheet at all, so
        // fall back to the one file in the embeddings folder.
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if (str_starts_with($name, 'xl/embeddings/') && str_ends_with(strtolower($name), '.docx')) {
                $bytes = $zip->getFromIndex($i);

                if ($bytes !== false && $bytes !== '') {
                    return $bytes;
                }
            }
        }

        return null;
    }

    /** @return array{document: string, numbering: string}|null */
    private function openDocx(string $bytes): ?array
    {
        $temp = tempnam(sys_get_temp_dir(), 'nexdocx');

        if ($temp === false) {
            return null;
        }

        file_put_contents($temp, $bytes);

        $inner = new \ZipArchive();

        if ($inner->open($temp) !== true) {
            @unlink($temp);

            return null;
        }

        $document = $inner->getFromName('word/document.xml');
        $numbering = $inner->getFromName('word/numbering.xml');

        $inner->close();
        @unlink($temp);

        if ($document === false || $document === '') {
            return null;
        }

        return ['document' => $document, 'numbering' => $numbering ?: ''];
    }

    // ------------------------------------------------------------------
    // Parsing
    // ------------------------------------------------------------------

    /**
     * The list style each numbering definition asks for, as a CSS marker.
     *
     * @return array<string, string>  numId => list-style-type
     */
    private function listFormats(string $numbering): array
    {
        if ($numbering === '') {
            return [];
        }

        $abstract = [];

        preg_match_all(
            '#<w:abstractNum\b[^>]*w:abstractNumId="(\d+)".*?</w:abstractNum>#s',
            $numbering,
            $definitions,
            PREG_SET_ORDER,
        );

        foreach ($definitions as $definition) {
            if (! preg_match('#<w:numFmt w:val="([a-zA-Z]+)"#', $definition[0], $format)) {
                continue;
            }

            // Word gives each item on this page its own numbering definition
            // and carries the sequence in the start value. Ignoring it prints
            // fifteen items all numbered 1.
            $start = 1;

            if (preg_match('#<w:start w:val="(\d+)"#', $definition[0], $from)) {
                $start = max(1, (int) $from[1]);
            }

            $abstract[$definition[1]] = [
                'marker' => match ($format[1]) {
                    'bullet' => 'disc',
                    'lowerLetter' => 'lower-alpha',
                    'upperLetter' => 'upper-alpha',
                    'lowerRoman' => 'lower-roman',
                    'upperRoman' => 'upper-roman',
                    default => 'decimal',
                },
                'start' => $start,
            ];
        }

        preg_match_all(
            '#<w:num\b[^>]*w:numId="(\d+)"[^>]*>\s*<w:abstractNumId w:val="(\d+)"#s',
            $numbering,
            $links,
            PREG_SET_ORDER,
        );

        $formats = [];

        foreach ($links as $link) {
            $formats[$link[1]] = $abstract[$link[2]] ?? ['marker' => 'decimal', 'start' => 1];
        }

        return $formats;
    }

    /**
     * The document as a flat list of paragraphs.
     *
     * @return list<array{html: string, marker: ?string, list: ?string, size: float, weight: int}>
     */
    private function blocks(string $document, array $formats): array
    {
        if (! preg_match('#<w:body>(.*)</w:body>#s', $document, $body)) {
            return [];
        }

        // Text boxes and their compatibility fallbacks repeat content that is
        // already in the body — the page heading appears three times over if
        // these are left in.
        $text = preg_replace('#<mc:AlternateContent.*?</mc:AlternateContent>#s', '', $body[1]);
        $body[1] = preg_replace('#<w:drawing.*?</w:drawing>#s', '', $text ?? $body[1]) ?? $body[1];

        preg_match_all('#<w:p\b[^>]*>(.*?)</w:p>#s', $body[1], $paragraphs, PREG_SET_ORDER);

        $blocks = [];

        foreach ($paragraphs as $paragraph) {
            $properties = '';

            if (preg_match('#<w:pPr>(.*?)</w:pPr>#s', $paragraph[1], $pPr)) {
                $properties = $pPr[1];
            }

            $html = $this->runs($paragraph[1]);

            if (trim(strip_tags($html)) === '') {
                continue;
            }

            $list = null;

            if (preg_match('#<w:numPr>.*?<w:numId w:val="(\d+)"#s', $properties, $numId)) {
                $list = $formats[$numId[1]] ?? ['marker' => 'decimal', 'start' => 1];
                $list['id'] = $numId[1];
            }

            $size = 9.5;

            if (preg_match('#<w:sz w:val="(\d+)"#', $paragraph[1], $sz)) {
                $size = (int) $sz[1] / self::HALF_POINTS;
            }

            $align = 'left';

            if (preg_match('#<w:jc w:val="([a-z]+)"#', $properties, $jc)) {
                $align = match ($jc[1]) {
                    'center' => 'center',
                    'right' => 'right',
                    'both' => 'justify',
                    default => 'left',
                };
            }

            $indent = 0.0;

            if (preg_match('#<w:ind\b[^>]*w:left="(\d+)"#', $properties, $ind)) {
                $indent = (int) $ind[1] / self::TWIPS_PER_POINT;
            }

            // The page heading is stored twice in this document. Printing it
            // twice is the sort of thing a panel notices.
            $previous = $blocks === [] ? null : $blocks[array_key_last($blocks)]['html'];

            if ($previous !== null && strip_tags($previous) === strip_tags($html)) {
                continue;
            }

            $blocks[] = [
                'html' => $html,
                'list' => $list,
                'size' => $size,
                'align' => $align,
                'indent' => $indent,
            ];
        }

        return $blocks;
    }

    /** A paragraph's runs, keeping the emphasis Word recorded on each. */
    private function runs(string $paragraph): string
    {
        preg_match_all('#<w:r\b[^>]*>(.*?)</w:r>#s', $paragraph, $runs, PREG_SET_ORDER);

        $html = '';

        foreach ($runs as $run) {
            preg_match_all('#<w:t(?:\b[^>]*)?>(.*?)</w:t>#s', $run[1], $texts);

            $text = implode('', $texts[1]);

            if ($text === '') {
                // A tab or a break still separates words either side of it.
                if (str_contains($run[1], '<w:tab/>') || str_contains($run[1], '<w:br/>')) {
                    $html .= ' ';
                }

                continue;
            }

            $text = htmlspecialchars(
                html_entity_decode($text, ENT_QUOTES | ENT_XML1),
                ENT_QUOTES,
            );

            $properties = '';

            if (preg_match('#<w:rPr>(.*?)</w:rPr>#s', $run[1], $rPr)) {
                $properties = $rPr[1];
            }

            // Word writes <w:b/> to switch a run bold and <w:b w:val="0"/> to
            // switch it back, so the presence of the tag is not enough.
            if ($this->on($properties, 'b')) {
                $text = '<b>' . $text . '</b>';
            }

            if ($this->on($properties, 'i')) {
                $text = '<i>' . $text . '</i>';
            }

            if (preg_match('#<w:u w:val="([a-zA-Z]+)"#', $properties, $u) && $u[1] !== 'none') {
                $text = '<u>' . $text . '</u>';
            }

            $html .= $text;
        }

        return $html;
    }

    private function on(string $properties, string $tag): bool
    {
        if (! preg_match('#<w:' . $tag . '\b([^>]*)/?>#', $properties, $match)) {
            return false;
        }

        return ! preg_match('#w:val="(0|false|off)"#', $match[1]);
    }

    private function columnCount(string $document): int
    {
        if (preg_match('#<w:cols\b[^>]*w:num="(\d+)"#', $document, $cols)) {
            return max(1, (int) $cols[1]);
        }

        return 1;
    }

    // ------------------------------------------------------------------
    // Layout
    // ------------------------------------------------------------------

    /**
     * Splits the blocks between two columns so both come out about as long.
     *
     * Text height cannot be measured here — Dompdf does that later, and only
     * once. So each paragraph's height is estimated from how many lines its
     * characters need at the column's width, which is close enough to put the
     * break within a line or two of where Word puts it.
     *
     * @return array{0: list<array>, 1: list<array>}
     */
    private function balance(array $blocks, float $columnWidth): array
    {
        $heights = [];
        $total = 0.0;

        foreach ($blocks as $block) {
            // Average glyph advance for a narrow sans face, near enough.
            $charsPerLine = max(12, (int) floor($columnWidth / ($block['size'] * 0.46)));
            $lines = max(1, (int) ceil(mb_strlen(strip_tags($block['html'])) / $charsPerLine));
            $height = $lines * $block['size'] * 1.22 + 2.4;

            $heights[] = $height;
            $total += $height;
        }

        $half = $total / 2;
        $running = 0.0;
        $split = count($blocks);

        foreach ($heights as $i => $height) {
            if ($running + $height / 2 >= $half) {
                $split = $i;
                break;
            }

            $running += $height;
        }

        return [
            array_slice($blocks, 0, $split),
            array_slice($blocks, $split),
        ];
    }

    /**
     * Gives every list item the number it will print with.
     *
     * Word numbers these 1 to 15 straight down the page even though a
     * paragraph of prose sits under each one, and the page is then split into
     * two columns. Both of those break HTML list numbering — a paragraph
     * closes the list, and a column starts a new one — so the number is worked
     * out here, once, before either happens.
     *
     * @param  list<array>  $blocks
     * @return list<array>
     */
    private function numbered(array $blocks): array
    {
        $counters = [];

        foreach ($blocks as $i => $block) {
            if ($block['list'] === null) {
                continue;
            }

            $id = $block['list']['id'] ?? $block['list']['marker'];
            $counters[$id] = ($counters[$id] ?? ($block['list']['start'] - 1)) + 1;

            $blocks[$i]['list']['number'] = $counters[$id];
        }

        return $blocks;
    }

    /** Estimated set height of a column, in points. */
    private function columnHeight(array $blocks, float $columnWidth): float
    {
        $total = 0.0;

        foreach ($blocks as $block) {
            $charsPerLine = max(12, (int) floor($columnWidth / ($block['size'] * 0.46)));
            $lines = max(1, (int) ceil(mb_strlen(strip_tags($block['html'])) / $charsPerLine));
            $total += $lines * $block['size'] * 1.22 + 2.4;
        }

        return $total;
    }

    /** One column of blocks, positioned absolutely within the page. */
    private function column(
        array $blocks,
        float $left,
        float $width,
        float $height,
        float $scale,
        float $fit = 1.0,
    ): string {
        $html = '';
        $openList = null;

        foreach ($blocks as $block) {
            if ($block['list'] !== null) {
                $id = $block['list']['id'] ?? $block['list']['marker'];

                if ($openList === null || ($openList['id'] ?? $openList['marker']) !== $id) {
                    $html .= $openList !== null ? $this->closeList($openList['marker']) : '';

                    // Each item already knows its number, worked out across
                    // the whole document. Counting per column would restart
                    // the second one at 1, so item 10 would print as item 1.
                    $resumed = $block['list'];
                    $resumed['start'] = $block['list']['number'];

                    $html .= $this->openList($resumed, $scale);
                    $openList = $block['list'];
                }

                $html .= '<li style="' . $this->textCss($block, $scale) . '">' . $block['html'] . '</li>';

                continue;
            }

            if ($openList !== null) {
                $html .= $this->closeList($openList['marker']);
                $openList = null;
            }

            $html .= '<p style="' . $this->textCss($block, $scale)
                . sprintf('margin:0 0 %.2fpt 0;', 2.4 * $scale)
                . ($block['indent'] > 0 ? sprintf('padding-left:%.2fpt;', $block['indent'] * $scale) : '')
                . '">' . $block['html'] . '</p>';
        }

        if ($openList !== null) {
            $html .= $this->closeList($openList['marker']);
        }

        $box = $fit > 0 ? $scale / $fit : $scale;

        return sprintf(
            '<div style="position:absolute;left:%.2fpt;top:0;width:%.2fpt;height:%.2fpt;">%s</div>',
            $left * $box,
            $width * $box,
            $height * $box,
            $html,
        );
    }

    private function openList(array $list, float $scale): string
    {
        if ($list['marker'] === 'disc') {
            return sprintf(
                '<ul style="list-style-type:disc;margin:0 0 %.2fpt 0;padding-left:%.2fpt;">',
                2.4 * $scale,
                11 * $scale,
            );
        }

        return sprintf(
            '<ol start="%d" style="list-style-type:%s;margin:0 0 %.2fpt 0;padding-left:%.2fpt;">',
            $list['start'],
            $list['marker'],
            2.4 * $scale,
            13 * $scale,
        );
    }

    private function closeList(string $marker): string
    {
        return $marker === 'disc' ? '</ul>' : '</ol>';
    }

    private function textCss(array $block, float $scale): string
    {
        return sprintf(
            'font-family:Helvetica, Arial, sans-serif;font-size:%.2fpt;line-height:1.22;text-align:%s;',
            $block['size'] * $scale,
            $block['align'],
        );
    }

    /** Relationship targets are relative to the part that declares them. */
    private function resolve(string $part, string $target): string
    {
        if (str_starts_with($target, '/')) {
            return ltrim($target, '/');
        }

        $segments = [];

        foreach (explode('/', dirname($part) . '/' . $target) as $segment) {
            if ($segment === '..') {
                array_pop($segments);
            } elseif ($segment !== '.' && $segment !== '') {
                $segments[] = $segment;
            }
        }

        return implode('/', $segments);
    }
}
