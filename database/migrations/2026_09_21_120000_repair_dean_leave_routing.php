<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Align legacy appointed-Dean accounts and move their untouched pending
     * applications to HR. No approval record is fabricated because the Dean
     * stage does not apply to a Dean's own leave.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('colleges')) {
            return;
        }

        $appointedDeanIds = DB::table('colleges')
            ->whereNotNull('dean_id')
            ->pluck('dean_id');

        if ($appointedDeanIds->isNotEmpty()) {
            DB::table('users')
                ->whereIn('id', $appointedDeanIds)
                ->where('role', '!=', 'dean')
                ->update(['role' => 'dean', 'updated_at' => now()]);
        }

        if (! Schema::hasTable('leave_applications')) {
            return;
        }

        $deanIds = $appointedDeanIds
            ->merge(DB::table('users')->where('role', 'dean')->pluck('id'))
            ->unique()
            ->values();

        if ($deanIds->isEmpty()) {
            return;
        }

        DB::table('leave_applications')
            ->whereIn('user_id', $deanIds)
            ->where('status', 'submitted')
            ->where('dean_status', 'pending')
            ->update(['status' => 'dean_approved', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Do not demote repaired accounts or route their leave backwards.
    }
};
