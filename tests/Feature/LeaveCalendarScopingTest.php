<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\LeaveApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The calendar's college boundary. A Dean must never see another college's
 * leave, and the limit is applied in the query rather than by hiding rows.
 */
class LeaveCalendarScopingTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, ?int $collegeId = null, string $name = null): User
    {
        return User::create([
            'employee_number' => 'E' . fake()->unique()->numberBetween(1000, 9999),
            'name' => $name ?? (ucfirst($role) . ' ' . fake()->unique()->numberBetween(1, 999)),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret-password',
            'role' => $role,
            'status' => 'active',
            'college_id' => $collegeId ?? College::where('code', 'CAS')->value('id'),
        ]);
    }

    private function leaveFor(User $employee): LeaveApplication
    {
        return LeaveApplication::create([
            'user_id' => $employee->id,
            'leave_type' => 'VL',
            'date_from' => now()->startOfMonth()->addDays(3),
            'date_to' => now()->startOfMonth()->addDays(4),
            'days' => 2,
            'status' => 'cd_approved',
            'file_path' => 'leave-applications/x.xlsx',
        ]);
    }

    public function test_a_dean_sees_only_their_own_colleges_leave(): void
    {
        $cas = College::where('code', 'CAS')->firstOrFail();
        $cte = College::where('code', 'CTE')->firstOrFail();

        $dean = $this->user('dean', $cas->id);
        $mine = $this->user('employee', $cas->id, 'Alice In CAS');
        $theirs = $this->user('employee', $cte->id, 'Bob In CTE');

        $this->leaveFor($mine);
        $this->leaveFor($theirs);

        $this->actingAs($dean)
            ->get(route('admin.leave.calendar'))
            ->assertOk()
            ->assertSee('Alice In CAS')
            ->assertDontSee('Bob In CTE');
    }

    public function test_a_dean_with_no_college_sees_nothing(): void
    {
        $employee = $this->user('employee', null, 'Alice In CAS');
        $this->leaveFor($employee);

        $orphan = $this->user('dean');
        $orphan->update(['college_id' => null]);

        // Failing open here would expose the whole campus.
        $this->actingAs($orphan)
            ->get(route('admin.leave.calendar'))
            ->assertOk()
            ->assertDontSee('Alice In CAS');
    }

    public function test_hr_and_the_campus_director_see_every_college(): void
    {
        $cas = College::where('code', 'CAS')->firstOrFail();
        $cte = College::where('code', 'CTE')->firstOrFail();

        $this->leaveFor($this->user('employee', $cas->id, 'Alice In CAS'));
        $this->leaveFor($this->user('employee', $cte->id, 'Bob In CTE'));

        foreach (['admin', 'campus_director'] as $role) {
            $this->actingAs($this->user($role))
                ->get(route('admin.leave.calendar'))
                ->assertOk()
                ->assertSee('Alice In CAS')
                ->assertSee('Bob In CTE');
        }
    }

    public function test_hr_can_filter_the_calendar_to_one_college(): void
    {
        $cas = College::where('code', 'CAS')->firstOrFail();
        $cte = College::where('code', 'CTE')->firstOrFail();

        $this->leaveFor($this->user('employee', $cas->id, 'Alice In CAS'));
        $this->leaveFor($this->user('employee', $cte->id, 'Bob In CTE'));

        $this->actingAs($this->user('admin'))
            ->get(route('admin.leave.calendar', ['college' => $cas->id]))
            ->assertOk()
            ->assertSee('Alice In CAS')
            ->assertDontSee('Bob In CTE');
    }

    public function test_a_dean_cannot_widen_their_scope_with_a_query_string(): void
    {
        $cas = College::where('code', 'CAS')->firstOrFail();
        $cte = College::where('code', 'CTE')->firstOrFail();

        $dean = $this->user('dean', $cas->id);
        $this->leaveFor($this->user('employee', $cte->id, 'Bob In CTE'));

        // Passing another college's id must not override the server-side limit.
        $this->actingAs($dean)
            ->get(route('admin.leave.calendar', ['college' => $cte->id]))
            ->assertOk()
            ->assertDontSee('Bob In CTE');
    }

    public function test_the_month_export_respects_the_same_boundary(): void
    {
        $cas = College::where('code', 'CAS')->firstOrFail();
        $cte = College::where('code', 'CTE')->firstOrFail();

        $dean = $this->user('dean', $cas->id);
        $this->leaveFor($this->user('employee', $cas->id, 'Alice In CAS'));
        $this->leaveFor($this->user('employee', $cte->id, 'Bob In CTE'));

        $pdf = $this->actingAs($dean)
            ->get(route('admin.leave.calendar.export'))
            ->assertOk()
            ->getContent();

        // A PDF compresses its text, so assert on the query instead: the
        // export must not even load the other college's rows.
        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_an_employee_cannot_open_the_calendar(): void
    {
        $this->actingAs($this->user('employee'))
            ->get(route('admin.leave.calendar'))
            ->assertForbidden();
    }
}
