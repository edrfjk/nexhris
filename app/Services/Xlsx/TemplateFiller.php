<?php

namespace App\Services\Xlsx;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use ZipArchive;

/**
 * Writes values into a copy of an official .xlsx template, in place.
 *
 * PhpSpreadsheet cannot be used for this: it does not model legacy form
 * controls, so loading the CS Form 212 and saving it again silently drops
 * every tick box on the form. This edits the package's XML directly instead.
 * A cell keeps the style the template gave it, and anything this class does
 * not touch — controls, pictures, print setup — is carried over byte for byte.
 */
class TemplateFiller
{
    private const MAIN_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const DRAWING_NS = 'http://schemas.openxmlformats.org/drawingml/2006/main';

    private const SHEET_DRAWING_NS = 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing';

    private ZipArchive $zip;

    /** @var array<string, string> sheet name => part path */
    private array $sheetPaths = [];

    /** @var array<string, DOMDocument> part path => parsed sheet, written on save() */
    private array $sheets = [];

    /** @var array<string, string> part path => raw XML, written on save() */
    private array $parts = [];

    private ?DOMDocument $styles = null;

    /** @var array<string, string> style key => index of the style added for it */
    private array $styleClones = [];

    /** @var array<string, int> font key => index of the font added for it */
    private array $fontIds = [];

    /** @var array<string, DOMDocument> drawing part path => parsed drawing, written on save() */
    private array $drawings = [];

    /** @var array<string, true> package parts to drop on save() */
    private array $removedParts = [];

    public function __construct(string $templatePath, private string $outputPath)
    {
        if (! is_file($templatePath)) {
            throw new \RuntimeException("Template not found: {$templatePath}");
        }

        if (! is_dir(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0775, true);
        }

        copy($templatePath, $outputPath);

        $this->zip = new ZipArchive();

        if ($this->zip->open($outputPath) !== true) {
            throw new \RuntimeException("Could not open {$outputPath} as a workbook.");
        }

        $this->indexSheets();
    }

    /** Whether the template has a sheet by this name. */
    public function hasSheet(string $sheet): bool
    {
        return isset($this->sheetPaths[$sheet]);
    }

    /**
     * Puts a value in a cell, keeping the cell's template style.
     *
     * Strings are written inline rather than through the shared-string table,
     * so the template's own strings are never renumbered.
     */
    public function setCell(
        string $sheet,
        string $coordinate,
        string|int|float|null $value,
        ?string $styleFrom = null,
    ): void {
        if ($value === null || $value === '') {
            return;
        }

        $doc = $this->sheet($sheet);
        $cell = $this->cell($doc, strtoupper($coordinate));

        // A few answer cells carry the template's label style (or none at
        // all); borrow the look of a neighbouring answer cell instead.
        if ($styleFrom !== null) {
            $style = $this->cell($doc, strtoupper($styleFrom))->getAttribute('s');
            $style === '' ? $cell->removeAttribute('s') : $cell->setAttribute('s', $style);
        }

        while ($cell->firstChild) {
            $cell->removeChild($cell->firstChild);
        }

        if (is_int($value) || is_float($value)) {
            $cell->removeAttribute('t');
            $cell->appendChild($doc->createElementNS(self::MAIN_NS, 'v', (string) $value));

            return;
        }

        $cell->setAttribute('t', 'inlineStr');
        $is = $doc->createElementNS(self::MAIN_NS, 'is');
        $t = $doc->createElementNS(self::MAIN_NS, 't');
        $t->appendChild($doc->createTextNode($this->xmlSafe($value)));

        // Leading, trailing and line-break whitespace is part of the value.
        if ($value !== trim($value) || str_contains($value, "\n")) {
            $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
        }

        $is->appendChild($t);
        $cell->appendChild($is);
    }

    /**
     * Adds a copy of a template page right after it (after any copies
     * already added), for the form's "continue on separate sheet".
     *
     * The copy is taken from the untouched template, so it arrives blank. It
     * carries the page's cells, styles, merges and print setup; drawings and
     * form controls are left behind, which is why this is only offered for
     * pages that have none.
     */
    public function cloneSheet(string $source, string $newName): void
    {
        $sourcePath = $this->sheetPaths[$source] ?? throw new \RuntimeException("No sheet named {$source}.");

        if (isset($this->sheetPaths[$newName])) {
            throw new \RuntimeException("A sheet named {$newName} already exists.");
        }

        $xml = (string) $this->zip->getFromName($sourcePath);

        if (preg_match('/<(drawing|legacyDrawing|controls)\b/', $xml)) {
            throw new \RuntimeException("Sheet {$source} has drawings or controls and cannot be copied.");
        }

        $xml = str_replace(' tabSelected="1"', '', $xml);
        // A custom view carries a GUID that must be unique in the workbook.
        $xml = preg_replace('#<customSheetViews>.*?</customSheetViews>#s', '', $xml);

        $index = 1;
        while ($this->zip->locateName("xl/worksheets/sheet{$index}.xml") !== false
            || in_array("xl/worksheets/sheet{$index}.xml", $this->sheetPaths, true)) {
            $index++;
        }
        $newPath = "xl/worksheets/sheet{$index}.xml";
        $this->parts[$newPath] = $xml;

        // The page's own relationships (its printer settings) are shared.
        $sourceRels = dirname($sourcePath) . '/_rels/' . basename($sourcePath) . '.rels';
        if (($rels = $this->zip->getFromName($sourceRels)) !== false) {
            $this->parts["xl/worksheets/_rels/sheet{$index}.xml.rels"] = $rels;
        }

        $types = $this->part('[Content_Types].xml');
        $this->parts['[Content_Types].xml'] = str_replace('</Types>',
            '<Override PartName="/' . $newPath . '" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>',
            $types);

        $workbookRels = $this->part('xl/_rels/workbook.xml.rels');
        preg_match_all('/\bId="rId(\d+)"/', $workbookRels, $ids);
        $rid = 'rId' . (max(array_map('intval', $ids[1] ?: [0])) + 1);
        $this->parts['xl/_rels/workbook.xml.rels'] = str_replace('</Relationships>',
            '<Relationship Id="' . $rid . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $index . '.xml"/></Relationships>',
            $workbookRels);

        $workbook = $this->part('xl/workbook.xml');
        preg_match_all('/<sheet\b[^>]*\/>/', $workbook, $sheetTags);
        $names = array_map(fn ($tag) => preg_match('/\bname="([^"]*)"/', $tag, $n) ? html_entity_decode($n[1]) : '', $sheetTags[0]);

        // After the source and any copies of it already added.
        $position = array_search($source, $names, true);
        while (isset($names[$position + 1]) && str_starts_with($names[$position + 1], $source . ' (')) {
            $position++;
        }
        $insertAt = $position + 1;

        preg_match_all('/\bsheetId="(\d+)"/', $workbook, $sheetIds);
        $sheetId = max(array_map('intval', $sheetIds[1])) + 1;
        $tag = '<sheet name="' . htmlspecialchars($newName, ENT_XML1 | ENT_QUOTES) . '" sheetId="' . $sheetId . '" r:id="' . $rid . '"/>';
        $workbook = str_replace($sheetTags[0][$position], $sheetTags[0][$position] . $tag, $workbook);

        // Names scoped to a sheet refer to it by position; everything after
        // the new sheet moves along one.
        $workbook = preg_replace_callback('/\blocalSheetId="(\d+)"/',
            fn ($m) => 'localSheetId="' . ((int) $m[1] >= $insertAt ? (int) $m[1] + 1 : (int) $m[1]) . '"',
            $workbook);

        $sourceIndex = array_search($source, $names, true);
        if (preg_match('/<definedName name="_xlnm\.Print_Area" localSheetId="' . $sourceIndex . '">([^<]*)<\/definedName>/', $workbook, $area)) {
            $range = preg_replace('/^.*!/', '', html_entity_decode($area[1]));
            $quoted = "'" . str_replace("'", "''", $newName) . "'!" . $range;
            $workbook = str_replace('</definedNames>',
                '<definedName name="_xlnm.Print_Area" localSheetId="' . $insertAt . '">' . htmlspecialchars($quoted, ENT_XML1) . '</definedName></definedNames>',
                $workbook);
        }

        $this->parts['xl/workbook.xml'] = $workbook;
        $this->sheetPaths[$newName] = $newPath;
    }

    /** Gives a cell another cell's style, leaving its value alone. */
    public function restyle(string $sheet, string $cell, string $styleFrom): void
    {
        $doc = $this->sheet($sheet);
        $style = $this->cell($doc, strtoupper($styleFrom))->getAttribute('s');
        $target = $this->cell($doc, strtoupper($cell));

        $style === '' ? $target->removeAttribute('s') : $target->setAttribute('s', $style);
    }

    /** Hides rows, e.g. the sections of a continuation page that are not continued. */
    public function hideRows(string $sheet, int $from, int $to): void
    {
        $doc = $this->sheet($sheet);

        for ($row = $from; $row <= $to; $row++) {
            $this->cell($doc, 'A' . $row)->parentNode->setAttribute('hidden', '1');
        }
    }

    /**
     * Gives a cell a font and alignment of our choosing while keeping its
     * borders, fill and number format from the template.
     *
     * The answer cells of an official form are rarely styled alike — CS Form
     * 212's run from 5pt to 10pt, bold and not, italic and not, left, centred
     * and "general" — which is invisible under handwriting and glaring once
     * typed. This lets every answer be set in one house style.
     *
     * @param  array{font?: string, size?: float, bold?: bool, italic?: bool, color?: string,
     *               horizontal?: string, vertical?: string, wrap?: bool, indent?: int}  $format
     */
    public function formatCell(string $sheet, string $coordinate, array $format): void
    {
        $format += [
            'font' => 'Arial Narrow', 'size' => 9.0, 'bold' => false, 'italic' => false, 'color' => '000000',
            'horizontal' => 'left', 'vertical' => 'center', 'wrap' => false, 'indent' => 0,
        ];

        $cell = $this->cell($this->sheet($sheet), strtoupper($coordinate));
        $base = $cell->getAttribute('s') === '' ? 0 : (int) $cell->getAttribute('s');

        $styles = $this->stylesDocument();
        $xpath = new DOMXPath($styles);
        $xpath->registerNamespace('m', self::MAIN_NS);

        $fontId = $this->fontId($styles, $xpath, $format);
        $key = implode('|', [$base, $fontId, $format['horizontal'], $format['vertical'], (int) $format['wrap'], $format['indent']]);

        if (! isset($this->styleClones[$key])) {
            $cellXfs = $xpath->query('//m:cellXfs')->item(0);
            $template = $xpath->query('m:xf', $cellXfs)->item($base);

            $xf = $template instanceof DOMElement
                ? $template->cloneNode(true)
                : $styles->createElementNS(self::MAIN_NS, 'xf');

            $xf->setAttribute('fontId', (string) $fontId);
            $xf->setAttribute('applyFont', '1');
            $xf->setAttribute('applyAlignment', '1');

            foreach (iterator_to_array($xf->childNodes) as $child) {
                if ($child instanceof DOMElement && $child->localName === 'alignment') {
                    $xf->removeChild($child);
                }
            }

            $alignment = $styles->createElementNS(self::MAIN_NS, 'alignment');
            $alignment->setAttribute('horizontal', $format['horizontal']);
            $alignment->setAttribute('vertical', $format['vertical']);

            if ($format['wrap']) {
                $alignment->setAttribute('wrapText', '1');
            }

            if ($format['indent'] > 0) {
                $alignment->setAttribute('indent', (string) $format['indent']);
            }

            // <alignment> is the first child an xf may have.
            $xf->insertBefore($alignment, $xf->firstChild);
            $cellXfs->appendChild($xf);

            $count = $xpath->query('m:xf', $cellXfs)->length;
            $cellXfs->setAttribute('count', (string) $count);
            $this->styleClones[$key] = (string) ($count - 1);
        }

        $cell->setAttribute('s', $this->styleClones[$key]);
    }

    /**
     * The printed size of the box a value is written into, in points: the
     * merged range the cell starts, or the cell alone.
     *
     * @return array{width: float, height: float}
     */
    public function boxSize(string $sheet, string $coordinate): array
    {
        $coordinate = strtoupper($coordinate);
        $doc = $this->sheet($sheet);
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('m', self::MAIN_NS);

        [$fromCol, $fromRow] = Coordinate::coordinateFromString($coordinate);
        [$toCol, $toRow] = [$fromCol, $fromRow];

        foreach ($xpath->query('//m:mergeCells/m:mergeCell') as $merge) {
            [$start, $end] = array_pad(explode(':', $merge->getAttribute('ref')), 2, null);

            if ($start === $coordinate && $end) {
                [$toCol, $toRow] = Coordinate::coordinateFromString($end);
                break;
            }
        }

        $format = $xpath->query('//m:sheetFormatPr')->item(0);
        $defaultHeight = $format instanceof DOMElement && $format->getAttribute('defaultRowHeight') !== ''
            ? (float) $format->getAttribute('defaultRowHeight') : 12.75;
        $defaultWidth = $format instanceof DOMElement && $format->getAttribute('defaultColWidth') !== ''
            ? (float) $format->getAttribute('defaultColWidth') : 8.43;

        $widths = [];
        foreach ($xpath->query('//m:cols/m:col') as $col) {
            for ($i = (int) $col->getAttribute('min'); $i <= (int) $col->getAttribute('max'); $i++) {
                $widths[$i] = $col->getAttribute('hidden') === '1' ? 0.0 : (float) $col->getAttribute('width');
            }
        }

        $width = 0.0;
        for ($i = Coordinate::columnIndexFromString($fromCol); $i <= Coordinate::columnIndexFromString($toCol); $i++) {
            // Excel column width is in characters of the default font;
            // for these forms that is 7px a character plus 5px of padding.
            $characters = $widths[$i] ?? $defaultWidth;
            $width += $characters > 0 ? (floor($characters * 7 + 5)) * 0.75 : 0;
        }

        $height = 0.0;
        for ($r = (int) $fromRow; $r <= (int) $toRow; $r++) {
            $row = $xpath->query("//m:sheetData/m:row[@r='{$r}']")->item(0);

            if ($row instanceof DOMElement && $row->getAttribute('hidden') === '1') {
                continue;
            }

            $height += $row instanceof DOMElement && $row->getAttribute('ht') !== ''
                ? (float) $row->getAttribute('ht') : $defaultHeight;
        }

        return ['width' => $width, 'height' => $height];
    }

    /** The index of a font with these properties, added to the workbook if new. */
    private function fontId(DOMDocument $styles, DOMXPath $xpath, array $format): int
    {
        $key = implode('|', [$format['font'], $format['size'], (int) $format['bold'], (int) $format['italic'], $format['color']]);

        if (isset($this->fontIds[$key])) {
            return $this->fontIds[$key];
        }

        $fonts = $xpath->query('//m:fonts')->item(0);
        $font = $styles->createElementNS(self::MAIN_NS, 'font');

        // CT_Font's children have a fixed order: b, i, …, sz, color, name, family.
        if ($format['bold']) {
            $font->appendChild($styles->createElementNS(self::MAIN_NS, 'b'));
        }
        if ($format['italic']) {
            $font->appendChild($styles->createElementNS(self::MAIN_NS, 'i'));
        }

        $size = $styles->createElementNS(self::MAIN_NS, 'sz');
        $size->setAttribute('val', rtrim(rtrim(number_format((float) $format['size'], 2, '.', ''), '0'), '.'));
        $font->appendChild($size);

        $color = $styles->createElementNS(self::MAIN_NS, 'color');
        $color->setAttribute('rgb', 'FF' . strtoupper($format['color']));
        $font->appendChild($color);

        $name = $styles->createElementNS(self::MAIN_NS, 'name');
        $name->setAttribute('val', $format['font']);
        $font->appendChild($name);

        $family = $styles->createElementNS(self::MAIN_NS, 'family');
        $family->setAttribute('val', '2');
        $font->appendChild($family);

        $fonts->appendChild($font);
        $count = $xpath->query('m:font', $fonts)->length;
        $fonts->setAttribute('count', (string) $count);

        return $this->fontIds[$key] = $count - 1;
    }

    /**
     * Merges a range, so a value centres across it like the answer boxes
     * around it. The template left a handful of answer rows unmerged.
     */
    public function mergeCells(string $sheet, string $range): void
    {
        $range = strtoupper($range);
        $doc = $this->sheet($sheet);
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('m', self::MAIN_NS);

        $merges = $xpath->query('//m:mergeCells')->item(0);

        if (! $merges) {
            $merges = $doc->createElementNS(self::MAIN_NS, 'mergeCells');
            $sheetData = $xpath->query('//m:sheetData')->item(0);
            // mergeCells follows sheetData (and any sheetProtection etc.);
            // placing it straight after sheetData keeps the schema order for
            // the templates this is used with.
            $sheetData->parentNode->insertBefore($merges, $sheetData->nextSibling);
        }

        foreach ($xpath->query('m:mergeCell', $merges) as $existing) {
            if ($existing->getAttribute('ref') === $range) {
                return;
            }
        }

        $merge = $doc->createElementNS(self::MAIN_NS, 'mergeCell');
        $merge->setAttribute('ref', $range);
        $merges->appendChild($merge);
        $merges->setAttribute('count', (string) $xpath->query('m:mergeCell', $merges)->length);
    }

    /**
     * Writes text made of runs in different fonts, e.g. a printed caption
     * followed by the answer. A run whose font is null keeps the cell's own.
     *
     * @param  array<int, array{0: string, 1: ?array{font?: string, size?: float, bold?: bool, color?: string}}>  $runs
     */
    public function setRichCell(string $sheet, string $coordinate, array $runs): void
    {
        $doc = $this->sheet($sheet);
        $cell = $this->cell($doc, strtoupper($coordinate));

        while ($cell->firstChild) {
            $cell->removeChild($cell->firstChild);
        }

        $cell->setAttribute('t', 'inlineStr');
        $is = $doc->createElementNS(self::MAIN_NS, 'is');

        foreach ($runs as [$text, $font]) {
            $run = $doc->createElementNS(self::MAIN_NS, 'r');

            if ($font !== null) {
                $font += ['font' => 'Arial Narrow', 'size' => 9.0, 'bold' => false, 'color' => '000000'];
                $properties = $doc->createElementNS(self::MAIN_NS, 'rPr');

                // CT_RPrElt order: rFont, charset, family, b, i, …, color, sz.
                $name = $doc->createElementNS(self::MAIN_NS, 'rFont');
                $name->setAttribute('val', $font['font']);
                $properties->appendChild($name);

                $family = $doc->createElementNS(self::MAIN_NS, 'family');
                $family->setAttribute('val', '2');
                $properties->appendChild($family);

                if ($font['bold']) {
                    $properties->appendChild($doc->createElementNS(self::MAIN_NS, 'b'));
                }

                $color = $doc->createElementNS(self::MAIN_NS, 'color');
                $color->setAttribute('rgb', 'FF' . strtoupper($font['color']));
                $properties->appendChild($color);

                $size = $doc->createElementNS(self::MAIN_NS, 'sz');
                $size->setAttribute('val', rtrim(rtrim(number_format((float) $font['size'], 2, '.', ''), '0'), '.'));
                $properties->appendChild($size);

                $run->appendChild($properties);
            }

            $t = $doc->createElementNS(self::MAIN_NS, 't');
            $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
            $t->appendChild($doc->createTextNode($this->xmlSafe($text)));
            $run->appendChild($t);
            $is->appendChild($run);
        }

        $cell->appendChild($is);
    }

    /** The text a cell currently shows, resolving shared strings. */
    public function getCellText(string $sheet, string $coordinate): string
    {
        $doc = $this->sheet($sheet);
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('m', self::MAIN_NS);
        $cell = $xpath->query("//m:sheetData/m:row/m:c[@r='" . strtoupper($coordinate) . "']")->item(0);

        if (! $cell instanceof DOMElement) {
            return '';
        }

        if ($cell->getAttribute('t') === 's') {
            $index = (int) $xpath->query('m:v', $cell)->item(0)?->textContent;

            return $this->sharedStrings()[$index] ?? '';
        }

        return $xpath->query('.//m:t|m:v', $cell)->item(0)?->textContent ?? '';
    }

    /**
     * Ticks or clears a legacy form-control check box.
     *
     * Found by its caption, or — where a page repeats captions, as the YES/NO
     * pairs on page 4 do — by its shape id, with the caption still checked so
     * a template whose controls were renumbered fails loudly instead of
     * ticking the wrong answer.
     *
     * Both places Excel records the state are updated — the VML shape, which
     * LibreOffice and our renderer read, and the control's properties part,
     * which newer Excel reads — so every viewer agrees.
     */
    public function setCheckbox(string $sheet, string $caption, bool $checked, ?int $shapeId = null): void
    {
        $sheetPath = $this->sheetPaths[$sheet] ?? throw new \RuntimeException("No sheet named {$sheet}.");
        $vmlPath = $this->relatedPart($sheetPath, 'vmlDrawing')
            ?? throw new \RuntimeException("Sheet {$sheet} has no form controls.");

        $wanted = $this->normaliseCaption($caption);
        $matches = [];

        $vml = preg_replace_callback('/<v:shape\b.*?<\/v:shape>/s', function ($m) use ($wanted, $checked, $shapeId, &$matches) {
            $shape = $m[0];

            if (! str_contains($shape, 'ObjectType="Checkbox"')) {
                return $shape;
            }

            // Excel names the shape number either id or, beside a named id, o:spid.
            preg_match('/\b(?:o:spid|id)="_x0000_s(\d+)"/', $shape, $id);
            $id = (int) ($id[1] ?? 0);

            if ($shapeId !== null && $id !== $shapeId) {
                return $shape;
            }

            preg_match('/<v:textbox\b.*?<\/v:textbox>/s', $shape, $box);
            $text = $this->normaliseCaption(html_entity_decode(strip_tags($box[0] ?? '')));

            if ($text !== $wanted) {
                return $shape;
            }

            $matches[] = $id;
            $shape = preg_replace('/\s*<x:Checked>\d<\/x:Checked>/', '', $shape);

            return $checked
                ? preg_replace('/<\/x:ClientData>/', '<x:Checked>1</x:Checked></x:ClientData>', $shape, 1)
                : $shape;
        }, $this->part($vmlPath));

        $where = $shapeId !== null ? " (control {$shapeId})" : '';

        if (count($matches) !== 1) {
            throw new \RuntimeException(count($matches) === 0
                ? "No check box captioned \"{$caption}\"{$where} on sheet {$sheet}."
                : "More than one check box is captioned \"{$caption}\" on sheet {$sheet}; name the control.");
        }

        $this->parts[$vmlPath] = $vml;
        $this->setControlProperty($sheetPath, (string) $matches[0], $checked);
    }

    /**
     * Stops every control of one kind (e.g. "Drop") from printing.
     *
     * A drop-down prints as an opaque empty box over the cell beneath it, so
     * a value the system writes into that cell would be hidden. The control
     * stays in the file, so the workbook still works in Excel.
     */
    public function hideControlsWhenPrinting(string $sheet, string $objectType): void
    {
        $sheetPath = $this->sheetPaths[$sheet] ?? throw new \RuntimeException("No sheet named {$sheet}.");
        $vmlPath = $this->relatedPart($sheetPath, 'vmlDrawing');

        if (! $vmlPath) {
            return;
        }

        $this->parts[$vmlPath] = preg_replace_callback(
            '/<x:ClientData ObjectType="' . preg_quote($objectType, '/') . '">(.*?)<\/x:ClientData>/s',
            fn ($m) => str_contains($m[1], '<x:PrintObject>')
                ? $m[0]
                : str_replace('</x:ClientData>', '<x:PrintObject>False</x:PrintObject></x:ClientData>', $m[0]),
            $this->part($vmlPath),
        );

        // The modern description of the same control has its own flag.
        $doc = $this->sheet($sheet);
        $xml = $doc->saveXML();
        $rels = $this->relationships($sheetPath);

        preg_match_all('/<control\b[^>]*\br:id="([^"]+)"/', $xml, $controls);

        foreach ($controls[1] as $rid) {
            $propsPath = $rels[$rid]['path'] ?? null;

            if ($propsPath && str_contains($this->part($propsPath), 'objectType="' . $objectType . '"')) {
                foreach ($doc->getElementsByTagName('control') as $control) {
                    if ($control->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id') === $rid) {
                        foreach ($control->getElementsByTagName('controlPr') as $pr) {
                            $pr->setAttribute('print', '0');
                        }
                    }
                }
            }
        }
    }

    /** The workbook's sheet names, in tab order. */
    public function sheetNames(): array
    {
        return array_keys($this->sheetPaths);
    }

    /**
     * Sets some of a cell's borders, keeping the others, its font, fill and
     * alignment as the template had them.
     *
     * @param  array<string, ?string>  $sides  left|right|top|bottom => 'thin', 'double', … or null for none
     */
    public function setBorders(string $sheet, string $coordinate, array $sides): void
    {
        $cell = $this->cell($this->sheet($sheet), strtoupper($coordinate));
        $base = $cell->getAttribute('s') === '' ? 0 : (int) $cell->getAttribute('s');

        $styles = $this->stylesDocument();
        $xpath = new DOMXPath($styles);
        $xpath->registerNamespace('m', self::MAIN_NS);

        $cellXfs = $xpath->query('//m:cellXfs')->item(0);
        $xf = $xpath->query('m:xf', $cellXfs)->item($base);
        $borders = $xpath->query('//m:borders')->item(0);
        $current = $xf instanceof DOMElement
            ? $xpath->query('m:border', $borders)->item((int) $xf->getAttribute('borderId'))
            : null;

        // CT_Border's children have a fixed order.
        $order = ['left', 'right', 'top', 'bottom', 'diagonal'];
        $border = $styles->createElementNS(self::MAIN_NS, 'border');

        foreach ($order as $side) {
            $existing = $current instanceof DOMElement ? $xpath->query("m:{$side}", $current)->item(0) : null;

            if ($side === 'diagonal' || ! array_key_exists($side, $sides)) {
                $border->appendChild($existing instanceof DOMElement
                    ? $existing->cloneNode(true)
                    : $styles->createElementNS(self::MAIN_NS, $side));

                continue;
            }

            $element = $styles->createElementNS(self::MAIN_NS, $side);

            if ($sides[$side] !== null) {
                $element->setAttribute('style', $sides[$side]);
                $color = $existing instanceof DOMElement ? $xpath->query('m:color', $existing)->item(0) : null;
                $element->appendChild($color instanceof DOMElement
                    ? $color->cloneNode(true)
                    : (function () use ($styles) {
                        $c = $styles->createElementNS(self::MAIN_NS, 'color');
                        $c->setAttribute('indexed', '64');

                        return $c;
                    })());
            }

            $border->appendChild($element);
        }

        $key = 'border|' . $base . '|' . $styles->saveXML($border);

        if (! isset($this->styleClones[$key])) {
            $borders->appendChild($border);
            $borderCount = $xpath->query('m:border', $borders)->length;
            $borders->setAttribute('count', (string) $borderCount);

            $clone = $xf instanceof DOMElement ? $xf->cloneNode(true) : $styles->createElementNS(self::MAIN_NS, 'xf');
            $clone->setAttribute('borderId', (string) ($borderCount - 1));
            $clone->setAttribute('applyBorder', '1');
            $cellXfs->appendChild($clone);

            $count = $xpath->query('m:xf', $cellXfs)->length;
            $cellXfs->setAttribute('count', (string) $count);
            $this->styleClones[$key] = (string) ($count - 1);
        }

        $cell->setAttribute('s', $this->styleClones[$key]);
    }

    /** The style of one side of a cell's border, or null for none. */
    public function borderStyle(string $sheet, string $coordinate, string $side): ?string
    {
        $cell = $this->cell($this->sheet($sheet), strtoupper($coordinate));
        $base = $cell->getAttribute('s') === '' ? 0 : (int) $cell->getAttribute('s');

        $xpath = new DOMXPath($this->stylesDocument());
        $xpath->registerNamespace('m', self::MAIN_NS);

        $xf = $xpath->query('//m:cellXfs/m:xf')->item($base);

        if (! $xf instanceof DOMElement) {
            return null;
        }

        $border = $xpath->query('//m:borders/m:border')->item((int) $xf->getAttribute('borderId'));
        $element = $border instanceof DOMElement ? $xpath->query("m:{$side}", $border)->item(0) : null;

        return $element instanceof DOMElement && $element->getAttribute('style') !== ''
            ? $element->getAttribute('style')
            : null;
    }

    /** A sheet's XML with every edit made so far. */
    public function sheetXml(string $sheet): string
    {
        return $this->sheet($sheet)->saveXML();
    }

    // ------------------------------------------------------------------
    // Drawing text boxes
    // ------------------------------------------------------------------

    /** A shape's fill: an RGB colour or scheme name, or null when see-through. */
    public function shapeFill(string $sheet, string $name): ?string
    {
        $shape = $this->shape($sheet, $name);

        if (! $shape) {
            return null;
        }

        $xpath = $this->drawingXPath($shape->ownerDocument);
        $fill = $xpath->query('xdr:spPr/a:solidFill/*', $shape)->item(0);

        if ($fill instanceof DOMElement) {
            return $fill->getAttribute('val') ?: $fill->localName;
        }

        // No fill of its own: the shape's style decides, which for these
        // boxes is a fill reference of 0 — none.
        return null;
    }

    /** Whether the sheet's drawing has a shape by this name (e.g. "TextBox 6"). */
    public function hasShape(string $sheet, string $name): bool
    {
        return $this->shape($sheet, $name) !== null;
    }

    /**
     * Writes text into a drawing text box, replacing whatever it held.
     *
     * Several campus forms put their answer spaces in invisible text boxes
     * rather than cells; this fills one the way a person typing into it in
     * Excel would, keeping the box's own alignment.
     *
     * @param  array{font?: string, size?: float, bold?: bool, underline?: bool, align?: string, anchor?: string}  $format
     */
    public function setShapeText(string $sheet, string $name, ?string $text, array $format = []): void
    {
        $shape = $this->shape($sheet, $name) ?? throw new \RuntimeException("No shape named {$name} on sheet {$sheet}.");
        $doc = $shape->ownerDocument;
        $xpath = $this->drawingXPath($doc);

        $body = $xpath->query('xdr:txBody', $shape)->item(0);

        if (! $body instanceof DOMElement) {
            throw new \RuntimeException("Shape {$name} on sheet {$sheet} holds no text.");
        }

        $paragraphs = $xpath->query('a:p', $body);
        $first = $paragraphs->item(0);
        $properties = $first instanceof DOMElement ? $xpath->query('a:pPr', $first)->item(0) : null;

        foreach (iterator_to_array($paragraphs) as $p) {
            $body->removeChild($p);
        }

        // Where the text sits in its box: 't', 'ctr' or 'b'. On a ruled line,
        // 'b' with no bottom inset puts it on the line, as handwriting would.
        $bodyPr = $xpath->query('a:bodyPr', $body)->item(0);

        if ($bodyPr instanceof DOMElement && isset($format['anchor'])) {
            $bodyPr->setAttribute('anchor', $format['anchor']);

            if ($format['anchor'] === 'b') {
                $bodyPr->setAttribute('bIns', '0');
            }
        }

        $format += ['font' => 'Arial', 'size' => 10.0, 'bold' => true, 'underline' => false];
        $size = (string) (int) round((float) $format['size'] * 100);

        foreach (explode("\n", (string) $text) as $line) {
            $p = $doc->createElementNS(self::DRAWING_NS, 'a:p');

            $pPr = $properties instanceof DOMElement
                ? $properties->cloneNode(true)
                : $doc->createElementNS(self::DRAWING_NS, 'a:pPr');

            if (isset($format['align'])) {
                $pPr->setAttribute('algn', $format['align']);
            }

            $p->appendChild($pPr);

            $runProperties = function (string $tag) use ($doc, $format, $size): DOMElement {
                $rPr = $doc->createElementNS(self::DRAWING_NS, $tag);
                $rPr->setAttribute('lang', 'en-US');
                $rPr->setAttribute('sz', $size);
                $rPr->setAttribute('b', $format['bold'] ? '1' : '0');

                if ($format['underline']) {
                    $rPr->setAttribute('u', 'sng');
                }

                $fill = $doc->createElementNS(self::DRAWING_NS, 'a:solidFill');
                $color = $doc->createElementNS(self::DRAWING_NS, 'a:srgbClr');
                $color->setAttribute('val', '000000');
                $fill->appendChild($color);
                $rPr->appendChild($fill);

                $latin = $doc->createElementNS(self::DRAWING_NS, 'a:latin');
                $latin->setAttribute('typeface', $format['font']);
                $rPr->appendChild($latin);

                $cs = $doc->createElementNS(self::DRAWING_NS, 'a:cs');
                $cs->setAttribute('typeface', $format['font']);
                $rPr->appendChild($cs);

                return $rPr;
            };

            if ($line !== '') {
                $run = $doc->createElementNS(self::DRAWING_NS, 'a:r');
                $run->appendChild($runProperties('a:rPr'));
                $t = $doc->createElementNS(self::DRAWING_NS, 'a:t');
                $t->appendChild($doc->createTextNode($this->xmlSafe($line)));
                $run->appendChild($t);
                $p->appendChild($run);
            }

            $p->appendChild($runProperties('a:endParaRPr'));
            $body->appendChild($p);
        }
    }

    /**
     * Moves a shape's bottom edge to $offset EMU into the row it ends in,
     * keeping its top where it is.
     */
    public function setShapeBottom(string $sheet, string $name, int $offset): void
    {
        $shape = $this->shape($sheet, $name) ?? throw new \RuntimeException("No shape named {$name} on sheet {$sheet}.");
        $xpath = $this->drawingXPath($shape->ownerDocument);
        $anchor = $shape->parentNode;

        $rowOff = $xpath->query('xdr:to/xdr:rowOff', $anchor)->item(0);
        $ext = $xpath->query('xdr:spPr/a:xfrm/a:ext', $shape)->item(0);

        if (! $rowOff instanceof DOMElement) {
            return;
        }

        $delta = $offset - (int) $rowOff->textContent;
        $rowOff->textContent = (string) $offset;

        if ($ext instanceof DOMElement) {
            $ext->setAttribute('cy', (string) max(0, (int) $ext->getAttribute('cy') + $delta));
        }
    }

    /** Makes a shape see-through (null) or fills it with an RGB colour. */
    public function setShapeFill(string $sheet, string $name, ?string $rgb): void
    {
        $shape = $this->shape($sheet, $name) ?? throw new \RuntimeException("No shape named {$name} on sheet {$sheet}.");
        $doc = $shape->ownerDocument;
        $xpath = $this->drawingXPath($doc);
        $spPr = $xpath->query('xdr:spPr', $shape)->item(0);

        if (! $spPr instanceof DOMElement) {
            return;
        }

        foreach (iterator_to_array($spPr->childNodes) as $child) {
            if ($child instanceof DOMElement && in_array($child->localName, ['noFill', 'solidFill', 'gradFill', 'pattFill', 'blipFill'], true)) {
                $spPr->removeChild($child);
            }
        }

        if ($rgb === null) {
            $fill = $doc->createElementNS(self::DRAWING_NS, 'a:noFill');
        } else {
            $fill = $doc->createElementNS(self::DRAWING_NS, 'a:solidFill');
            $color = $doc->createElementNS(self::DRAWING_NS, 'a:srgbClr');
            $color->setAttribute('val', strtoupper($rgb));
            $fill->appendChild($color);
        }

        // A fill comes straight after the geometry.
        $geometry = $xpath->query('a:prstGeom|a:custGeom', $spPr)->item(0);
        $spPr->insertBefore($fill, $geometry instanceof DOMElement ? $geometry->nextSibling : null);
    }

    /**
     * The room inside a text box for its text, in points: its size less the
     * box's own insets (DrawingML's default is 0.1in a side, 0.05in top and
     * bottom).
     *
     * @return array{width: float, height: float}
     */
    public function shapeTextArea(string $sheet, string $name): array
    {
        $shape = $this->shape($sheet, $name) ?? throw new \RuntimeException("No shape named {$name} on sheet {$sheet}.");
        $xpath = $this->drawingXPath($shape->ownerDocument);

        $ext = $xpath->query('xdr:spPr/a:xfrm/a:ext', $shape)->item(0);
        $body = $xpath->query('xdr:txBody/a:bodyPr', $shape)->item(0);

        $inset = fn (string $attribute, int $default) => $body instanceof DOMElement && $body->getAttribute($attribute) !== ''
            ? (int) $body->getAttribute($attribute) : $default;

        $width = $ext instanceof DOMElement ? (int) $ext->getAttribute('cx') : 0;
        $height = $ext instanceof DOMElement ? (int) $ext->getAttribute('cy') : 0;

        return [
            'width' => max(0, $width - $inset('lIns', 91440) - $inset('rIns', 91440)) / 12700,
            'height' => max(0, $height - $inset('tIns', 45720) - $inset('bIns', 45720)) / 12700,
        ];
    }

    // ------------------------------------------------------------------
    // Package parts
    // ------------------------------------------------------------------

    /** The package path of a sheet's part, e.g. xl/worksheets/sheet2.xml. */
    public function sheetPart(string $sheet): string
    {
        return $this->sheetPaths[$sheet] ?? throw new \RuntimeException("No sheet named {$sheet}.");
    }

    /** @return array<string, array{type: string, path: string}> relationship id => target */
    public function partRelationships(string $partPath): array
    {
        $relsPath = dirname($partPath) . '/_rels/' . basename($partPath) . '.rels';
        $xml = $this->part($relsPath);

        if ($xml === '') {
            return [];
        }

        preg_match_all('/<Relationship\b[^>]*>/', $xml, $tags);
        $out = [];

        foreach ($tags[0] as $tag) {
            preg_match('/\bId="([^"]*)"/', $tag, $id);
            preg_match('/\bType="([^"]*)"/', $tag, $type);
            preg_match('/\bTarget="([^"]*)"/', $tag, $target);
            $out[$id[1]] = [
                'type' => $type[1] ?? '',
                'path' => $this->resolve(dirname($partPath), $target[1] ?? ''),
            ];
        }

        return $out;
    }

    /** The raw bytes of a package part, as they stand with edits so far. */
    public function readPart(string $path): string
    {
        return $this->part($path);
    }

    public function writePart(string $path, string $contents): void
    {
        // A sheet written whole replaces any parsed copy of it.
        unset($this->removedParts[$path], $this->sheets[$path]);
        $this->parts[$path] = $contents;
    }

    public function removePart(string $path): void
    {
        unset($this->parts[$path]);
        $this->removedParts[$path] = true;
    }

    /** Writes every change back into the package and closes it. */
    public function save(): string
    {
        // Excel keeps computed values until it recalculates. Ask it to, so
        // nothing that depends on a filled cell opens showing stale output.
        $workbook = $this->part('xl/workbook.xml');

        if ($workbook !== '' && ! str_contains($workbook, 'fullCalcOnLoad')) {
            $this->parts['xl/workbook.xml'] = preg_match('/<calcPr\b/', $workbook)
                ? preg_replace('/<calcPr\b/', '<calcPr fullCalcOnLoad="1"', $workbook, 1)
                : str_replace('</workbook>', '<calcPr fullCalcOnLoad="1"/></workbook>', $workbook);
        }

        foreach ($this->parts as $path => $xml) {
            $this->zip->addFromString($path, $xml);
        }

        // Parsed sheets last: a copied sheet starts life in $parts and is
        // then edited as a document, and the document is the current one.
        foreach ($this->sheets as $path => $doc) {
            $this->zip->addFromString($path, $doc->saveXML());
        }

        foreach ($this->drawings as $path => $doc) {
            $this->zip->addFromString($path, $doc->saveXML());
        }

        foreach (array_keys($this->removedParts) as $path) {
            if ($this->zip->locateName($path) !== false) {
                $this->zip->deleteName($path);
            }
        }

        if ($this->styles) {
            $this->zip->addFromString('xl/styles.xml', $this->styles->saveXML());
        }

        $this->zip->close();

        return $this->outputPath;
    }

    // ------------------------------------------------------------------

    private function indexSheets(): void
    {
        $workbook = $this->zip->getFromName('xl/workbook.xml');
        $rels = $this->relationships('xl/workbook.xml');

        preg_match_all('/<sheet\b[^>]*\bname="([^"]*)"[^>]*\br:id="([^"]*)"/', $workbook, $m, PREG_SET_ORDER);

        foreach ($m as [, $name, $rid]) {
            if (isset($rels[$rid])) {
                $this->sheetPaths[html_entity_decode($name)] = $rels[$rid]['path'];
            }
        }
    }

    /** @return array<string, array{type: string, path: string}> */
    private function relationships(string $partPath): array
    {
        $relsPath = dirname($partPath) . '/_rels/' . basename($partPath) . '.rels';
        $xml = $this->zip->getFromName($relsPath);

        if ($xml === false) {
            return [];
        }

        preg_match_all('/<Relationship\b[^>]*>/', $xml, $tags);
        $out = [];

        foreach ($tags[0] as $tag) {
            preg_match('/\bId="([^"]*)"/', $tag, $id);
            preg_match('/\bType="([^"]*)"/', $tag, $type);
            preg_match('/\bTarget="([^"]*)"/', $tag, $target);
            $out[$id[1]] = [
                'type' => $type[1] ?? '',
                'path' => $this->resolve(dirname($partPath), $target[1] ?? ''),
            ];
        }

        return $out;
    }

    private function relatedPart(string $sheetPath, string $typeSuffix): ?string
    {
        foreach ($this->relationships($sheetPath) as $rel) {
            if (str_ends_with($rel['type'], '/' . $typeSuffix)) {
                return $rel['path'];
            }
        }

        return null;
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

    private function sheet(string $sheet): DOMDocument
    {
        $path = $this->sheetPaths[$sheet] ?? throw new \RuntimeException("No sheet named {$sheet}.");

        if (! isset($this->sheets[$path])) {
            $doc = new DOMDocument();
            $doc->preserveWhiteSpace = true;
            // A copied sheet exists only in $parts until save().
            $doc->loadXML($this->part($path));
            $this->sheets[$path] = $doc;
        }

        return $this->sheets[$path];
    }

    /** The named shape on a sheet's drawing, or null. */
    private function shape(string $sheet, string $name): ?DOMElement
    {
        $drawingPath = $this->relatedPart($this->sheetPart($sheet), 'drawing');

        if (! $drawingPath) {
            return null;
        }

        if (! isset($this->drawings[$drawingPath])) {
            $doc = new DOMDocument();
            $doc->preserveWhiteSpace = true;
            $doc->loadXML($this->part($drawingPath));
            $this->drawings[$drawingPath] = $doc;
        }

        $xpath = $this->drawingXPath($this->drawings[$drawingPath]);
        $quoted = '"' . str_replace('"', '', $name) . '"';

        $found = $xpath->query("//xdr:sp[xdr:nvSpPr/xdr:cNvPr/@name={$quoted}]")->item(0);

        return $found instanceof DOMElement ? $found : null;
    }

    private function drawingXPath(DOMDocument $doc): DOMXPath
    {
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('xdr', self::SHEET_DRAWING_NS);
        $xpath->registerNamespace('a', self::DRAWING_NS);

        return $xpath;
    }

    private function stylesDocument(): DOMDocument
    {
        if (! $this->styles) {
            $this->styles = new DOMDocument();
            $this->styles->preserveWhiteSpace = true;
            $this->styles->loadXML($this->zip->getFromName('xl/styles.xml'));
        }

        return $this->styles;
    }

    private function part(string $path): string
    {
        return $this->parts[$path] ?? (string) $this->zip->getFromName($path);
    }

    /** Finds the cell element, creating its row and itself in order if absent. */
    private function cell(DOMDocument $doc, string $coordinate): DOMElement
    {
        [$column, $rowNumber] = Coordinate::coordinateFromString($coordinate);
        $columnIndex = Coordinate::columnIndexFromString($column);
        $rowNumber = (int) $rowNumber;

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('m', self::MAIN_NS);
        $sheetData = $xpath->query('//m:sheetData')->item(0);

        $row = $xpath->query("m:row[@r='{$rowNumber}']", $sheetData)->item(0);

        if (! $row) {
            $row = $doc->createElementNS(self::MAIN_NS, 'row');
            $row->setAttribute('r', (string) $rowNumber);
            $before = null;

            foreach ($xpath->query('m:row', $sheetData) as $existing) {
                if ((int) $existing->getAttribute('r') > $rowNumber) {
                    $before = $existing;
                    break;
                }
            }

            $sheetData->insertBefore($row, $before);
        }

        $before = null;

        foreach ($xpath->query('m:c', $row) as $existing) {
            $existingRef = $existing->getAttribute('r');

            if ($existingRef === $coordinate) {
                return $existing;
            }

            [$existingColumn] = Coordinate::coordinateFromString($existingRef);

            if (Coordinate::columnIndexFromString($existingColumn) > $columnIndex) {
                $before = $existing;
                break;
            }
        }

        $cell = $doc->createElementNS(self::MAIN_NS, 'c');
        $cell->setAttribute('r', $coordinate);
        $row->insertBefore($cell, $before);

        return $cell;
    }

    /** @return array<int, string> */
    private function sharedStrings(): array
    {
        static $cache = [];
        $key = spl_object_id($this);

        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $xml = $this->zip->getFromName('xl/sharedStrings.xml');
        $strings = [];

        if ($xml !== false) {
            $doc = new DOMDocument();
            $doc->loadXML($xml);
            $xpath = new DOMXPath($doc);
            $xpath->registerNamespace('m', self::MAIN_NS);

            foreach ($xpath->query('//m:si') as $si) {
                $text = '';
                foreach ($xpath->query('.//m:t', $si) as $t) {
                    $text .= $t->textContent;
                }
                $strings[] = $text;
            }
        }

        return $cache[$key] = $strings;
    }

    private function setControlProperty(string $sheetPath, string $shapeId, bool $checked): void
    {
        $sheetXml = $this->sheets[$sheetPath] ?? null;
        $xml = $sheetXml ? $sheetXml->saveXML() : $this->zip->getFromName($sheetPath);

        if (! preg_match('/<control\b[^>]*\bshapeId="' . $shapeId . '"[^>]*\br:id="([^"]+)"/', $xml, $m)) {
            return;
        }

        $rels = $this->relationships($sheetPath);
        $propsPath = $rels[$m[1]]['path'] ?? null;

        if (! $propsPath) {
            return;
        }

        $props = preg_replace('/\s+checked="[^"]*"/', '', $this->part($propsPath));

        if ($checked) {
            $props = preg_replace('/<formControlPr\b/', '<formControlPr checked="Checked"', $props, 1);
        }

        $this->parts[$propsPath] = $props;
    }

    private function normaliseCaption(string $caption): string
    {
        // The form pads captions with non-breaking spaces.
        return mb_strtolower(trim(preg_replace('/[\s\x{A0}]+/u', ' ', $caption)));
    }

    /** Drops characters XML 1.0 cannot carry, which a pasted value can contain. */
    private function xmlSafe(string $value): string
    {
        return preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value) ?? '';
    }
}
