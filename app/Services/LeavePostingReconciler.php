<?php

namespace App\Services;

use App\Models\LeaveApplication;
use App\Models\LeaveLedgerEntry;
use App\Support\Leave\LeaveTypes;

/**
 * What an approved leave form should post as, and where a posting departs
 * from it (LEDGER-03, decision B).
 *
 * The expected charge follows the credits certified at approval (7.A): days
 * the certified balance covers are charged with pay to the type's own credit,
 * the rest without pay. It deliberately imposes no other rule — in
 * particular, no "with pay + without pay = days" check (decision C is still
 * open). It only reports differences; HR may still post them, with a reason.
 */
class LeavePostingReconciler
{
    public const CHARGE_COLUMNS = ['vl_used', 'vl_used_wop', 'sl_used', 'sl_used_wop', 'service_used'];

    /**
     * @return array{date_from: ?string, date_to: ?string, days: float, charges: array<string, float>}
     */
    public function expected(LeaveApplication $application): array
    {
        $days = round((float) $application->days, 3);
        $charges = array_fill_keys(self::CHARGE_COLUMNS, 0.0);
        $certified = $application->credit_certification ?? [];
        $balance = $application->user?->leaveBalance;

        switch (LeaveTypes::charge($application->leave_type)) {
            case 'vl':
                $available = (float) ($certified['vl'] ?? $balance->vl_balance ?? 0);
                $charges['vl_used'] = round(min($days, max(0.0, $available)), 3);
                $charges['vl_used_wop'] = round($days - $charges['vl_used'], 3);
                break;

            case 'sl':
                $available = (float) ($certified['sl'] ?? $balance->sl_balance ?? 0);
                $charges['sl_used'] = round(min($days, max(0.0, $available)), 3);
                $charges['sl_used_wop'] = round($days - $charges['sl_used'], 3);
                break;

            case 'service':
                // The service card has no without-pay column; the whole leave
                // is charged to service credits, as the form already prefilled.
                $charges['service_used'] = $days;
                break;

            // Types not charged to credits (special leaves, maternity, …)
            // post no charge by default.
        }

        return [
            'date_from' => $application->date_from?->toDateString(),
            'date_to' => $application->date_to?->toDateString(),
            'days' => $days,
            'charges' => $charges,
        ];
    }

    /**
     * Each way the posting departs from the approved form, in words HR reads
     * on the refusal message and in the audit trail.
     *
     * On the service-credits card every with-pay column is charged to
     * service credits (LeaveLedgerEntry::daysCharged), so there the with-pay
     * columns are compared as one service-credit charge. Moving a VL or SL
     * leave onto that card is still a different allocation, and is reported.
     *
     * @param  array{period_from: string, period_to: string, days: float|string, charges: array<string, float|string>}  $posted
     * @return list<array{field: string, approved: mixed, posted: mixed}>
     */
    public function differences(LeaveApplication $application, array $posted, string $ledger = LeaveLedgerEntry::LEAVE): array
    {
        $expected = $this->expected($application);
        $differences = [];

        if ($ledger === LeaveLedgerEntry::SERVICE) {
            $c = $posted['charges'];
            $posted['charges']['service_used'] = (float) ($c['vl_used'] ?? 0) + (float) ($c['sl_used'] ?? 0) + (float) ($c['service_used'] ?? 0);
            $posted['charges']['vl_used'] = 0.0;
            $posted['charges']['sl_used'] = 0.0;
        }

        foreach (['period_from' => 'date_from', 'period_to' => 'date_to'] as $postedKey => $key) {
            $value = date('Y-m-d', strtotime((string) $posted[$postedKey]));

            if ($expected[$key] !== $value) {
                $differences[] = ['field' => $key, 'approved' => $expected[$key], 'posted' => $value];
            }
        }

        if (abs($expected['days'] - (float) $posted['days']) > 0.0005) {
            $differences[] = ['field' => 'days', 'approved' => $expected['days'], 'posted' => round((float) $posted['days'], 3)];
        }

        foreach (self::CHARGE_COLUMNS as $column) {
            $value = round((float) ($posted['charges'][$column] ?? 0), 3);

            if (abs($expected['charges'][$column] - $value) > 0.0005) {
                $differences[] = ['field' => $column, 'approved' => $expected['charges'][$column], 'posted' => $value];
            }
        }

        return $differences;
    }

    public static function label(string $field): string
    {
        return [
            'date_from' => 'first day',
            'date_to' => 'last day',
            'days' => 'days',
            'vl_used' => 'VL with pay',
            'vl_used_wop' => 'VL without pay',
            'sl_used' => 'SL with pay',
            'sl_used_wop' => 'SL without pay',
            'service_used' => 'service credits',
        ][$field] ?? $field;
    }

    /** "days: approved 2, posted 3; VL without pay: approved 0, posted 3" */
    public static function describe(array $differences): string
    {
        return collect($differences)
            ->map(fn ($d) => sprintf('%s: approved %s, posted %s', self::label($d['field']), self::show($d['approved']), self::show($d['posted'])))
            ->join('; ');
    }

    private static function show(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        return is_numeric($value)
            ? rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.')
            : (string) $value;
    }
}
