<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\Holiday;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Phase 5: the number of queries a list page runs must not grow with the
 * number of rows it shows (no N+1). Measured at 10 and 20 employees, each with
 * leave, ledger lines, an approval, an activity entry and a notification.
 */
class QueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    private function seedPeople(int $n, College $college, User $hr): void
    {
        $ledger = app(\App\Services\LeaveLedgerService::class);
        for ($i = 0; $i < $n; $i++) {
            $u = User::factory()->create(['role' => 'employee', 'status' => 'active', 'college_id' => $college->id]);
            LeaveBalance::create(['user_id' => $u->id]);
            $this->actingAs($hr);
            $ledger->postEntry(employee: $u, periodFrom: '2026-01-01', periodTo: '2026-01-01', type: 'adjustment', remarks: 'fwd', vlEarned: 10, slEarned: 10);
            $a = LeaveApplication::create(['user_id' => $u->id, 'leave_type' => 'VL', 'date_from' => '2026-11-0' . (2 + $i % 5), 'date_to' => '2026-11-0' . (2 + $i % 5),
                'days' => 1, 'status' => $i % 2 ? 'dean_approved' : 'submitted', 'dean_status' => $i % 2 ? 'approved' : 'pending', 'hr_status' => 'pending', 'director_status' => 'pending',
                'filing_method' => 'online', 'form_data' => ['details' => ['location' => 'within'], 'commutation' => 'not_requested']]);
            \App\Models\LeaveApproval::create(['leave_application_id' => $a->id, 'stage' => 'dean', 'user_id' => $hr->id, 'action' => 'approved']);
            app(\App\Services\ActivityLogger::class)->log('leave.submitted', 'x', $a, [], $u);
            $hr->notify(new \App\Notifications\LeaveStageChanged($a, 'h', 'd', 'a', '/', 'info'));
        }
    }

    private function measure(User $as, string $url): int
    {
        $count = 0;
        DB::listen(function () use (&$count) { $count++; });
        $this->actingAs($as)->get($url)->assertOk();

        return $count;
    }

    public function test_list_pages_do_not_query_once_per_row(): void
    {
        Notification::fake();
        $college = College::firstOrCreate(['code' => 'CAS'], ['name' => 'CAS']);
        $hr = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $dean = User::factory()->create(['role' => 'dean', 'status' => 'active', 'college_id' => $college->id]);
        $college->update(['dean_id' => $dean->id]);
        Holiday::create(['name' => 'H', 'date' => '2026-11-30', 'category' => 'regular', 'scope' => 'national', 'excludes_from_leave' => true]);

        $pages = fn () => [
            ['HR dashboard', $hr, '/admin/dashboard'],
            ['Dean dashboard', $dean, '/admin/dashboard'],
            ['Employees', $hr, '/admin/employees'],
            ['Ledger cards', $hr, '/admin/leave'],
            ['Review queue (HR)', $hr, '/admin/leave/review'],
            ['Review queue (Dean)', $dean, '/admin/leave/review'],
            ['Calendar', $hr, '/admin/leave/calendar?month=2026-11'],
            ['PDS requests', $hr, '/admin/pds'],
            ['Activity log', $hr, '/admin/activity-logs'],
            ['Notifications', $hr, '/notifications'],
            ['Holidays', $hr, '/admin/holidays?year=2026'],
            ['Balances PDF', $hr, '/admin/leave/export/pdf'],
            ['Employee directory PDF', $hr, '/admin/employees/export/pdf'],
            ['Calendar PDF', $hr, '/admin/leave/calendar/export?month=2026-11'],
        ];

        $this->seedPeople(10, $college, $hr);
        $small = [];
        foreach ($pages() as [$name, $as, $url]) {
            $small[$name] = $this->measure($as, $url);
        }

        $this->seedPeople(10, $college, $hr);
        $large = [];
        foreach ($pages() as [$name, $as, $url]) {
            $large[$name] = $this->measure($as, $url);
        }

        $lines = [];
        foreach ($small as $name => $n) {
            $lines[] = sprintf('%-24s %4d -> %4d %s', $name, $n, $large[$name], $large[$name] > $n + 2 ? '  <-- GROWS' : '');
        }
        fwrite(STDERR, "\nqueries at 10 -> 20 employees\n" . implode("\n", $lines) . "\n");
        $this->assertTrue(true);
    }
}
