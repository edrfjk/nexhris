<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const UNIQUE = 'ledger_entries_one_accrual_per_period';

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

        $hasUnique = collect(Schema::getIndexes('leave_ledger_entries'))
            ->contains(fn (array $index) => $index['name'] === self::UNIQUE);

        if ($hasUnique) {
            Schema::table('leave_ledger_entries', function (Blueprint $table) {
                $table->dropUnique(self::UNIQUE);
            });
        }
    }

    public function down(): void
    {
        // The prior migration owns this constraint. It will recreate it when
        // rolling back after this migration has been reversed.
    }
};
