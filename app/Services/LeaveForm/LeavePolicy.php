<?php

namespace App\Services\LeaveForm;

use App\Models\LeaveApplication;
use App\Models\User;
use App\Support\Leave\LeaveTypes;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Checks a leave filing against page 2 of CS Form No. 6 — "Instructions and
 * Requirements" — before it reaches anyone's desk.
 *
 * Two kinds of finding:
 *
 *  - errors stop the filing. They are the rules the form states as limits
 *    (Special Privilege Leave is three days, a calamity leave is taken within
 *    thirty days of the calamity and once a year) and the details the form
 *    cannot be read without (where a vacation is spent, what the illness is).
 *  - warnings are shown and the filing goes ahead. They are the rules the
 *    form itself softens with "whenever possible" or "except in emergency",
 *    the documents that must accompany the form, and credits that will not
 *    cover the days — HR decides whether those days go without pay.
 *
 * The on-screen form asks this class as the employee types, and the filing
 * is checked again when it is submitted, so the two can never disagree.
 */
class LeavePolicy
{
    /** Statuses that mean a filing no longer counts against a limit. */
    private const NOT_COUNTED = ['draft', 'dean_returned', 'hr_returned', 'cd_returned'];

    /**
     * @param  array  $input  leave_type, others_specify, date_from, date_to, days,
     *                        details[location, location_specify, sickness, illness,
     *                        women_illness, study, calamity_date], commutation,
     *                        has_attachments
     * @return array{errors: array<string, string>, warnings: list<string>, documents: list<string>, days: float, calendar_days: int}
     */
    public function check(array $input, User $applicant, ?int $ignoreApplicationId = null, ?CarbonInterface $today = null): array
    {
        $today = Carbon::parse($today ?? now())->startOfDay();
        $errors = [];
        $warnings = [];
        $documents = [];

        $code = $input['leave_type'] ?? null;

        if (! LeaveTypes::has($code)) {
            return [
                'errors' => ['leave_type' => 'Choose the type of leave.'],
                'warnings' => [], 'documents' => [], 'days' => 0.0, 'calendar_days' => 0,
            ];
        }

        $type = LeaveTypes::get($code);
        $label = $type['label'];
        $details = $input['details'] ?? [];

        // ------------------------------------------------------------
        // 6.A Others
        // ------------------------------------------------------------
        if ($code === 'OTHERS' && ! filled($input['others_specify'] ?? null)) {
            $errors['others_specify'] = 'Say what the leave is for — it is printed on the form\'s "Others" line.';
        }

        // ------------------------------------------------------------
        // 6.C Days and dates
        // ------------------------------------------------------------
        [$from, $to] = [$this->date($input['date_from'] ?? null), $this->date($input['date_to'] ?? null)];
        $days = 0.0;
        $calendarDays = 0;

        if ($type['days_only']) {
            $days = round((float) ($input['days'] ?? 0), 2);

            if ($days <= 0) {
                $errors['days'] = 'Enter the number of days.';
            }
        } else {
            if (! $from) {
                $errors['date_from'] = 'Choose the first day of the leave.';
            }

            if (! $to) {
                $errors['date_to'] = 'Choose the last day of the leave.';
            }

            if ($from && $to && $to->lt($from)) {
                $errors['date_to'] = 'The last day cannot come before the first day.';
            }
        }

        if ($from && $to && ! $to->lt($from)) {
            $calendarDays = (int) $from->diffInDays($to) + 1;
            $days = $type['calendar']
                ? (float) $calendarDays
                : (float) self::workingDays($from, $to);

            if ($days <= 0) {
                $errors['date_from'] = 'Those dates fall on a weekend — there are no working days to file.';
            }

            if ($type['max_days'] !== null && $days > $type['max_days']) {
                $unit = $type['calendar'] ? 'days' : 'working days';
                $errors['date_to'] = sprintf(
                    '%s is up to %d %s; these dates come to %s.',
                    $label, $type['max_days'], $unit, self::number($days),
                );
            }

            if ($type['max_months'] !== null && $to->gt($from->copy()->addMonthsNoOverflow($type['max_months'])->subDay())) {
                $errors['date_to'] = sprintf(
                    '%s is up to %d month%s; these dates run past %s.',
                    $label, $type['max_months'], $type['max_months'] === 1 ? '' : 's',
                    $from->copy()->addMonthsNoOverflow($type['max_months'])->subDay()->format('F j, Y'),
                );
            }

            if ($overlap = $this->overlapping($applicant, $from, $to, $ignoreApplicationId)) {
                $errors['date_from'] = sprintf(
                    'You already filed leave covering %s to %s. Choose other dates, or wait for that form to be returned before filing it again.',
                    $overlap->date_from->format('M j, Y'),
                    $overlap->date_to->format('M j, Y'),
                );
            }
        }

        // Limits across the year: Special Privilege and Solo Parent leave
        // have a yearly allowance, calamity leave is once a year.
        $year = ($from ?? $today)->year;

        if ($type['per_year'] !== null && $days > 0) {
            $used = $this->daysFiledInYear($applicant, $code, $year, $ignoreApplicationId);

            if ($used + $days > $type['per_year']) {
                $errors['date_to'] = sprintf(
                    '%s is %d days a year. You have already filed %s day%s in %d, so %s more would go over.',
                    $label, $type['per_year'], self::number($used), $used == 1 ? '' : 's', $year, self::number($days),
                );
            }
        }

        if ($type['once_a_year'] && $this->filedInYear($applicant, $code, $year, $ignoreApplicationId)) {
            $errors['leave_type'] = sprintf('%s may be taken once a year, and you have already filed it in %d.', $label, $year);
        }

        // ------------------------------------------------------------
        // 6.B Details
        // ------------------------------------------------------------
        switch ($type['detail']) {
            case LeaveTypes::DETAIL_LOCATION:
                $location = $details['location'] ?? null;

                if (! isset(LeaveTypes::LOCATIONS[$location])) {
                    $errors['details.location'] = 'Say whether the leave will be spent within the Philippines or abroad — it is needed for your travel authority.';
                } elseif ($location === 'abroad' && ! filled($details['location_specify'] ?? null)) {
                    $errors['details.location_specify'] = 'Name the country you will travel to.';
                }
                break;

            case LeaveTypes::DETAIL_SICKNESS:
                if (! isset(LeaveTypes::SICKNESS[$details['sickness'] ?? null])) {
                    $errors['details.sickness'] = 'Say whether you were in hospital or an out-patient.';
                }

                if (! filled($details['illness'] ?? null)) {
                    $errors['details.illness'] = 'Specify the illness.';
                }
                break;

            case LeaveTypes::DETAIL_WOMEN:
                if (! filled($details['women_illness'] ?? null)) {
                    $errors['details.women_illness'] = 'Specify the illness or the gynecological surgery.';
                }
                break;

            case LeaveTypes::DETAIL_STUDY:
                if (! isset(LeaveTypes::STUDY[$details['study'] ?? null])) {
                    $errors['details.study'] = 'Say whether the study leave is for completing a master\'s degree or a BAR/Board examination review.';
                }
                break;
        }

        // Calamity leave runs within thirty days of the calamity.
        if ($code === 'SEL') {
            $calamity = $this->date($details['calamity_date'] ?? null);

            if (! $calamity) {
                $errors['details.calamity_date'] = 'Enter the date the calamity or disaster occurred.';
            } elseif ($calamity->gt($today)) {
                $errors['details.calamity_date'] = 'The calamity date cannot be in the future.';
            } elseif ($from && $to) {
                $deadline = $calamity->copy()->addDays(30);

                if ($from->lt($calamity)) {
                    $errors['date_from'] = 'Calamity leave starts on or after the day of the calamity.';
                } elseif ($to->gt($deadline)) {
                    $errors['date_to'] = sprintf(
                        'Calamity leave is taken within thirty (30) days of the calamity — by %s.',
                        $deadline->format('F j, Y'),
                    );
                }
            }
        }

        // ------------------------------------------------------------
        // 6.D Commutation
        // ------------------------------------------------------------
        if (! isset(LeaveTypes::COMMUTATION[$input['commutation'] ?? null])) {
            $errors['commutation'] = 'Say whether commutation is requested.';
        }

        // ------------------------------------------------------------
        // Reminders: timing, documents, credits
        // ------------------------------------------------------------
        if ($from && $type['advance'] !== null) {
            $ahead = (int) $today->diffInDays($from, false);

            if ($ahead < 0) {
                $warnings[] = sprintf(
                    '%s is normally filed before it starts. These dates have already begun — the %s may ask why it was filed late.',
                    $label, $this->firstReviewer($applicant),
                );
            } elseif ($ahead < $type['advance']) {
                $warnings[] = sprintf(
                    '%s should be filed %s ahead whenever possible; this starts in %d day%s.',
                    $label,
                    $type['advance'] === 7 ? 'at least one (1) week' : sprintf('%s (%d) days', self::words($type['advance']), $type['advance']),
                    $ahead, $ahead === 1 ? '' : 's',
                );
            }
        }

        if ($code === 'SL' && $from && $to) {
            if ($from->gt($today) || $days > 5) {
                $documents[] = 'A medical certificate (sick leave filed in advance or longer than five days). If you did not consult a doctor, an affidavit instead.';
            }

            if ($to->lt($today->copy()->subDays(7))) {
                $warnings[] = 'Sick leave is filed immediately upon your return to work. This leave ended more than a week ago.';
            }
        }

        if ($code === 'RL') {
            $warnings[] = 'Rehabilitation leave is applied for within one (1) week of the accident, unless a longer period is warranted.';
        }

        foreach ($type['documents'] as $document) {
            $documents[] = $document;
        }

        // The form's footnote: thirty calendar days or more, or terminal leave.
        if ($code === 'TERMINAL' || $calendarDays >= 30) {
            $clearance = 'Clearance from money, property and work-related accountabilities (leave of thirty days or more, and terminal leave).';

            if (! in_array('Clearance from money, property and work-related accountabilities', $documents, true)) {
                $documents[] = $clearance;
            } else {
                $documents = array_map(
                    fn ($d) => $d === 'Clearance from money, property and work-related accountabilities' ? $clearance : $d,
                    $documents,
                );
            }
        }

        if ($documents && empty($input['has_attachments'])) {
            $warnings[] = 'This leave needs supporting documents. Attach them here, or hand them to HR with the form.';
        }

        $charge = $type['charge'];

        if ($charge && $days > 0) {
            $available = $this->balance($applicant, $charge);

            if ($days > $available) {
                $warnings[] = sprintf(
                    'You have %s %s day%s and are filing %s. HR decides whether the other %s go without pay.',
                    self::number($available),
                    ['vl' => 'vacation leave', 'sl' => 'sick leave', 'service' => 'service credit'][$charge],
                    $available == 1 ? '' : 's',
                    self::number($days),
                    self::number(round($days - $available, 2)),
                );
            }
        }

        return [
            'errors' => $errors,
            'warnings' => array_values(array_unique($warnings)),
            'documents' => array_values(array_unique($documents)),
            'days' => $days,
            'calendar_days' => $calendarDays,
        ];
    }

    /** Monday to Friday between two dates, both included. */
    public static function workingDays(CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) Carbon::parse($from)->startOfDay()->diffInWeekdays(Carbon::parse($to)->startOfDay()->addDay());
    }

    // ------------------------------------------------------------------

    private function overlapping(User $applicant, Carbon $from, Carbon $to, ?int $ignore): ?LeaveApplication
    {
        return $applicant->leaveApplications()
            ->whereNotIn('status', self::NOT_COUNTED)
            ->where('leave_type', '!=', 'MONETIZE')
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore))
            ->whereDate('date_from', '<=', $to->toDateString())
            ->whereDate('date_to', '>=', $from->toDateString())
            ->first();
    }

    private function daysFiledInYear(User $applicant, string $code, int $year, ?int $ignore): float
    {
        return (float) $applicant->leaveApplications()
            ->where('leave_type', $code)
            ->whereNotIn('status', self::NOT_COUNTED)
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore))
            ->whereYear('date_from', $year)
            ->sum('days');
    }

    private function filedInYear(User $applicant, string $code, int $year, ?int $ignore): bool
    {
        return $applicant->leaveApplications()
            ->where('leave_type', $code)
            ->whereNotIn('status', self::NOT_COUNTED)
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore))
            ->whereYear('date_from', $year)
            ->exists();
    }

    private function balance(User $applicant, string $charge): float
    {
        $balance = $applicant->leaveBalance;

        return (float) match ($charge) {
            'vl' => $balance->vl_balance ?? 0,
            'sl' => $balance->sl_balance ?? 0,
            'service' => $balance->service_balance ?? 0,
            default => 0,
        };
    }

    private function firstReviewer(User $applicant): string
    {
        $first = app(\App\Services\LeaveChain::class)->stagesFor($applicant)[0] ?? null;

        return $first ? \App\Services\LeaveChain::LABELS[$first] : 'reviewer';
    }

    private function date(mixed $value): ?Carbon
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private static function words(int $n): string
    {
        return [1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six', 7 => 'seven'][$n] ?? (string) $n;
    }
}
