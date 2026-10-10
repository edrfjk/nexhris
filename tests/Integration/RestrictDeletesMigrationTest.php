<?php

namespace Tests\Integration;

use App\Models\LeaveApplication;
use App\Models\LeaveApproval;
use App\Models\LeaveBalance;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The migration awaiting approval, applied on MariaDB — the engine it would
 * run on in production.
 */
class RestrictDeletesMigrationTest extends MariaDbTestCase
{
    private const PATH = 'database/migrations-pending';

    protected function setUp(): void
    {
        if (! glob(__DIR__ . '/../../' . self::PATH . '/*restrict_deletes_on_official_records.php')) {
            $this->markTestSkipped('The pending RESTRICT migration is kept out of this checkout.');
        }

        parent::setUp();
    }

    private function rule(string $table, string $column): ?string
    {
        foreach (Schema::getForeignKeys($table) as $key) {
            if ($key['columns'] === [$column]) {
                return strtolower((string) $key['on_delete']);
            }
        }

        return null;
    }

    private function counts(): array
    {
        return collect(['users', 'leave_applications', 'leave_approvals', 'leave_balances', 'leave_ledger_entries'])
            ->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()])->all();
    }

    private function seedRecords(): array
    {
        $employee = User::factory()->create(['role' => 'employee', 'status' => 'active']);
        $reviewer = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        LeaveBalance::create(['user_id' => $employee->id, 'vl_balance' => 5, 'sl_balance' => 5]);
        $application = LeaveApplication::create([
            'user_id' => $employee->id, 'leave_type' => 'VL',
            'date_from' => '2026-10-19', 'date_to' => '2026-10-20', 'days' => 2, 'status' => 'hr_approved',
        ]);
        LeaveApproval::create(['leave_application_id' => $application->id, 'stage' => 'hr', 'user_id' => $reviewer->id, 'action' => 'approved']);

        return [$employee, $application];
    }

    public function test_it_changes_the_rules_in_place_and_keeps_every_row(): void
    {
        [$employee, $application] = $this->seedRecords();
        $before = $this->counts();

        Artisan::call('migrate', ['--path' => self::PATH, '--force' => true]);

        $this->assertSame($before, $this->counts(), 'No row may be lost or added by the migration.');

        foreach ([
            ['leave_applications', 'user_id'], ['leave_ledger_entries', 'user_id'], ['leave_balances', 'user_id'],
            ['leave_approvals', 'leave_application_id'], ['service_records', 'user_id'],
            ['employee_ledgers', 'user_id'], ['ledger_changes', 'employee_ledger_id'],
            ['pds_submissions', 'user_id'], ['pds_submission_revisions', 'pds_submission_id'],
            ['pds_personal_information', 'user_id'], ['pds_family_backgrounds', 'user_id'], ['pds_children', 'user_id'],
            ['pds_educational_backgrounds', 'user_id'], ['pds_civil_service_eligibilities', 'user_id'],
            ['pds_work_experiences', 'user_id'], ['pds_voluntary_works', 'user_id'], ['pds_trainings', 'user_id'],
            ['pds_other_information', 'user_id'], ['pds_questionnaires', 'user_id'], ['pds_references', 'user_id'],
            ['pds_declarations', 'user_id'],
        ] as [$table, $column]) {
            $this->assertSame('restrict', $this->rule($table, $column), "{$table}.{$column}");
        }

        // Rules the migration does not own are left alone.
        $this->assertSame('set null', $this->rule('leave_ledger_entries', 'leave_application_id'));
        $this->assertSame('set null', $this->rule('activity_logs', 'user_id'));
    }

    public function test_afterwards_the_database_refuses_raw_deletes_of_official_records(): void
    {
        [$employee, $application] = $this->seedRecords();
        Artisan::call('migrate', ['--path' => self::PATH, '--force' => true]);

        foreach ([
            fn () => DB::table('users')->where('id', $employee->id)->delete(),
            fn () => DB::table('leave_applications')->where('id', $application->id)->delete(),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('A raw delete of an official record must be refused by the database.');
            } catch (QueryException $e) {
                $this->assertSame('23000', $e->getCode());
            }
        }

        $this->assertNotNull(DB::table('users')->find($employee->id));
        $this->assertSame(1, DB::table('leave_approvals')->count());
    }

    public function test_a_raw_delete_of_a_ledger_line_keeps_its_adjustment_history(): void
    {
        [$employee, $application] = $this->seedRecords();
        Artisan::call('migrate', ['--path' => self::PATH, '--force' => true]);
        $hr = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $entryId = DB::table('leave_ledger_entries')->insertGetId([
            'user_id' => $employee->id, 'period_from' => '2026-10-19', 'period_to' => '2026-10-19',
            'type' => 'leave_deduction', 'ledger' => 'leave', 'year_label' => '2026',
            'vl_earned' => 0, 'vl_used' => 1, 'vl_used_wop' => 0, 'vl_balance' => 4,
            'sl_earned' => 0, 'sl_used' => 0, 'sl_used_wop' => 0, 'sl_balance' => 5,
            'service_earned' => 0, 'service_used' => 0, 'service_balance' => 0,
            'leave_application_id' => $application->id, 'encoded_by' => $hr->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $adjustmentId = DB::table('leave_posting_adjustments')->insertGetId([
            'leave_application_id' => $application->id, 'leave_ledger_entry_id' => $entryId,
            'original_date_from' => '2026-10-19', 'original_date_to' => '2026-10-20', 'original_days' => 2,
            'original_charges' => json_encode(['vl_used' => 2]), 'posted_date_from' => '2026-10-19',
            'posted_date_to' => '2026-10-19', 'posted_days' => 1, 'posted_charges' => json_encode(['vl_used' => 1]),
            'differences' => json_encode([['field' => 'days', 'approved' => 2, 'posted' => 1]]),
            'reason' => 'Returned early', 'adjusted_by' => $hr->id, 'created_at' => now(),
        ]);

        DB::table('leave_ledger_entries')->where('id', $entryId)->delete();

        $row = DB::table('leave_posting_adjustments')->find($adjustmentId);
        $this->assertNotNull($row, 'The adjustment must survive the removal of its ledger line.');
        $this->assertNull($row->leave_ledger_entry_id);
        $this->assertSame('Returned early', $row->reason);

        // And the adjustment protects its leave form and its HR author.
        foreach ([fn () => DB::table('leave_applications')->where('id', $application->id)->delete(),
                  fn () => DB::table('users')->where('id', $hr->id)->delete()] as $attempt) {
            try {
                $attempt();
                $this->fail('An adjustment\'s leave form and author must not be deletable.');
            } catch (QueryException $e) {
                $this->assertSame('23000', $e->getCode());
            }
        }
    }

    public function test_an_account_with_no_records_can_still_be_deleted(): void
    {
        Artisan::call('migrate', ['--path' => self::PATH, '--force' => true]);
        $spare = User::factory()->create(['role' => 'employee']);

        DB::table('users')->where('id', $spare->id)->delete();

        $this->assertNull(DB::table('users')->find($spare->id));
    }

    public function test_it_rolls_back_to_cascade_without_losing_rows(): void
    {
        $this->seedRecords();
        Artisan::call('migrate', ['--path' => self::PATH, '--force' => true]);
        $before = $this->counts();

        Artisan::call('migrate:rollback', ['--path' => self::PATH, '--force' => true]);

        $this->assertSame('cascade', $this->rule('leave_applications', 'user_id'));
        $this->assertSame($before, $this->counts());
    }
}
