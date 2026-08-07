<?php
namespace App\Services;

use App\Models\LeaveBalance;
use App\Models\LeaveLedgerEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LeaveLedgerService
{
    public function postEntry(
        User $employee,
        string $periodFrom,
        string $periodTo,
        string $type,
        ?string $remarks = null,
        float $vlEarned = 0,
        float $vlUsed = 0,
        float $slEarned = 0,
        float $slUsed = 0,
        ?int $leaveApplicationId = null,
        ?int $encodedBy = null,
    ): LeaveLedgerEntry {
        return DB::transaction(function () use (
            $employee, $periodFrom, $periodTo, $type, $remarks,
            $vlEarned, $vlUsed, $slEarned, $slUsed, $leaveApplicationId, $encodedBy
        ) {
            $balance = LeaveBalance::firstOrCreate(['user_id' => $employee->id]);

            $newVlBalance = $balance->vl_balance + $vlEarned - $vlUsed;
            $newSlBalance = $balance->sl_balance + $slEarned - $slUsed;

            if ($newVlBalance < 0 || $newSlBalance < 0) {
                throw new \RuntimeException(
                    "This would result in a negative balance for {$employee->name} " .
                    "(VL: " . number_format($newVlBalance, 3) . ", SL: " . number_format($newSlBalance, 3) . "). " .
                    "Adjust the values and try again."
                );
            }

            $entry = LeaveLedgerEntry::create([
                'user_id' => $employee->id,
                'period_from' => $periodFrom,
                'period_to' => $periodTo,
                'remarks' => $remarks,
                'type' => $type,
                'vl_earned' => $vlEarned,
                'vl_used' => $vlUsed,
                'vl_balance' => $newVlBalance,
                'sl_earned' => $slEarned,
                'sl_used' => $slUsed,
                'sl_balance' => $newSlBalance,
                'leave_application_id' => $leaveApplicationId,
                'encoded_by' => $encodedBy ?? auth()->id(),
            ]);

            $balance->update(['vl_balance' => $newVlBalance, 'sl_balance' => $newSlBalance]);

            return $entry;
        });
    }
}