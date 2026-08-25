<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The campus keeps two cards, not one.
 *
 * A leave ledger line is charged against the vacation or sick balance. A
 * service credit line is charged against service credits — even when the day
 * taken was sick or vacation leave. HR decides which card an approved leave
 * is written on, and a line written on one must never appear on the other.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('leave_ledger_entries', 'ledger')) {
            return;
        }

        Schema::table('leave_ledger_entries', function (Blueprint $table) {
            $table->string('ledger', 10)->default('leave')->after('type')->index();
        });

        // Everything already recorded that moved service credits belongs on
        // the service card; the rest is leave.
        \Illuminate\Support\Facades\DB::table('leave_ledger_entries')
            ->where(function ($q) {
                $q->where('service_earned', '>', 0)->orWhere('service_used', '>', 0);
            })
            ->update(['ledger' => 'service']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('leave_ledger_entries', 'ledger')) {
            Schema::table('leave_ledger_entries', function (Blueprint $table) {
                $table->dropColumn('ledger');
            });
        }
    }
};
