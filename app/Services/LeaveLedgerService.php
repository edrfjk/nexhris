<?php

namespace App\Services;

use App\Models\LeaveBalance;
use App\Models\LeaveLedgerEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LeaveLedgerService
{
    /**
     * Every other sensitive action in the system writes to the activity log —
     * logins, leave approvals, PDS submissions, template publishing. The
     * ledger was the exception, and it is the one table that says how much
     * leave a person has earned over a career: a line could be posted, edited
     * or struck off with no record of who did it or what the figure was
     * before.
     */
    public function __construct(private ActivityLogger $log)
    {
    }

    /**
     * Posts one line to an employee's ledger card and rolls the running
     * balances forward.
     *
     * Absences fall into two columns on the official card. "With pay" is
     * charged against the credit balance; "without pay" is recorded for the
     * payroll record but leaves the balance untouched — that is precisely the
     * case where the employee had no credits left to spend.
     */
    public function postEntry(
        User $employee,
        string $periodFrom,
        string $periodTo,
        string $type,
        ?string $remarks = null,
        float $vlEarned = 0,
        float $vlUsed = 0,
        float $vlUsedWop = 0,
        float $slEarned = 0,
        float $slUsed = 0,
        float $slUsedWop = 0,
        float $serviceEarned = 0,
        float $serviceUsed = 0,
        ?int $leaveApplicationId = null,
        ?int $encodedBy = null,
        ?string $yearLabel = null,
        string $ledger = LeaveLedgerEntry::LEAVE,
    ): LeaveLedgerEntry {
        return DB::transaction(function () use (
            $employee, $periodFrom, $periodTo, $type, $remarks,
            $vlEarned, $vlUsed, $vlUsedWop, $slEarned, $slUsed, $slUsedWop,
            $serviceEarned, $serviceUsed, $leaveApplicationId, $encodedBy, $yearLabel, $ledger
        ) {
            // All balance-changing ledger operations take the employee row as
            // their common lock. Without this, two HR requests can both read
            // the same balance and the later write silently overwrites the
            // first one.
            User::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $balance = LeaveBalance::firstOrCreate(['user_id' => $employee->id]);

            if ($ledger === LeaveLedgerEntry::SERVICE) {
                // A day written on the service card is charged to service
                // credits whether it was sick or vacation leave, so the
                // leave balances are left exactly where they were.
                $newVlBalance = round((float) $balance->vl_balance, 2);
                $newSlBalance = round((float) $balance->sl_balance, 2);
                $newServiceBalance = round(
                    (float) $balance->service_balance + $serviceEarned
                    - $vlUsed - $slUsed - $serviceUsed,
                    3,
                );
            } else {
                $newVlBalance = round((float) $balance->vl_balance + $vlEarned - $vlUsed, 2);
                $newSlBalance = round((float) $balance->sl_balance + $slEarned - $slUsed, 2);
                $newServiceBalance = round((float) $balance->service_balance, 3);
            }

            if ($newVlBalance < 0 || $newSlBalance < 0 || $newServiceBalance < 0) {
                throw new \RuntimeException(
                    "This would leave {$employee->name} with a negative balance "
                    . '(VL: ' . number_format($newVlBalance, 2)
                    . ', SL: ' . number_format($newSlBalance, 2)
                    . ', Service credits: ' . number_format($newServiceBalance, 2) . '). '
                    . 'Record the excess days in the "without pay" column instead.'
                );
            }

            $entry = LeaveLedgerEntry::create([
                'user_id' => $employee->id,
                'period_from' => $periodFrom,
                'period_to' => $periodTo,
                'year_label' => $yearLabel ?: date('Y', strtotime($periodFrom)),
                'remarks' => $remarks,
                'type' => $type,
                // Set only on accruals. The unique index across
                // (user_id, ledger, accrual_period) then refuses a second
                // credit for the same month while leaving absences — of which
                // there may be several in a month — untouched.
                'accrual_period' => $type === 'earned'
                    ? self::accrualPeriod($periodFrom, $periodTo)
                    : null,
                'ledger' => $ledger,
                'vl_earned' => $vlEarned,
                'vl_used' => $vlUsed,
                'vl_used_wop' => $vlUsedWop,
                'vl_balance' => $newVlBalance,
                'sl_earned' => $slEarned,
                'sl_used' => $slUsed,
                'sl_used_wop' => $slUsedWop,
                'sl_balance' => $newSlBalance,
                'service_earned' => $serviceEarned,
                'service_used' => $serviceUsed,
                'service_balance' => $newServiceBalance,
                'leave_application_id' => $leaveApplicationId,
                'encoded_by' => $encodedBy ?? auth()->id(),
            ]);

            $balance->update([
                'vl_balance' => $newVlBalance,
                'sl_balance' => $newSlBalance,
                'service_balance' => $newServiceBalance,
            ]);

            $this->log->log(
                'ledger.posted',
                sprintf(
                    "%s posted a %s entry on the %s card of %s.",
                    auth()->user()->name ?? 'The system',
                    $type,
                    $ledger,
                    $employee->name,
                ),
                $entry,
                [
                    'ledger' => $ledger,
                    'type' => $type,
                    'period' => $periodFrom . ' to ' . $periodTo,
                    'vl' => ['earned' => $vlEarned, 'used' => $vlUsed, 'balance' => $newVlBalance],
                    'sl' => ['earned' => $slEarned, 'used' => $slUsed, 'balance' => $newSlBalance],
                    'service' => ['earned' => $serviceEarned, 'used' => $serviceUsed, 'balance' => $newServiceBalance],
                ],
            );

            return $entry;
        });
    }

    /**
     * Corrects a line already on the card.
     *
     * Every row carries the balance as it stood after that line, so changing
     * one in the middle invalidates every line below it. The card is replayed
     * afterwards rather than patched.
     */
    public function updateEntry(LeaveLedgerEntry $entry, array $data): LeaveLedgerEntry
    {
        return DB::transaction(function () use ($entry, $data) {
            $fields = [
                'period_from', 'period_to', 'remarks',
                'vl_earned', 'vl_used', 'vl_used_wop',
                'sl_earned', 'sl_used', 'sl_used_wop',
                'service_earned', 'service_used',
            ];

            // Captured before the write: an audit line that cannot say what the
            // figure used to be does not settle an argument about a balance.
            $before = array_intersect_key($entry->getOriginal(), array_flip($fields));

            $entry->update(array_intersect_key($data, array_flip($fields)));

            // A corrected period changes which month the accrual belongs to.
            if ($entry->type === 'earned') {
                $entry->update([
                    'accrual_period' => self::accrualPeriod(
                        (string) $entry->period_from,
                        (string) $entry->period_to,
                    ),
                ]);
            }

            $changed = array_keys(array_diff_assoc(
                array_map('strval', array_intersect_key($entry->getAttributes(), array_flip($fields))),
                array_map('strval', $before),
            ));

            $this->recalculate($entry->user, rejectNegative: true);

            $this->log->log(
                'ledger.edited',
                sprintf(
                    "%s corrected a line on the ledger card of %s (%s).",
                    auth()->user()->name ?? 'The system',
                    $entry->user->name,
                    $changed ? implode(', ', $changed) : 'no field changed',
                ),
                $entry,
                ['changed' => $changed, 'before' => $before],
            );

            return $entry->refresh();
        });
    }

    /** The month an accrual credits, as the unique index sees it. */
    private static function accrualPeriod(string $from, string $to): string
    {
        return substr($from, 0, 10) . ':' . substr($to, 0, 10);
    }

    /** Strikes a line off the card and replays what follows it. */
    public function deleteEntry(LeaveLedgerEntry $entry): void
    {
        DB::transaction(function () use ($entry) {
            $employee = $entry->user;

            // The row is about to stop existing, so everything worth keeping
            // has to be copied out first.
            $record = $entry->only([
                'ledger', 'type', 'period_from', 'period_to', 'remarks',
                'vl_earned', 'vl_used', 'sl_earned', 'sl_used',
                'service_earned', 'service_used',
            ]);

            $entry->delete();

            $this->recalculate($employee, rejectNegative: true);

            $this->log->log(
                'ledger.deleted',
                sprintf(
                    "%s struck a line off the ledger card of %s.",
                    auth()->user()->name ?? 'The system',
                    $employee->name,
                ),
                $employee,
                ['removed' => $record],
            );
        });
    }

    /**
     * Replays the whole card in date order, rewriting the running balance on
     * every line and the employee's standing balance at the end.
     *
     * This is the only way a mid-card correction can be trusted: the figures
     * below it were all computed from the old value.
     */
    public function recalculate(User $employee, bool $rejectNegative = false): void
    {
        DB::transaction(function () use ($employee, $rejectNegative) {
            // Use the same lock as postEntry(), updateEntry(), and
            // deleteEntry() so replaying a card cannot race a new posting.
            User::whereKey($employee->id)->lockForUpdate()->firstOrFail();

            $vl = 0.0;
            $sl = 0.0;
            $service = 0.0;

            $entries = LeaveLedgerEntry::where('user_id', $employee->id)
                ->orderBy('period_from')
                ->orderBy('id')
                ->get();

            foreach ($entries as $entry) {
                if ($entry->isOnServiceCard()) {
                    // A day taken against service credits comes off the
                    // service balance whether it was sick or vacation leave.
                    // The card runs to three places.
                    $service = round(
                        $service + (float) $entry->service_earned - $entry->daysCharged(), 3
                    );

                    if ($rejectNegative && $service < 0) {
                        throw new \RuntimeException(
                            "This correction would make {$employee->name}'s service-credit balance negative."
                        );
                    }

                    // The leave columns are not this card's business; the
                    // printed form leaves them blank either way.
                    $entry->updateQuietly([
                        'vl_balance' => 0,
                        'sl_balance' => 0,
                        'service_balance' => $service,
                    ]);

                    continue;
                }

                // The leave card charges vacation and sick separately, to two
                // places, and never touches service credits.
                $vl = round($vl + (float) $entry->vl_earned - (float) $entry->vl_used, 2);
                $sl = round($sl + (float) $entry->sl_earned - (float) $entry->sl_used, 2);

                if ($rejectNegative && ($vl < 0 || $sl < 0)) {
                    throw new \RuntimeException(
                        "This correction would make {$employee->name}'s leave balance negative."
                    );
                }

                $entry->updateQuietly([
                    'vl_balance' => $vl,
                    'sl_balance' => $sl,
                    'service_balance' => 0,
                ]);
            }

            LeaveBalance::updateOrCreate(
                ['user_id' => $employee->id],
                ['vl_balance' => $vl, 'sl_balance' => $sl, 'service_balance' => $service],
            );
        });
    }
}
