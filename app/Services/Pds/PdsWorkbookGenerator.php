<?php

namespace App\Services\Pds;

use App\Models\PdsTemplate;
use App\Services\Xlsx\TemplateFiller;
use App\Support\Pds\PdsFormSchema;
use Carbon\Carbon;

/**
 * Prints the on-screen PDS answers into the official CS Form 212 workbook.
 *
 * The output is the published template itself with the answers written into
 * its answer cells, so every font, size, colour, border and tick box is the
 * Civil Service Commission's own, not an imitation: a cell keeps the style
 * the template gave it. The workbook then goes through the same PDF
 * conversion as an uploaded one.
 *
 * The cell addresses below are for CS Form 212 (Revised 2026). Before writing
 * anything, the template is checked for the labels those addresses sit
 * beside; a template with a different layout is refused rather than filled
 * into the wrong boxes.
 *
 * Where a table has more entries than its page has rows, the page is repeated
 * as a continuation sheet, as the form's own "(Continue on separate sheet if
 * necessary)" asks.
 */
class PdsWorkbookGenerator
{
    public const NOT_APPLICABLE = 'N/A';

    private const LEFT = 'left';

    private const CENTER = 'center';

    /**
     * One look for every answer on the form, whatever the template's cell
     * said: the form's own Arial Narrow, bold, black. 9pt is the largest
     * size every answer box on the form can hold, down to page 4's 11pt
     * lines; only text too long for its box, even wrapped, is set smaller.
     */
    private const ANSWER_SIZE = 9.0;

    private const SMALLEST_SIZE = 6.0;

    /** Labels that must be where the 2026 layout puts them. */
    private const LAYOUT_CHECKS = [
        ['C1', 'B10', 'SURNAME'],
        ['C1', 'B11', 'FIRST NAME'],
        ['C1', 'B36', "SPOUSE'S SURNAME"],
        ['C1', 'I36', 'NAME of CHILDREN'],
        ['C1', 'B54', 'ELEMENTARY'],
        ['C1', 'B58', 'GRADUATE STUDIES'],
        ['C1', 'A61', 'Revised 2026'],
        ['C2', 'A2', 'CIVIL SERVICE ELIGIBILITY'],
        ['C2', 'A13', 'WORK EXPERIENCE'],
        ['C2', 'A48', 'Page 2 of 4'],
        ['C3', 'A2', 'VOLUNTARY WORK'],
        ['C3', 'A14', 'LEARNING AND DEVELOPMENT'],
        ['C3', 'A40', 'OTHER INFORMATION'],
        ['C3', 'K51', 'Page 3 of 4'],
        ['C4', 'C13', 'administrative offense'],
        ['C4', 'H20', 'Date Filed'],
        ['C4', 'C43', 'indigenous group'],
        ['C4', 'A51', 'NAME'],
        ['C4', 'B61', 'Government Issued ID'],
        ['C4', 'A71', 'Page 4 of 4'],
    ];

    /** Rows of item 26, one per level. */
    private const EDUCATION_ROWS = [
        'elementary' => 54,
        'secondary' => 55,
        'vocational' => 56,
        'college' => 57,
        'graduate' => 58,
    ];

    private const EDUCATION_COLUMNS = [
        'school' => 'D',
        'degree' => 'G',
        'from' => 'J',
        'to' => 'K',
        'units' => 'L',
        'year_graduated' => 'M',
        'honors' => 'N',
    ];

    /** School, course and honours are text; the rest are years and figures. */
    private const EDUCATION_ALIGN = [
        'school' => self::LEFT, 'degree' => self::LEFT, 'honors' => self::LEFT,
        'from' => self::CENTER, 'to' => self::CENTER, 'units' => self::CENTER, 'year_graduated' => self::CENTER,
    ];

    private const CHILDREN_FIRST_ROW = 37;

    /**
     * The tables on pages 2 and 3: where each sits, how many rows the page
     * holds, the rows of its whole section, and which column each answer
     * goes in.
     */
    private const TABLES = [
        'C2' => [
            'eligibility' => [
                'first' => 5, 'rows' => 7, 'section' => [2, 12],
                'columns' => ['name' => 'A', 'rating' => 'F', 'exam_date' => 'G', 'exam_place' => 'I', 'license_number' => 'L', 'license_valid_until' => 'M'],
                'na' => 'A',
            ],
            'work' => [
                'first' => 18, 'rows' => 28, 'section' => [13, 46],
                'columns' => ['from' => 'A', 'to' => 'C', 'position' => 'D', 'department' => 'G', 'salary' => 'J', 'grade' => 'K', 'status' => 'L', 'government' => 'M'],
                'na' => 'D',
            ],
        ],
        'C3' => [
            'voluntary' => [
                'first' => 6, 'rows' => 7, 'section' => [2, 13],
                'columns' => ['organization' => 'A', 'from' => 'E', 'to' => 'F', 'hours' => 'G', 'position' => 'H'],
                'na' => 'A',
            ],
            'learning' => [
                'first' => 18, 'rows' => 21, 'section' => [14, 39],
                'columns' => ['title' => 'A', 'from' => 'E', 'to' => 'F', 'hours' => 'G', 'type' => 'H', 'sponsor' => 'I'],
                'na' => 'A',
            ],
            'skills' => [
                'first' => 42, 'rows' => 7, 'section' => [40, 49],
                'columns' => ['text' => 'A'], 'na' => 'A',
            ],
            'distinctions' => [
                'first' => 42, 'rows' => 7, 'section' => [40, 49],
                'columns' => ['text' => 'C'], 'na' => 'C',
            ],
            'memberships' => [
                'first' => 42, 'rows' => 7, 'section' => [40, 49],
                'columns' => ['text' => 'I'], 'na' => 'I',
            ],
        ],
    ];

    /** Table columns holding running text, set left; the rest (dates, figures, codes) are centred. */
    private const TEXT_COLUMNS = ['name', 'exam_place', 'position', 'department', 'organization', 'title', 'sponsor', 'text'];

    /** Columns that hold dates, printed dd/mm/yyyy. */
    private const DATE_COLUMNS = ['from', 'to', 'exam_date', 'license_valid_until'];

    /**
     * Questions 34–40: the YES and NO controls on page 4, by shape id. The
     * captions are all just "YES"/"NO", so the id is what tells them apart;
     * TemplateFiller still checks the caption.
     */
    private const QUESTION_BOXES = [
        'q34a' => [4097, 4098],
        'q34b' => [4099, 4100],
        'q35a' => [4101, 4102],
        'q35b' => [4103, 4104],
        'q36' => [4105, 4106],
        'q37' => [4107, 4108],
        'q38a' => [4122, 4123],
        'q38b' => [4124, 4125],
        'q39' => [4109, 4110],
        'q40a' => [4111, 4114],
        'q40b' => [4112, 4115],
        'q40c' => [4113, 4116],
    ];

    /**
     * Where each YES is explained. Only the first line under a question's
     * "If YES, give details" is usable; the one beneath it is a 5pt spacer.
     */
    private const QUESTION_DETAILS = [
        'q34_details' => 'I11',
        'q35a_details' => 'I15',
        // The J cells beside these captions are right-aligned and a column
        // wide; the line to write on is K:L.
        'q35b_date_filed' => 'K20',
        'q35b_status' => 'K21',
        'q36_details' => 'I25',
        'q37_details' => 'I29',
        'q38a_details' => 'K32',
        'q38b_details' => 'K35',
        'q39_details' => 'I39',
        'q40a_details' => 'L44',
        'q40b_details' => 'L46',
        'q40c_details' => 'L48',
    ];

    private const REFERENCE_FIRST_ROW = 52;

    /**
     * Fills a copy of $template with $data and returns the workbook's path.
     *
     * @throws PdsTemplateMismatch when the template is not the 2026 layout
     */
    public function generate(PdsTemplate $template, array $data, string $outputPath): string
    {
        if (! $template->exists()) {
            throw new PdsTemplateMismatch('The official PDS template is missing from the server.');
        }

        $filler = new TemplateFiller($template->absolutePath(), $outputPath);

        $this->assertLayout($filler);

        $this->personal($filler, $data['personal'] ?? []);
        $this->family($filler, $data['family'] ?? []);
        $this->education($filler, $data['education'] ?? []);
        $this->tables($filler, 'C2', [
            'eligibility' => $data['eligibility']['items'] ?? [],
            'work' => $this->mostRecentFirst($data['work']['items'] ?? []),
        ]);
        $this->tables($filler, 'C3', [
            'voluntary' => $data['voluntary']['items'] ?? [],
            'learning' => $this->mostRecentFirst($data['learning']['items'] ?? []),
            'skills' => $data['other']['skills'] ?? [],
            'distinctions' => $data['other']['distinctions'] ?? [],
            'memberships' => $data['other']['memberships'] ?? [],
        ]);
        $this->questions($filler, $data['questions'] ?? []);
        $this->references($filler, $data['references'] ?? []);

        return $filler->save();
    }

    private function assertLayout(TemplateFiller $filler): void
    {
        foreach (self::LAYOUT_CHECKS as [$sheet, $cell, $expected]) {
            $actual = $filler->hasSheet($sheet) ? $filler->getCellText($sheet, $cell) : '';

            if (! str_contains(mb_strtolower(preg_replace('/\s+/', ' ', $actual)), mb_strtolower($expected))) {
                throw new PdsTemplateMismatch(
                    'The active PDS template does not match the CS Form 212 (Revised 2026) layout '
                    . 'the on-screen form prints into, so it was not filled in. '
                    . 'Please ask the HR Office to check the published template.'
                );
            }
        }
    }

    // ------------------------------------------------------------------
    // Page 1 — I. Personal information
    // ------------------------------------------------------------------

    private function personal(TemplateFiller $f, array $p): void
    {
        $this->text($f, 'C1', 'D10', $p['surname'] ?? null);
        $this->text($f, 'C1', 'D11', $p['first_name'] ?? null);
        $this->nameExtension($f, 'L11', $p['name_extension'] ?? null);
        $this->text($f, 'C1', 'D12', $p['middle_name'] ?? null);
        $this->text($f, 'C1', 'D13', $this->date($p['date_of_birth'] ?? null));
        $this->text($f, 'C1', 'D15', $p['place_of_birth'] ?? null);

        // 5. Sex at birth, 6. Civil status: tick boxes on the form itself.
        if ($sex = $p['sex'] ?? null) {
            $f->setCheckbox('C1', PdsFormSchema::SEX[$sex] ?? '', true);
        }

        $civil = $p['civil_status'] ?? null;
        $civilCaption = [
            'single' => 'Single', 'married' => 'Married', 'widowed' => 'Widowed',
            'separated' => 'Separated', 'other' => 'Other/s:',
        ][$civil] ?? null;

        if ($civilCaption) {
            $f->setCheckbox('C1', $civilCaption, true);
        }

        if ($civil === 'other') {
            $this->answer($f, 'C1', 'E20', $p['civil_status_other'] ?? null);
        }

        $this->text($f, 'C1', 'D22', $this->number($p['height'] ?? null));
        $this->text($f, 'C1', 'D24', $this->number($p['weight'] ?? null));
        $this->text($f, 'C1', 'D25', $p['blood_type'] ?? null);
        $this->text($f, 'C1', 'D27', $p['umid'] ?? null);
        $this->text($f, 'C1', 'D29', $p['pagibig'] ?? null);
        $this->text($f, 'C1', 'D31', $p['philhealth'] ?? null);
        $this->text($f, 'C1', 'D32', $p['philsys'] ?? null);
        $this->text($f, 'C1', 'D33', $p['tin'] ?? null);
        $this->text($f, 'C1', 'D34', $p['agency_employee_no'] ?? null);

        // 16. Citizenship. The country is chosen from a drop-down on the
        // workbook, which prints as a blank box over its cell — so it is made
        // non-printing and the country written into the cell beneath instead.
        $f->hideControlsWhenPrinting('C1', 'Drop');
        $f->mergeCells('C1', 'J16:N16');

        if (($p['citizenship'] ?? null) === 'dual') {
            $f->setCheckbox('C1', 'Dual Citizenship', true);

            if ($by = $p['dual_by'] ?? null) {
                $f->setCheckbox('C1', $by === 'birth' ? 'by birth' : 'by naturalization', true);
            }

            $this->answer($f, 'C1', 'J16', $p['dual_country'] ?? null, self::CENTER);
        } elseif (($p['citizenship'] ?? null) === 'filipino') {
            $f->setCheckbox('C1', 'Filipino', true);
        }

        // 17. Residential address. Answer rows sit above their captions.
        $this->address($f, $p['residential'] ?? [], [
            'house' => 'I17', 'street' => 'L17',
            'subdivision' => 'I19', 'barangay' => 'L19',
            'city' => 'I22', 'province' => 'L22',
            'zip' => 'I24',
        ]);

        // 18. Permanent address. The template left the city/province answer
        // row unmerged; merge it like the rows above so it centres the same.
        $f->mergeCells('C1', 'I29:K29');
        $f->mergeCells('C1', 'L29:N29');

        $this->address($f, $p['permanent'] ?? [], [
            'house' => 'I25', 'street' => 'L25',
            'subdivision' => 'I27', 'barangay' => 'L27',
            'city' => 'I29', 'province' => 'L29',
            'zip' => 'I31',
        ]);

        $this->text($f, 'C1', 'I32', $p['telephone'] ?? null, self::CENTER);
        $this->text($f, 'C1', 'I33', $p['mobile'] ?? null, self::CENTER);
        $this->text($f, 'C1', 'I34', $p['email'] ?? null, self::CENTER);
    }

    // ------------------------------------------------------------------
    // Page 1 — II. Family background
    // ------------------------------------------------------------------

    private function family(TemplateFiller $f, array $fam): void
    {
        $spouse = $fam['spouse'] ?? [];
        $this->text($f, 'C1', 'D36', $spouse['surname'] ?? null);
        $this->text($f, 'C1', 'D37', $spouse['first_name'] ?? null);
        $this->nameExtension($f, 'G37', $spouse['name_extension'] ?? null);
        $this->text($f, 'C1', 'D38', $spouse['middle_name'] ?? null);
        $this->text($f, 'C1', 'D39', $spouse['occupation'] ?? null);
        $this->text($f, 'C1', 'D40', $spouse['employer'] ?? null);
        $this->text($f, 'C1', 'D41', $spouse['business_address'] ?? null);
        $this->text($f, 'C1', 'D42', $spouse['telephone'] ?? null);

        $father = $fam['father'] ?? [];
        $this->text($f, 'C1', 'D43', $father['surname'] ?? null);
        $this->text($f, 'C1', 'D44', $father['first_name'] ?? null);
        $this->nameExtension($f, 'G44', $father['name_extension'] ?? null);
        $this->text($f, 'C1', 'D45', $father['middle_name'] ?? null);

        $mother = $fam['mother'] ?? [];
        $this->text($f, 'C1', 'D47', $mother['surname'] ?? null);
        $this->text($f, 'C1', 'D48', $mother['first_name'] ?? null);
        $this->text($f, 'C1', 'D49', $mother['middle_name'] ?? null);

        $children = array_values(array_filter(
            $fam['children'] ?? [],
            fn ($child) => filled($child['name'] ?? null),
        ));

        if (! $children) {
            $this->answer($f, 'C1', 'I' . self::CHILDREN_FIRST_ROW, self::NOT_APPLICABLE);
            $this->answer($f, 'C1', 'M' . self::CHILDREN_FIRST_ROW, self::NOT_APPLICABLE, self::CENTER);
        }

        foreach (array_slice($children, 0, PdsFormSchema::MAX_CHILDREN) as $i => $child) {
            $row = self::CHILDREN_FIRST_ROW + $i;
            $this->answer($f, 'C1', "I{$row}", $child['name']);
            $this->answer($f, 'C1', "M{$row}", $this->date($child['date_of_birth'] ?? null) ?? self::NOT_APPLICABLE, self::CENTER);
        }
    }

    // ------------------------------------------------------------------
    // Page 1 — III. Educational background
    // ------------------------------------------------------------------

    private function education(TemplateFiller $f, array $edu): void
    {
        // The template breaks this caption with ~200 spaces, which Excel
        // wraps but other renderers push out of the box; a line break reads
        // the same everywhere.
        $f->setCell('C1', 'B56', "VOCATIONAL /\nTRADE COURSE");

        foreach (self::EDUCATION_ROWS as $level => $row) {
            $entry = array_filter($edu[$level] ?? [], 'filled');

            // A level never attended reads N/A, as the form asks, rather
            // than being left blank.
            if (! $entry) {
                $this->answer($f, 'C1', "D{$row}", self::NOT_APPLICABLE);

                continue;
            }

            foreach (self::EDUCATION_COLUMNS as $key => $column) {
                $this->text($f, 'C1', "{$column}{$row}", $entry[$key] ?? null, self::EDUCATION_ALIGN[$key]);
            }
        }
    }

    // ------------------------------------------------------------------
    // Pages 2 and 3 — the tables
    // ------------------------------------------------------------------

    /**
     * Fills a page's tables, adding continuation pages for whatever does not
     * fit. On a continuation page only the tables being continued are shown.
     *
     * @param  array<string, array<int, array>>  $entries  table => rows
     */
    private function tables(TemplateFiller $f, string $page, array $entries): void
    {
        $tables = self::TABLES[$page];

        $pages = 1;
        foreach ($tables as $table => $layout) {
            $pages = max($pages, (int) ceil(count($entries[$table] ?? []) / $layout['rows']));
        }

        for ($copy = 0; $copy < $pages; $copy++) {
            $sheet = $page;

            if ($copy > 0) {
                $sheet = "{$page} (" . ($copy + 1) . ')';
                $f->cloneSheet($page, $sheet);
            }

            if ($page === 'C3') {
                $this->evenOutLearningRows($f, $sheet);
            }

            $continued = [];

            foreach ($tables as $table => $layout) {
                $rows = array_slice(array_values($entries[$table] ?? []), $copy * $layout['rows'], $layout['rows']);

                if ($copy === 0 && ! $rows) {
                    // Nothing to declare still gets written: N/A.
                    $this->answer($f, $sheet, $layout['na'] . $layout['first'], self::NOT_APPLICABLE, $this->tableAlign($layout, $layout['na']));

                    continue;
                }

                if ($rows) {
                    $continued[$layout['section'][0]] = true;
                }

                foreach ($rows as $i => $entry) {
                    $row = $layout['first'] + $i;

                    foreach ($layout['columns'] as $key => $column) {
                        $this->answer($f, $sheet, "{$column}{$row}", $this->cellValue($table, $key, $entry), in_array($key, self::TEXT_COLUMNS, true) ? self::LEFT : self::CENTER);
                    }
                }
            }

            if ($copy > 0) {
                foreach ($tables as $layout) {
                    if (! isset($continued[$layout['section'][0]])) {
                        $f->hideRows($sheet, ...$layout['section']);
                    }
                }
            }
        }
    }

    /**
     * The L&D table is not built evenly in the template: rows 19 to 25 and
     * 29 to 34 are not merged across the title and sponsor columns, so long
     * entries wrapped into a sliver. Give every row the first row's shape.
     */
    private function evenOutLearningRows(TemplateFiller $f, string $sheet): void
    {
        foreach ([...range(19, 25), ...range(29, 34)] as $row) {
            $f->mergeCells($sheet, "A{$row}:D{$row}");
            $f->mergeCells($sheet, "I{$row}:K{$row}");
        }

    }

    private function cellValue(string $table, string $key, array $entry): ?string
    {
        $value = $entry[$key] ?? null;

        if ($table === 'work' && $key === 'to' && ! filled($value)) {
            return 'PRESENT';
        }

        if (! filled($value)) {
            return null;
        }

        return match (true) {
            in_array($key, self::DATE_COLUMNS, true) => $this->date((string) $value),
            $key === 'salary' => number_format((float) $value, 2),
            $key === 'hours' => $this->number($value),
            default => trim((string) $value),
        };
    }

    /** Work and training are listed from the most recent, as the form asks. */
    private function mostRecentFirst(array $rows): array
    {
        usort($rows, fn ($a, $b) => strcmp((string) ($b['from'] ?? ''), (string) ($a['from'] ?? '')));

        return $rows;
    }

    // ------------------------------------------------------------------
    // Page 4 — questions 34 to 40
    // ------------------------------------------------------------------

    private function questions(TemplateFiller $f, array $answers): void
    {
        // 35b's date and status lines span K:L. Merged before anything is
        // written, so each answer is sized to the whole line.
        $f->mergeCells('C4', 'K20:L20');
        $f->mergeCells('C4', 'K21:L21');

        // These captions carry typed underscores as a writing line. Printed
        // beside the real ruled line below them, where the answer goes, they
        // read as a second, empty answer (and 34's wraps into a stray third).
        // The caption is printed without them; the ruled line stays.
        foreach (['G10', 'G14', 'G19', 'G24', 'G28'] as $caption) {
            $text = $f->getCellText('C4', $caption);
            $f->setCell('C4', $caption, rtrim(preg_replace('/_+/', '', $text)));
        }

        foreach (self::QUESTION_BOXES as $question => [$yes, $no]) {
            $answer = $answers[$question] ?? null;

            if ($answer === 'yes') {
                $f->setCheckbox('C4', 'YES', true, $yes);
            } elseif ($answer === 'no') {
                $f->setCheckbox('C4', 'NO', true, $no);
            }
        }

        foreach (PdsFormSchema::QUESTIONS as $question => $definition) {
            $answeredYes = $question === 'q34b'
                ? (($answers['q34a'] ?? null) === 'yes' || ($answers['q34b'] ?? null) === 'yes')
                : ($answers[$question] ?? null) === 'yes';

            // Details belong to a YES; a NO prints none, whatever is saved.
            if (! $answeredYes) {
                continue;
            }

            foreach (array_keys($definition['details']) as $field) {
                $value = $answers[$field] ?? null;
                $this->answer($f, 'C4', self::QUESTION_DETAILS[$field], $field === 'q35b_date_filed' ? $this->date($value) : $value);
            }
        }
    }

    // ------------------------------------------------------------------
    // Page 4 — 41. References, 42. Government ID
    // ------------------------------------------------------------------

    private function references(TemplateFiller $f, array $data): void
    {
        $references = array_slice(array_values($data['references'] ?? []), 0, PdsFormSchema::MAX_REFERENCES);

        if (! $references) {
            $this->answer($f, 'C4', 'A' . self::REFERENCE_FIRST_ROW, self::NOT_APPLICABLE);
        }

        foreach ($references as $i => $reference) {
            $row = self::REFERENCE_FIRST_ROW + $i;
            $this->answer($f, 'C4', "A{$row}", $reference['name'] ?? null);
            $this->answer($f, 'C4', "F{$row}", $reference['address'] ?? null);
            $this->answer($f, 'C4', "G{$row}", $reference['contact'] ?? null, self::CENTER);
        }

        $id = $data['government_id'] ?? [];

        $this->answer($f, 'C4', 'D61', $id['type'] ?? null);
        $this->answer($f, 'C4', 'D62', $id['number'] ?? null);
        $this->answer($f, 'C4', 'D64', $id['issued'] ?? null);
    }

    // ------------------------------------------------------------------

    /** Writes the answer, or N/A when it was left blank. */
    private function text(TemplateFiller $f, string $sheet, string $cell, mixed $value, string $align = self::LEFT): void
    {
        $this->answer($f, $sheet, $cell, filled($value) ? $value : self::NOT_APPLICABLE, $align);
    }

    /**
     * Writes one answer in the house style: bold Arial Narrow at 9pt, wrapped
     * within its box, stepped down only if the text would not otherwise fit.
     * Nothing is written for a blank value.
     */
    private function answer(TemplateFiller $f, string $sheet, string $cell, mixed $value, string $align = self::LEFT): void
    {
        if (! filled($value)) {
            return;
        }

        $text = trim((string) $value);
        $f->setCell($sheet, $cell, $text);

        // An indent keeps left-set text off the box's border.
        $indent = $align === self::LEFT ? 1 : 0;
        $box = $f->boxSize($sheet, $cell);

        $f->formatCell($sheet, $cell, [
            'size' => $this->fittingSize($text, $box['width'] - 4 - $indent * 7.5, $box['height']),
            'bold' => true,
            'horizontal' => $align,
            'vertical' => 'center',
            'wrap' => true,
            'indent' => $indent,
        ]);
    }

    /** The largest size, from 9pt down, at which the text fits its box. */
    private function fittingSize(string $text, float $width, float $height): float
    {
        for ($size = self::ANSWER_SIZE; $size > self::SMALLEST_SIZE; $size -= 0.5) {
            if ($this->linesNeeded($text, $width, $size) * $size * 1.15 <= $height) {
                return $size;
            }
        }

        return self::SMALLEST_SIZE;
    }

    /** How many lines the text wraps to, estimated from Arial Narrow Bold's widths. */
    private function linesNeeded(string $text, float $width, float $size): int
    {
        $measure = function (string $word) use ($size): float {
            $em = 0.0;

            foreach (mb_str_split($word) as $char) {
                $em += match (true) {
                    $char === ' ' => 0.23,
                    in_array($char, ['M', 'W', 'm', 'w', '@'], true) => 0.72,
                    ctype_upper($char) => 0.6,
                    ctype_digit($char), ctype_lower($char) => 0.46,
                    default => 0.33,
                };
            }

            return $em * $size;
        };

        $lines = 1;
        $used = 0.0;
        $space = $measure(' ');

        foreach (preg_split('/\s+/', $text) as $word) {
            $wordWidth = $measure($word);

            if ($used > 0 && $used + $space + $wordWidth > $width) {
                $lines++;
                $used = 0.0;
            }

            // A single word wider than the box breaks across lines.
            while ($wordWidth > $width && $width > 0) {
                $lines++;
                $wordWidth -= $width;
            }

            $used += ($used > 0 ? $space : 0) + $wordWidth;
        }

        return $lines;
    }

    private function tableAlign(array $layout, string $column): string
    {
        $key = array_search($column, $layout['columns'], true);

        return in_array($key, self::TEXT_COLUMNS, true) ? self::LEFT : self::CENTER;
    }

    /**
     * A name extension has no answer box of its own: the form leaves room
     * beside its caption, which is where a hand-filled form puts it too.
     */
    private function nameExtension(TemplateFiller $f, string $cell, ?string $value): void
    {
        if (! filled($value)) {
            return;
        }

        // The caption keeps its printed size; the answer is set like any other.
        $caption = trim($f->getCellText('C1', $cell));
        $f->setRichCell('C1', $cell, [
            [$caption . '  ', null],
            [strtoupper(trim($value)), ['size' => self::ANSWER_SIZE, 'bold' => true]],
        ]);
    }

    /**
     * Address parts sit above centred captions, so they are centred too.
     *
     * @param  array<string, string>  $cells  part => cell
     */
    private function address(TemplateFiller $f, array $address, array $cells): void
    {
        foreach ($cells as $part => $cell) {
            $this->text($f, 'C1', $cell, $address[$part] ?? null, self::CENTER);
        }
    }

    /** The form asks for dd/mm/yyyy. */
    private function date(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function number(mixed $value): ?string
    {
        return filled($value) ? rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') : null;
    }
}
