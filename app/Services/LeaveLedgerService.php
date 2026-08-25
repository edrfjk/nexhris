<?php

namespace App\Services;

use App\Models\LeaveBalance;
use App\Models\LeaveLedgerEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LeaveLedgerService
{
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
            $entry->update(array_intersect_key($data, array_flip([
                'period_from', 'period_to', 'remarks',
                'vl_earned', 'vl_used', 'vl_used_wop',
                'sl_earned', 'sl_used', 'sl_used_wop',
                'service_earned', 'service_used',
            ])));

            $this->recalculate($entry->user);

            return $entry->refresh();
        });
    }

    /** Strikes a line off the card and replays what follows it. */
    public function deleteEntry(LeaveLedgerEntry $entry): void
    {
        DB::transaction(function () use ($entry) {
            $employee = $entry->user;
            $entry->delete();

            $this->recalculate($employee);
        });
    }

    /**
     * Replays the whole card in date order, rewriting the running balance on
     * every line and the employee's standing balance at the end.
     *
     * This is the only way a mid-card correction can be trusted: the figures
     * below it were all computed from the old value.
     */
    public function recalculate(User $employee): void
    {
        DB::transaction(function () use ($employee) {
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
