<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('leave_balances', function (Blueprint $table) {
            $table->decimal('vl_balance', 8, 2)->default(0)->change();
            $table->decimal('sl_balance', 8, 2)->default(0)->change();
            $table->decimal('service_balance', 8, 2)->default(0)->after('sl_balance');
        });

        Schema::table('leave_ledger_entries', function (Blueprint $table) {
            foreach (['vl_earned', 'vl_used', 'vl_balance', 'sl_earned', 'sl_used', 'sl_balance'] as $column) {
                $table->decimal($column, 8, 2)->change();
            }
            $table->decimal('service_earned', 8, 2)->default(0)->after('sl_balance');
            $table->decimal('service_used', 8, 2)->default(0)->after('service_earned');
            $table->decimal('service_balance', 8, 2)->default(0)->after('service_used');
        });
    }

    public function down(): void
    {
        Schema::table('leave_ledger_entries', function (Blueprint $table) {
            $table->dropColumn(['service_earned', 'service_used', 'service_balance']);
        });
        Schema::table('leave_balances', function (Blueprint $table) {
            $table->dropColumn('service_balance');
        });
    }
};
