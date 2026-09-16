<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The indexes every list screen depends on, and the one constraint that keeps
 * the ledger honest.
 *
 * None of this shows at three employees. At three hundred, on shared hosting,
 * every filtered list is a full table scan — and the leave ledger, which is the
 * system of record for credits accrued across whole careers, had nothing
 * stopping the same month being credited twice.
 */
return new class extends Migration
{
    /**
     * Columns the app filters or sorts on, per table.
     *
     * Taken from the queries the list screens actually run: role and status on
     * every people listing, the leave status and date range on the calendar and
     * the review queue, period_from wherever a ledger card is replayed in order.
     */
    private const INDEXES = [
        'users' => ['role', 'status'],
        'leave_applications' => ['status', 'date_from', 'date_to'],
        'leave_ledger_entries' => ['period_from'],
        'pds_submissions' => ['status'],
        'notifications' => ['read_at'],
    ];

    private const UNIQUE = 'ledger_entries_one_accrual_per_period';

    public function up(): void
    {
        foreach (self::INDEXES as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column) || $this->indexed($table, $column)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($column) {
                    $blueprint->index($column);
                });
            }
        }

        if (! Schema::hasTable('leave_ledger_entries')) {
            return;
        }

        // Only monthly accruals are once-per-period. An employee may take leave
        // twice in the same month and each absence is its own line on the card,
        // so a constraint across every row would refuse ordinary work.
        //
        // The column is null for anything that is not an accrual, and both
        // MySQL and SQLite treat nulls in a unique index as distinct — so the
        // constraint binds exactly the rows it should and ignores the rest.
        if (! Schema::hasColumn('leave_ledger_entries', 'accrual_period')) {
            Schema::table('leave_ledger_entries', function (Blueprint $table) {
                $table->string('accrual_period', 40)
                    ->nullable()
                    ->after('type')
                    ->comment('Set only on accruals, so one period cannot be credited twice');
            });
        }

        $this->backfillAccrualPeriods();
        $this->removeDuplicateAccruals();

        if (! $this->uniqueExists()) {
            Schema::table('leave_ledger_entries', function (Blueprint $table) {
                $table->unique(['user_id', 'ledger', 'accrual_period'], self::UNIQUE);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leave_ledger_entries')) {
            if ($this->uniqueExists()) {
                Schema::table('leave_ledger_entries', function (Blueprint $table) {
                    $table->dropUnique(self::UNIQUE);
                });
            }

            if (Schema::hasColumn('leave_ledger_entries', 'accrual_period')) {
                Schema::table('leave_ledger_entries', function (Blueprint $table) {
                    $table->dropColumn('accrual_period');
                });
            }
        }

        foreach (self::INDEXES as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($table, $column) {
                    $blueprint->dropIndex($table . '_' . $column . '_index');
                });
            }
        }
    }

    /** Stamps existing accruals with the period they credit. */
    private function backfillAccrualPeriods(): void
    {
        DB::table('leave_ledger_entries')
            ->where('type', 'earned')
            ->whereNull('accrual_period')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('leave_ledger_entries')
                        ->where('id', $row->id)
                        ->update([
                            'accrual_period' => substr((string) $row->period_from, 0, 10)
                                . ':' . substr((string) $row->period_to, 0, 10),
                        ]);
                }
            });
    }

    /**
     * Keeps the earliest of each duplicated accrual.
     *
     * The constraint cannot be added while duplicates exist, and the earliest
     * row is the one the balance was originally built from.
     */
    private function removeDuplicateAccruals(): void
    {
        $groups = DB::table('leave_ledger_entries')
            ->select('user_id', 'ledger', 'accrual_period', DB::raw('MIN(id) as keep'))
            ->whereNotNull('accrual_period')
            ->groupBy('user_id', 'ledger', 'accrual_period')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            DB::table('leave_ledger_entries')
                ->where('user_id', $group->user_id)
                ->where('ledger', $group->ledger)
                ->where('accrual_period', $group->accrual_period)
                ->where('id', '!=', $group->keep)
                ->delete();
        }
    }

    private function indexed(string $table, string $column): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index) => $index['columns'] === [$column]);
    }

    private function uniqueExists(): bool
    {
        return collect(Schema::getIndexes('leave_ledger_entries'))
            ->contains(fn (array $index) => $index['name'] === self::UNIQUE);
    }
};
