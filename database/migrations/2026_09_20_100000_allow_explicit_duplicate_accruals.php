<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const UNIQUE = 'ledger_entries_one_accrual_per_period';
    private const USER_INDEX = 'leave_ledger_entries_user_id_index';

    /**
     * Duplicate accruals are allowed only when HR explicitly confirms the
     * warning in the posting form. The controller still blocks ordinary
     * accidental duplicate submissions.
     */
    public function up(): void
    {
        if (! Schema::hasTable('leave_ledger_entries')) {
            return;
        }

        $indexes = collect(Schema::getIndexes('leave_ledger_entries'));

        $hasUnique = $indexes
            ->contains(fn (array $index) => $index['name'] === self::UNIQUE);

        $hasUserIndex = $indexes
            ->contains(fn (array $index) => $index['name'] === self::USER_INDEX);

        // MariaDB requires an index for the user_id foreign key.
        // The unique accrual index currently provides that index,
        // so create a dedicated user_id index before removing it.
        if ($hasUnique && ! $hasUserIndex) {
            Schema::table('leave_ledger_entries', function (Blueprint $table) {
                $table->index('user_id', self::USER_INDEX);
            });
        }

        if ($hasUnique) {
            Schema::table('leave_ledger_entries', function (Blueprint $table) {
                $table->dropUnique(self::UNIQUE);
            });
        }
    }

    public function down(): void
    {
        // The prior migration owns the unique constraint.
    }
};