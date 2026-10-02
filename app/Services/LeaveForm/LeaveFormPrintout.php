<?php

namespace App\Services\LeaveForm;

use App\Models\LeaveApplication;
use App\Models\User;
use App\Services\LeaveChain;
use App\Support\Leave\LeaveTypes;
use Carbon\Carbon;

/**
 * Everything CS Form No. 6 prints for one filing, gathered in one place.
 *
 * Items 1, 2 and 4 come from the employee's record as it stood when the
 * leave was filed. Section 6 is what the employee entered. Section 7 is
 * built from the reviewers' decisions, so the printed form is always the
 * form as it stands: the Dean's recommendation appears once the Dean has
 * acted, the Campus Director's once they have, and 7.A shows the ledger
 * figures HR certified.
 */
final class LeaveFormPrintout
{
    /** The applicant's details as the form prints them; kept with the filing. */
    public static function applicant(User $user): array
    {
        $parts = $user->nameParts();
        $middle = trim((string) $user->middle_name);

        return [
            'office' => $user->collegeName() ?: ($user->departmentName() ?: ''),
            'last_name' => $parts['family'],
            'first_name' => $parts['first'],
            // The form asks for the middle name, not an initial.
            'middle_name' => $middle !== '' ? mb_strtoupper($middle) : rtrim($parts['middle'], '.'),
            'position' => (string) $user->position,
        ];
    }

    /** "JUAN S. DELA CRUZ", the way the form's printed signatories read. */
    public static function signatory(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        $parts = $user->nameParts();

        return trim(preg_replace('/\s+/', ' ', "{$parts['first']} {$parts['middle']} {$parts['family']}"));
    }

    /** The printout of a filed application, as it stands now. */
    public static function forApplication(LeaveApplication $application): array
    {
        $application->loadMissing(['user.leaveBalance', 'user.college.dean', 'dean', 'ledgerEntry']);

        $data = $application->form_data ?? [];
        $applicant = $application->user;

        $content = self::section6(
            $data + [
                'leave_type' => $application->leave_type,
                'date_from' => $application->date_from?->toDateString(),
                'date_to' => $application->date_to?->toDateString(),
                'days' => (float) $application->days,
            ],
            $data['applicant'] ?? self::applicant($applicant),
            $applicant,
        );

        $content['certification'] = self::certification($application);
        $content['supervisor'] = self::supervisorFor($application);
        $content['recommendation'] = self::recommendation($application);
        $content['decision'] = self::decision($application, $content['certification']);

        return $content;
    }

    /**
     * The printout of a filing not yet submitted, for the employee to check
     * before sending it. Section 7 is blank but for the credits on file.
     */
    public static function forDraft(array $input, User $applicant): array
    {
        $content = self::section6($input, self::applicant($applicant), $applicant);

        $balance = $applicant->leaveBalance;
        $content['certification'] = self::credits(
            now()->toDateString(),
            (float) ($balance->vl_balance ?? 0),
            (float) ($balance->sl_balance ?? 0),
            $input['leave_type'] ?? null,
            (float) $content['days'],
        );
        $content['supervisor'] = self::signatory($applicant->college?->dean);
        $content['recommendation'] = null;
        $content['decision'] = null;

        return $content;
    }

    // ------------------------------------------------------------------

    private static function section6(array $input, array $applicant, User $user): array
    {
        $code = $input['leave_type'] ?? null;
        $type = LeaveTypes::has($code) ? LeaveTypes::get($code) : null;

        $others = match (true) {
            $code === 'OTHERS' => trim((string) ($input['others_specify'] ?? '')),
            $type && $type['group'] === LeaveTypes::OTHERS => $type['label'],
            default => null,
        };

        return [
            'applicant' => $applicant,
            'applicant_name' => self::signatory($user),
            'date_filed' => $input['date_filed'] ?? now()->toDateString(),
            'salary' => trim((string) ($input['salary'] ?? '')),
            'leave_type' => $code,
            'group' => $type['group'] ?? null,
            'others' => $others,
            'details' => array_filter($input['details'] ?? [], fn ($v) => filled($v)),
            'days_only' => (bool) ($type['days_only'] ?? false),
            'calendar' => (bool) ($type['calendar'] ?? false),
            'date_from' => $input['date_from'] ?? null,
            'date_to' => $input['date_to'] ?? null,
            'days' => (float) ($input['days'] ?? 0),
            'commutation' => $input['commutation'] ?? null,
        ];
    }

    /**
     * 7.A — the VL and SL credits, as HR certified them when they approved.
     * Until HR acts, the figures are the ledger's today.
     */
    private static function certification(LeaveApplication $application): array
    {
        $snapshot = $application->credit_certification;

        if (! $snapshot) {
            $balance = $application->user?->leaveBalance;
            $snapshot = [
                'as_of' => now()->toDateString(),
                'vl' => (float) ($balance->vl_balance ?? 0),
                'sl' => (float) ($balance->sl_balance ?? 0),
            ];
        }

        return self::credits(
            $snapshot['as_of'],
            (float) $snapshot['vl'],
            (float) $snapshot['sl'],
            $application->leave_type,
            (float) $application->days,
        );
    }

    private static function credits(string $asOf, float $vl, float $sl, ?string $code, float $days): array
    {
        $charge = LeaveTypes::charge($code);

        // Only what the credits can cover is charged against them; anything
        // beyond is leave without pay, shown under 7.C.
        $lessVl = $charge === 'vl' ? min($days, max(0.0, $vl)) : 0.0;
        $lessSl = $charge === 'sl' ? min($days, max(0.0, $sl)) : 0.0;

        return [
            'as_of' => $asOf,
            'charge' => $charge,
            'vl' => ['earned' => $vl, 'less' => $lessVl, 'balance' => round($vl - $lessVl, 3)],
            'sl' => ['earned' => $sl, 'less' => $lessSl, 'balance' => round($sl - $lessSl, 3)],
        ];
    }

    /** The Dean of the applicant's college, named over "Immediate Supervisor/Head". */
    private static function supervisorFor(LeaveApplication $application): ?string
    {
        $applicant = $application->user;

        if (! $applicant || ! app(LeaveChain::class)->stageApplies($applicant, 'dean')) {
            return null; // A Dean's own form, or the Campus Director's, has no Dean stage.
        }

        return self::signatory($application->dean ?? $applicant->college?->dean);
    }

    /** 7.B — the Dean's recommendation, once given. */
    private static function recommendation(LeaveApplication $application): ?array
    {
        return match ($application->dean_status) {
            'approved' => ['approved' => true, 'reason' => null],
            'returned' => ['approved' => false, 'reason' => $application->dean_remarks],
            default => null,
        };
    }

    /** 7.C or 7.D — the final decision, once made. */
    private static function decision(LeaveApplication $application, array $certification): ?array
    {
        if ($application->director_status === 'returned') {
            return ['approved' => false, 'reason' => $application->director_remarks];
        }

        if (! $application->isFullyApproved()) {
            return null;
        }

        $days = (float) $application->days;

        // Once HR has posted it, the ledger says exactly what was charged.
        if ($entry = $application->ledgerEntry) {
            $withPay = (float) $entry->vl_used + (float) $entry->sl_used + (float) $entry->service_used;
            $withoutPay = (float) $entry->vl_used_wop + (float) $entry->sl_used_wop;

            return ['approved' => true, 'with_pay' => $withPay, 'without_pay' => $withoutPay];
        }

        $withPay = match ($certification['charge']) {
            'vl' => $certification['vl']['less'],
            'sl' => $certification['sl']['less'],
            'service' => min($days, (float) ($application->user?->leaveBalance->service_balance ?? 0)),
            default => $days,
        };

        return ['approved' => true, 'with_pay' => $withPay, 'without_pay' => round(max(0.0, $days - $withPay), 3)];
    }

    // ------------------------------------------------------------------
    // How dates and figures are written on the form
    // ------------------------------------------------------------------

    public static function longDate(?string $date): string
    {
        return $date ? Carbon::parse($date)->format('F j, Y') : '';
    }

    /** "October 5–7, 2026", "October 30 – November 3, 2026". */
    public static function inclusiveDates(?string $from, ?string $to): string
    {
        if (! $from) {
            return '';
        }

        $a = Carbon::parse($from);
        $b = $to ? Carbon::parse($to) : $a;

        return match (true) {
            $a->isSameDay($b) => $a->format('F j, Y'),
            $a->isSameMonth($b) => $a->format('F j') . "\u{2013}" . $b->format('j, Y'),
            $a->isSameYear($b) => $a->format('F j') . " \u{2013} " . $b->format('F j, Y'),
            default => $a->format('F j, Y') . " \u{2013} " . $b->format('F j, Y'),
        };
    }

    /** Credits print with the ledger card's three decimals. */
    public static function credit(float $value): string
    {
        return number_format($value, 3);
    }

    public static function days(float $value): string
    {
        return LeavePolicy::number($value);
    }
}
