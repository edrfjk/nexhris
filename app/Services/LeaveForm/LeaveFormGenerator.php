<?php

namespace App\Services\LeaveForm;

use App\Models\LeaveApplication;
use App\Models\LeaveFormTemplate;
use App\Services\Xlsx\TemplateFiller;
use App\Support\TextMetrics;

/**
 * Prints a leave filing into the official CS Form No. 6 workbook.
 *
 * The output is HR's published template with the answers written where a
 * person typing in Excel would put them — into the form's own answer boxes —
 * and the tick boxes ticked. Fonts, rules, the college header and the
 * signatories' names are the template's own. The known printing faults of
 * the campus form are corrected on the way (see CsForm6Repairs).
 */
class LeaveFormGenerator
{
    /** Answers are set in the form's own face: Arial, bold. */
    private const FONT = 'Arial';

    private const ANSWER_SIZE = 10.0;

    private const NAME_SIZE = 11.0;

    private const SMALLEST = 6.0;

    public function __construct(private CsForm6Repairs $repairs)
    {
    }

    /**
     * The workbook to print into: the version this filing was made on, the
     * active one, or the corrected copy that ships with the system — the
     * first of those laid out as CS Form No. 6.
     */
    public function templatePath(?LeaveApplication $application = null): string
    {
        $candidates = array_filter([
            $application?->formTemplate,
            LeaveFormTemplate::active(),
        ]);

        foreach ($candidates as $template) {
            if ($template->exists() && strtolower(pathinfo($template->file_path, PATHINFO_EXTENSION)) === 'xlsx'
                && $this->isCsForm6($template->absolutePath())) {
                return $template->absolutePath();
            }
        }

        return resource_path('templates/CS-Form-6-2020.xlsx');
    }

    /**
     * Writes the filled form to $outputPath and returns it.
     *
     * @param  array  $content  from LeaveFormPrintout
     */
    public function generate(array $content, string $outputPath, ?string $templatePath = null): string
    {
        $templatePath ??= $this->templatePath();

        if (! is_dir(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0775, true);
        }

        // Corrected first, on a copy, then filled.
        $base = $outputPath . '.base.xlsx';
        copy($templatePath, $base);

        try {
            $this->repairs->repair($base);

            $filler = new TemplateFiller($base, $outputPath);

            if (! CsForm6::matches($filler)) {
                $filler->save();

                throw new LeaveFormTemplateMismatch(
                    'The leave form template is not laid out as CS Form No. 6 (Revised 2020), so it was not filled in. '
                    . 'Please ask the HR Office to check the published template.'
                );
            }

            [$sheet] = CsForm6::sheets($filler);

            // A few answer boxes stop short of the line they belong on.
            foreach (CsForm6::BOX_BOTTOMS as $key => $offset) {
                $filler->setShapeBottom($sheet, CsForm6::BOXES[$key], $offset);
            }

            $this->itemsOneToFive($filler, $sheet, $content);
            $this->typeOfLeave($filler, $sheet, $content);
            $this->detailsOfLeave($filler, $sheet, $content);
            $this->daysAndDates($filler, $sheet, $content);
            $this->commutation($filler, $sheet, $content);
            $this->certification($filler, $sheet, $content['certification'] ?? null);
            $this->recommendation($filler, $sheet, $content);
            $this->decision($filler, $sheet, $content['decision'] ?? null);

            return $filler->save();
        } finally {
            @unlink($base);
        }
    }

    // ------------------------------------------------------------------
    // 1–5
    // ------------------------------------------------------------------

    private function itemsOneToFive(TemplateFiller $f, string $sheet, array $c): void
    {
        $applicant = $c['applicant'];

        $this->box($f, $sheet, 'office', $applicant['office'] ?? '', self::ANSWER_SIZE, 'ctr');
        $this->box($f, $sheet, 'last_name', $applicant['last_name'] ?? '', self::NAME_SIZE, 'ctr');
        $this->box($f, $sheet, 'first_name', $applicant['first_name'] ?? '', self::NAME_SIZE, 'ctr');
        $this->box($f, $sheet, 'middle_name', $applicant['middle_name'] ?? '', self::NAME_SIZE, 'ctr');
        $this->box($f, $sheet, 'date_filed', LeaveFormPrintout::longDate($c['date_filed']), self::ANSWER_SIZE, 'ctr');
        $this->box($f, $sheet, 'position', $applicant['position'] ?? '', self::ANSWER_SIZE, 'ctr');
        $this->box($f, $sheet, 'salary', $c['salary'] ?? '', self::ANSWER_SIZE, 'ctr');

        // Over "(Signature of Applicant)": the name, as the form is not signed.
        if (filled($c['applicant_name'] ?? null)) {
            $this->cell($f, $sheet, CsForm6::APPLICANT_CELL, $c['applicant_name'], self::ANSWER_SIZE, 'center');
        }
    }

    // ------------------------------------------------------------------
    // 6.A
    // ------------------------------------------------------------------

    private function typeOfLeave(TemplateFiller $f, string $sheet, array $c): void
    {
        $code = $c['leave_type'];

        if (isset(CsForm6::TYPE_BOXES[$code])) {
            $this->tick($f, $sheet, CsForm6::TYPE_BOXES[$code]);
        }

        if ($code === 'MONETIZE') {
            $this->tick($f, $sheet, CsForm6::DETAIL_BOXES['monetization']);
        }

        if ($code === 'TERMINAL') {
            $this->tick($f, $sheet, CsForm6::DETAIL_BOXES['terminal']);
        }

        if (filled($c['others'] ?? null)) {
            $this->box($f, $sheet, 'others', $c['others'], self::ANSWER_SIZE, 'l');
        }
    }

    // ------------------------------------------------------------------
    // 6.B
    // ------------------------------------------------------------------

    private function detailsOfLeave(TemplateFiller $f, string $sheet, array $c): void
    {
        $d = $c['details'] ?? [];

        switch ($d['location'] ?? null) {
            case 'within':
                $this->tick($f, $sheet, CsForm6::DETAIL_BOXES['within_philippines']);
                $this->box($f, $sheet, 'within_philippines', $d['location_specify'] ?? '', self::ANSWER_SIZE, 'l');
                break;
            case 'abroad':
                $this->tick($f, $sheet, CsForm6::DETAIL_BOXES['abroad']);
                $this->box($f, $sheet, 'abroad', $d['location_specify'] ?? '', self::ANSWER_SIZE, 'l');
                break;
        }

        switch ($d['sickness'] ?? null) {
            case 'hospital':
                $this->tick($f, $sheet, CsForm6::DETAIL_BOXES['in_hospital']);
                $this->lines($f, $sheet, ['in_hospital', 'sick_more'], $d['illness'] ?? '');
                break;
            case 'outpatient':
                $this->tick($f, $sheet, CsForm6::DETAIL_BOXES['out_patient']);
                $this->lines($f, $sheet, ['out_patient', 'sick_more'], $d['illness'] ?? '');
                break;
        }

        if (filled($d['women_illness'] ?? null)) {
            $this->lines($f, $sheet, ['women_illness', 'women_more'], $d['women_illness']);
        }

        if (isset($d['study'])) {
            $this->tick($f, $sheet, CsForm6::DETAIL_BOXES[$d['study'] === 'bar' ? 'bar_review' : 'masters']);
        }
    }

    // ------------------------------------------------------------------
    // 6.C, 6.D
    // ------------------------------------------------------------------

    private function daysAndDates(TemplateFiller $f, string $sheet, array $c): void
    {
        $days = (float) ($c['days'] ?? 0);

        if ($days > 0) {
            $unit = $c['days_only'] || $c['calendar'] ? 'day' : 'working day';
            $this->box($f, $sheet, 'working_days',
                LeaveFormPrintout::days($days) . ' ' . $unit . ($days == 1 ? '' : 's'),
                self::ANSWER_SIZE, 'ctr');
        }

        if (! $c['days_only']) {
            $this->box($f, $sheet, 'inclusive_dates',
                LeaveFormPrintout::inclusiveDates($c['date_from'], $c['date_to']),
                self::ANSWER_SIZE, 'ctr');
        }
    }

    private function commutation(TemplateFiller $f, string $sheet, array $c): void
    {
        $choice = match ($c['commutation'] ?? null) {
            'requested' => 'requested',
            'not_requested' => 'not_requested',
            default => null,
        };

        if ($choice) {
            $this->tick($f, $sheet, CsForm6::DETAIL_BOXES[$choice]);
        }
    }

    // ------------------------------------------------------------------
    // 7.A–7.D
    // ------------------------------------------------------------------

    private function certification(TemplateFiller $f, string $sheet, ?array $credits): void
    {
        if (! $credits) {
            return;
        }

        $this->box($f, $sheet, 'as_of', LeaveFormPrintout::longDate($credits['as_of']), self::ANSWER_SIZE, 'ctr');

        foreach (CsForm6::CREDIT_CELLS as $kind => $cells) {
            foreach ($cells as $row => $cell) {
                $this->cell($f, $sheet, $cell, LeaveFormPrintout::credit((float) $credits[$kind][$row]), self::ANSWER_SIZE, 'center');
            }
        }
    }

    private function recommendation(TemplateFiller $f, string $sheet, array $c): void
    {
        if (filled($c['supervisor'] ?? null)) {
            $this->cell($f, $sheet, CsForm6::SUPERVISOR_CELL, $c['supervisor'], self::NAME_SIZE, 'center');
        }

        $recommendation = $c['recommendation'] ?? null;

        if (! $recommendation) {
            return;
        }

        if ($recommendation['approved']) {
            $this->tick($f, $sheet, CsForm6::DETAIL_BOXES['for_approval']);

            return;
        }

        $this->tick($f, $sheet, CsForm6::DETAIL_BOXES['for_disapproval']);
        $this->lines($f, $sheet, ['disapproval_1', 'disapproval_2', 'disapproval_3', 'disapproval_4'], (string) $recommendation['reason']);
    }

    private function decision(TemplateFiller $f, string $sheet, ?array $decision): void
    {
        if (! $decision) {
            return;
        }

        if (! $decision['approved']) {
            $this->lines($f, $sheet, ['disapproved_1', 'disapproved_2', 'disapproved_3'], (string) $decision['reason']);

            return;
        }

        $this->box($f, $sheet, 'days_with_pay', LeaveFormPrintout::days((float) $decision['with_pay']), self::ANSWER_SIZE, 'ctr');

        if ((float) $decision['without_pay'] > 0) {
            $this->box($f, $sheet, 'days_without_pay', LeaveFormPrintout::days((float) $decision['without_pay']), self::ANSWER_SIZE, 'ctr');
        }
    }

    // ------------------------------------------------------------------
    // Writing
    // ------------------------------------------------------------------

    /** Boxes that sit on a ruled line, where the answer is set on the line. */
    private const ON_A_LINE = [
        'date_filed', 'position', 'salary', 'within_philippines', 'abroad', 'in_hospital', 'out_patient',
        'sick_more', 'women_illness', 'women_more', 'others', 'working_days', 'inclusive_dates', 'as_of',
        'disapproval_1', 'disapproval_2', 'disapproval_3', 'disapproval_4',
        'days_with_pay', 'days_without_pay', 'others_approved',
        'disapproved_1', 'disapproved_2', 'disapproved_3',
    ];

    /** One answer in one of the form's text boxes, shrunk if it would not fit. */
    private function box(TemplateFiller $f, string $sheet, string $key, string $text, float $size, string $align): void
    {
        $text = trim($text);

        if ($text === '') {
            return;
        }

        $name = CsForm6::BOXES[$key];
        $room = $f->shapeTextArea($sheet, $name)['width'];

        $f->setShapeText($sheet, $name, $text, [
            'font' => self::FONT,
            'size' => TextMetrics::fit($text, $room, $size, self::SMALLEST),
            'bold' => true,
            'align' => $align,
        ] + (in_array($key, self::ON_A_LINE, true) ? ['anchor' => 'b'] : []));
    }

    /**
     * Running text over several ruled lines, each a text box. Set at the
     * largest size at which all of it fits the lines there are.
     *
     * @param  list<string>  $keys
     */
    private function lines(TemplateFiller $f, string $sheet, array $keys, string $text): void
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));

        if ($text === '') {
            return;
        }

        $widths = array_map(fn ($key) => $f->shapeTextArea($sheet, CsForm6::BOXES[$key])['width'], $keys);

        for ($size = self::ANSWER_SIZE; $size > self::SMALLEST; $size -= 0.5) {
            $lines = TextMetrics::wrap($text, $widths, $size);
            $last = count($lines) - 1;

            if (TextMetrics::width($lines[$last], $size, true) <= $widths[$last]) {
                break;
            }
        }

        foreach ($lines as $i => $line) {
            $this->box($f, $sheet, $keys[$i], $line, $size, 'l');
        }
    }

    private function cell(TemplateFiller $f, string $sheet, string $cell, string $text, float $size, string $align): void
    {
        $f->setCell($sheet, $cell, $text);

        $width = $f->boxSize($sheet, $cell)['width'] - 4;

        $f->formatCell($sheet, $cell, [
            'font' => self::FONT,
            'size' => TextMetrics::fit($text, $width, $size, self::SMALLEST),
            'bold' => true,
            'horizontal' => $align,
            'vertical' => 'bottom',
        ]);
    }

    private function tick(TemplateFiller $f, string $sheet, int $shapeId): void
    {
        // The form's check boxes have no captions; the id alone names them.
        $f->setCheckbox($sheet, '', true, $shapeId);
    }

    private function isCsForm6(string $path): bool
    {
        $probe = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cs6probe-' . bin2hex(random_bytes(4)) . '.xlsx';

        try {
            $filler = new TemplateFiller($path, $probe);
            $matches = CsForm6::matches($filler);
            $filler->save();

            return $matches;
        } catch (\Throwable) {
            return false;
        } finally {
            @unlink($probe);
        }
    }
}
