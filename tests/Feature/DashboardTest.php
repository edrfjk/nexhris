<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\College;
use App\Models\HrPolicy;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\PdsSubmission;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, ?int $collegeId = null, array $overrides = []): User
    {
        return User::create(array_merge([
            'employee_number' => 'E' . fake()->unique()->numberBetween(1000, 9999),
            'name' => ucfirst(str_replace('_', ' ', $role)) . ' ' . fake()->unique()->numberBetween(1, 999),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret-password',
            'role' => $role,
            'status' => 'active',
            'position' => 'Instructor I',
            'college_id' => $collegeId ?? College::where('code', 'CAS')->value('id'),
        ], $overrides));
    }

    private function leave(User $employee, string $status = 'cd_approved', int $daysAgo = 5): LeaveApplication
    {
        return LeaveApplication::create([
            'user_id' => $employee->id,
            'leave_type' => 'VL',
            'date_from' => now()->subDays($daysAgo),
            'date_to' => now()->subDays($daysAgo - 1),
            'days' => 2,
            'status' => $status,
            'file_path' => 'leave-applications/x.xlsx',
        ]);
    }

    private function service(): DashboardService
    {
        return app(DashboardService::class);
    }

    // ------------------------------------------------------------------
    // Each role gets its own dashboard
    // ------------------------------------------------------------------

    public function test_hr_sees_the_command_centre(): void
    {
        $hr = $this->user('admin');
        $employee = $this->user('employee');
        $this->leave($employee);

        $this->actingAs($hr)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Where forms are waiting')
            ->assertSee('Breakdown by college')
            ->assertSee('Recent activity');
    }

    public function test_a_dean_sees_the_dean_dashboard(): void
    {
        $dean = $this->user('dean');

        $this->actingAs($dean)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('pending your approval')
            ->assertSee('College headcount')
            // HR-only panels must not appear.
            ->assertDontSee('Breakdown by college')
            ->assertDontSee('Recent activity');
    }

    public function test_the_campus_director_sees_the_director_dashboard(): void
    {
        $director = $this->user('campus_director');

        $this->actingAs($director)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('awaiting your final approval')
            ->assertSee('Pending approvals by college');
    }

    public function test_an_employee_sees_their_own_dashboard(): void
    {
        $employee = $this->user('employee');
        LeaveBalance::create(['user_id' => $employee->id, 'vl_balance' => 12, 'sl_balance' => 8]);

        $this->actingAs($employee)
            ->get(route('employee.dashboard'))
            ->assertOk()
            ->assertSee('My leave credits')
            ->assertSee('12.00')
            ->assertSee('My records');
    }

    public function test_an_employee_cannot_open_the_admin_dashboard(): void
    {
        $this->actingAs($this->user('employee'))
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // Scoping
    // ------------------------------------------------------------------

    public function test_a_deans_figures_cover_only_their_own_college(): void
    {
        $cas = College::where('code', 'CAS')->firstOrFail();
        $cte = College::where('code', 'CTE')->firstOrFail();

        $dean = $this->user('dean', $cas->id);
        $mine = $this->user('employee', $cas->id, ['name' => 'Alice In CAS']);
        $theirs = $this->user('employee', $cte->id, ['name' => 'Bob In CTE']);

        // Both are on leave today.
        LeaveApplication::create([
            'user_id' => $mine->id, 'leave_type' => 'VL',
            'date_from' => today(), 'date_to' => today(), 'days' => 1,
            'status' => 'cd_approved', 'file_path' => 'x.xlsx',
        ]);
        LeaveApplication::create([
            'user_id' => $theirs->id, 'leave_type' => 'VL',
            'date_from' => today(), 'date_to' => today(), 'days' => 1,
            'status' => 'cd_approved', 'file_path' => 'x.xlsx',
        ]);

        $data = $this->service()->forDean($dean);

        // Headcount counts the Dean plus their own employee, never the other college.
        $this->assertSame(1, $data['onLeaveToday']->count());
        $this->assertSame('Alice In CAS', $data['onLeaveToday']->first()->user->name);

        $this->actingAs($dean)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Alice In CAS')
            ->assertDontSee('Bob In CTE');
    }

    public function test_a_dean_with_no_college_sees_nothing(): void
    {
        $employee = $this->user('employee', null, ['name' => 'Alice In CAS']);
        LeaveApplication::create([
            'user_id' => $employee->id, 'leave_type' => 'VL',
            'date_from' => today(), 'date_to' => today(), 'days' => 1,
            'status' => 'cd_approved', 'file_path' => 'x.xlsx',
        ]);

        $orphan = $this->user('dean');
        $orphan->update(['college_id' => null]);

        $data = $this->service()->forDean($orphan->fresh());

        $this->assertSame(0, $data['onLeaveToday']->count());
        $this->assertSame(0, $data['pending']);
    }

    public function test_a_reviewers_own_application_is_left_out_of_their_pending_count(): void
    {
        $dean = $this->user('dean');

        // A Dean's own form skips the Dean stage, but guard the count anyway.
        LeaveApplication::create([
            'user_id' => $dean->id, 'leave_type' => 'VL',
            'date_from' => now()->addWeek(), 'date_to' => now()->addWeek(), 'days' => 1,
            'status' => 'submitted', 'file_path' => 'x.xlsx',
        ]);

        $this->assertSame(0, $this->service()->forDean($dean)['pending']);
    }

    // ------------------------------------------------------------------
    // Figures
    // ------------------------------------------------------------------

    public function test_the_bottleneck_counts_forms_per_stage(): void
    {
        $hr = $this->user('admin');

        $this->leave($this->user('employee'), 'submitted');
        $this->leave($this->user('employee'), 'submitted');
        $this->leave($this->user('employee'), 'hr_approved');

        $bottleneck = collect($this->service()->forHr($hr)['bottleneck'])->keyBy('stage');

        $this->assertSame(2, $bottleneck['dean']['count']);
        $this->assertSame(0, $bottleneck['hr']['count']);
        $this->assertSame(1, $bottleneck['campus_director']['count']);
    }

    public function test_compliance_reflects_submitted_pds(): void
    {
        $hr = $this->user('admin');
        $a = $this->user('employee');
        $this->user('employee');

        PdsSubmission::create([
            'user_id' => $a->id, 'applicable_year' => now()->year, 'status' => 'approved', 'version' => 1,
        ]);

        $compliance = $this->service()->forHr($hr)['compliance'];

        // The denominator is the people who actually file a PDS — employees,
        // Deans and the Campus Director. The HR account itself is not one of
        // them, so two staff exist here and one has submitted.
        $this->assertSame(2, $compliance['total']);
        $this->assertSame(1, $compliance['submitted']);
        $this->assertSame(50, $compliance['percent']);
    }

    public function test_the_leave_type_split_counts_only_approved_leave(): void
    {
        $hr = $this->user('admin');
        $employee = $this->user('employee');

        $this->leave($employee, 'cd_approved');
        // Still in review, so it must not be counted as taken.
        $this->leave($employee, 'submitted');

        $split = $this->service()->forHr($hr)['leaveTypes'];

        $this->assertSame(1, $split['counts'][0]);
        $this->assertSame(0, $split['counts'][1]);
    }

    public function test_the_trend_covers_six_months(): void
    {
        $hr = $this->user('admin');
        $this->leave($this->user('employee'));

        $trend = $this->service()->forHr($hr)['trend'];

        $this->assertCount(6, $trend['labels']);
        $this->assertCount(6, $trend['days']);
        $this->assertSame(now()->format('M Y'), end($trend['labels']));
    }

    public function test_the_employee_dashboard_shows_unread_policies_and_announcements(): void
    {
        $hr = $this->user('admin');
        $employee = $this->user('employee');

        HrPolicy::create([
            'title' => 'Code of Conduct', 'content' => 'x', 'type' => 'text',
            'is_published' => true, 'created_by' => $hr->id,
        ]);

        Announcement::create([
            'title' => 'Campus Notice', 'body' => 'x',
            'is_published' => true, 'published_at' => now(), 'posted_by' => $hr->id,
        ]);

        $data = $this->service()->forEmployee($employee);

        $this->assertSame(1, $data['policiesUnread']);
        $this->assertSame(1, $data['announcements']->count());

        $this->actingAs($employee)
            ->get(route('employee.dashboard'))
            ->assertOk()
            ->assertSee('Campus Notice')
            ->assertSee('1 to read');
    }

    public function test_the_employee_dashboard_shows_a_stepper_for_leave_in_progress(): void
    {
        $employee = $this->user('employee');
        $this->leave($employee, 'dean_approved');

        $this->actingAs($employee)
            ->get(route('employee.dashboard'))
            ->assertOk()
            ->assertSee('My leave application')
            ->assertSee('Awaiting HR review');
    }

    public function test_empty_dashboards_offer_guidance_rather_than_blank_panels(): void
    {
        $hr = $this->user('admin');

        $this->actingAs($hr)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee("You're all caught up");
    }
}
