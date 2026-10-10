<?php

namespace Tests\Integration;

use App\Models\College;
use App\Models\LeaveApplication;
use App\Models\LeaveApproval;
use App\Models\LeaveBalance;
use App\Models\LeaveLedgerEntry;
use App\Models\User;

/**
 * Two real processes, two database connections, one leave form, on MariaDB.
 * The first contender holds its transaction open; the second arrives while
 * the row is locked. Exactly one may win.
 */
class ConcurrentDecisionTest extends MariaDbTestCase
{
    private User $employee;

    private User $hr;

    private User $hr2;

    private User $director;

    protected function setUp(): void
    {
        parent::setUp();

        $college = College::firstOrCreate(['code' => 'CAS'], ['name' => 'College of Arts and Sciences']);
        $this->employee = User::factory()->create(['role' => 'employee', 'status' => 'active', 'college_id' => $college->id]);
        $this->hr = User::factory()->create(['role' => 'admin', 'status' => 'active', 'name' => 'First HR']);
        $this->hr2 = User::factory()->create(['role' => 'admin', 'status' => 'active', 'name' => 'Second HR']);
        $this->director = User::factory()->create(['role' => 'campus_director', 'status' => 'active']);
        LeaveBalance::create(['user_id' => $this->employee->id, 'vl_balance' => 10, 'sl_balance' => 10]);
    }

    /** A form waiting on HR (the Dean has signed). */
    private function atHr(): LeaveApplication
    {
        return LeaveApplication::create([
            'user_id' => $this->employee->id, 'leave_type' => 'VL',
            'date_from' => '2026-10-19', 'date_to' => '2026-10-20', 'days' => 2,
            'status' => 'dean_approved', 'dean_status' => 'approved',
            'hr_status' => 'pending', 'director_status' => 'pending',
        ]);
    }

    public function test_two_hr_administrators_approving_at_once_record_one_approval(): void
    {
        $application = $this->atHr();

        [$first, $second] = $this->race([
            ['action' => 'approve', 'reviewer' => $this->hr->id, 'application' => $application->id, 'hold' => 2],
            ['action' => 'approve', 'reviewer' => $this->hr2->id, 'application' => $application->id, 'delay' => 0.5],
        ]);

        $this->assertSame('ok', $first['outcome'], json_encode($first));
        $this->assertSame('already_decided', $second['outcome'], json_encode($second));
        // The second really did wait on the lock rather than slip past it.
        $this->assertGreaterThan(1.0, $second['waited']);

        $application->refresh();
        $this->assertSame('hr_approved', $application->status);
        $this->assertSame($this->hr->id, $application->hr_id);
        $this->assertSame(1, LeaveApproval::where('leave_application_id', $application->id)->where('stage', 'hr')->count());
    }

    public function test_an_approval_and_a_return_at_once_cannot_both_succeed(): void
    {
        $application = $this->atHr();

        [$approve, $return] = $this->race([
            ['action' => 'approve', 'reviewer' => $this->hr->id, 'application' => $application->id, 'hold' => 2],
            ['action' => 'return', 'reviewer' => $this->hr2->id, 'application' => $application->id, 'delay' => 0.5],
        ]);

        $this->assertSame('ok', $approve['outcome'], json_encode($approve));
        $this->assertSame('already_decided', $return['outcome'], json_encode($return));

        $application->refresh();
        $this->assertSame('hr_approved', $application->status);
        $this->assertNull($application->hr_remarks);
        $this->assertSame(0, LeaveApproval::where('leave_application_id', $application->id)->where('action', 'returned')->count());
    }

    public function test_two_postings_at_once_deduct_once(): void
    {
        $application = LeaveApplication::create([
            'user_id' => $this->employee->id, 'leave_type' => 'VL',
            'date_from' => '2026-10-19', 'date_to' => '2026-10-20', 'days' => 2,
            'status' => 'cd_approved', 'dean_status' => 'approved', 'hr_status' => 'approved', 'director_status' => 'approved',
        ]);

        [$first, $second] = $this->race([
            ['action' => 'post', 'reviewer' => $this->hr->id, 'application' => $application->id, 'hold' => 2],
            ['action' => 'post', 'reviewer' => $this->hr2->id, 'application' => $application->id, 'delay' => 0.5],
        ]);

        $this->assertSame('ok', $first['outcome'], json_encode($first));
        $this->assertSame('refused', $second['outcome'], json_encode($second));
        $this->assertStringContainsString('already been posted', $second['message']);

        $this->assertSame(1, LeaveLedgerEntry::where('leave_application_id', $application->id)->count());
        $this->assertEquals(8, LeaveBalance::where('user_id', $this->employee->id)->value('vl_balance'));
        $this->assertSame('completed', $application->fresh()->status);
    }

    public function test_two_adjusted_postings_at_once_record_one_line_and_one_adjustment(): void
    {
        $application = LeaveApplication::create([
            'user_id' => $this->employee->id, 'leave_type' => 'VL',
            'date_from' => '2026-10-19', 'date_to' => '2026-10-20', 'days' => 2,
            'status' => 'cd_approved', 'dean_status' => 'approved', 'hr_status' => 'approved', 'director_status' => 'approved',
        ]);
        $adjusted = ['period_to' => '2026-10-19', 'days' => 1, 'vl_used' => 1, 'adjustment_reason' => 'Returned early', 'confirm_adjustment' => 1];

        [$first, $second] = $this->race([
            ['action' => 'post', 'reviewer' => $this->hr->id, 'application' => $application->id, 'hold' => 2, 'input' => $adjusted],
            ['action' => 'post', 'reviewer' => $this->hr2->id, 'application' => $application->id, 'delay' => 0.5, 'input' => $adjusted],
        ]);

        $this->assertSame('ok', $first['outcome'], json_encode($first));
        $this->assertSame('refused', $second['outcome'], json_encode($second));

        $this->assertSame(1, LeaveLedgerEntry::where('leave_application_id', $application->id)->count());
        $this->assertSame(1, \App\Models\LeavePostingAdjustment::where('leave_application_id', $application->id)->count());
        $this->assertEquals(9, LeaveBalance::where('user_id', $this->employee->id)->value('vl_balance'));

        // The approved form kept its figures.
        $application->refresh();
        $this->assertEquals(2, $application->days);
        $this->assertSame('2026-10-20', $application->date_to->toDateString());
    }
}
