<?php

namespace Tests\Feature;

use App\Models\LeaveApplication;
use App\Models\LeaveApproval;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The migration awaiting approval in database/migrations-pending, on the
 * SQLite test database. Its real effect — RESTRICT in place, rows untouched —
 * is tested on MariaDB in tests/Integration/RestrictDeletesMigrationTest.
 */
class PendingRestrictDeletesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function deleteRule(string $table, string $column): ?string
    {
        foreach (Schema::getForeignKeys($table) as $key) {
            if ($key['columns'] === [$column]) {
                return strtolower((string) $key['on_delete']);
            }
        }

        return null;
    }

    public function test_it_is_not_part_of_the_normal_migration_run(): void
    {
        $this->assertSame('cascade', $this->deleteRule('leave_applications', 'user_id'));
        $this->assertSame('cascade', $this->deleteRule('leave_approvals', 'leave_application_id'));
        $this->assertFalse(DB::table('migrations')->where('migration', 'like', '%restrict_deletes_on_official_records%')->exists());
    }

    public function test_it_refuses_to_run_on_sqlite_and_loses_nothing(): void
    {
        if (! glob(base_path('database/migrations-pending/*restrict_deletes_on_official_records.php'))) {
            $this->markTestSkipped('The pending RESTRICT migration is kept out of this checkout.');
        }

        // On SQLite the table rebuild fired the cascade and deleted approval
        // history, so the migration must not run here at all.
        $employee = User::factory()->create(['role' => 'employee']);
        $reviewer = User::factory()->create(['role' => 'admin']);
        $application = LeaveApplication::create([
            'user_id' => $employee->id, 'leave_type' => 'VL',
            'date_from' => '2026-10-19', 'date_to' => '2026-10-20', 'days' => 2, 'status' => 'hr_approved',
        ]);
        LeaveApproval::create(['leave_application_id' => $application->id, 'stage' => 'hr', 'user_id' => $reviewer->id, 'action' => 'approved']);

        try {
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--path' => 'database/migrations-pending']);
            $this->fail('The migration must refuse to run on SQLite.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('only runs on MySQL/MariaDB', $e->getMessage());
        }

        $this->assertSame(1, LeaveApproval::count());
        $this->assertSame('cascade', $this->deleteRule('leave_applications', 'user_id'));
    }
}
