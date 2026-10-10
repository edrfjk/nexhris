<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\College;
use App\Models\Holiday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HolidayManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hr = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Independence Day',
            'date' => '2026-06-12',
            'category' => 'regular',
            'scope' => 'national',
            'excludes_from_leave' => '1',
            'source' => 'Proclamation No. 000, s. 2025',
        ], $overrides);
    }

    public function test_hr_sees_the_calendar_for_a_year(): void
    {
        Holiday::create($this->payload() + []);
        Holiday::create($this->payload(['name' => 'Next year', 'date' => '2027-06-12']));

        $this->actingAs($this->hr)->get(route('admin.holidays.index', ['year' => 2026]))
            ->assertOk()
            ->assertSee('Independence Day')
            ->assertDontSee('Next year')
            ->assertSee('Not charged');
    }

    public function test_hr_adds_edits_and_removes_a_holiday(): void
    {
        $this->actingAs($this->hr)->post(route('admin.holidays.store'), $this->payload())
            ->assertRedirect(route('admin.holidays.index', ['year' => 2026]))->assertSessionHasNoErrors();

        $holiday = Holiday::sole();
        $this->assertTrue($holiday->excludes_from_leave);

        // An unticked box marks the day as charged.
        $this->put(route('admin.holidays.update', $holiday), $this->payload(['category' => 'special_working', 'excludes_from_leave' => null]))
            ->assertSessionHasNoErrors();
        $this->assertFalse($holiday->fresh()->excludes_from_leave);

        $this->delete(route('admin.holidays.destroy', $holiday))->assertRedirect();
        $this->assertSame(0, Holiday::count());

        $this->assertSame(3, ActivityLog::where('action', 'like', 'holiday.%')->count());
    }

    public function test_a_local_holiday_must_name_its_place(): void
    {
        $this->actingAs($this->hr)->post(route('admin.holidays.store'), $this->payload(['category' => 'special_local', 'scope' => 'local']))
            ->assertSessionHasErrors('locality');

        $this->post(route('admin.holidays.store'), $this->payload(['category' => 'special_local', 'scope' => 'local', 'locality' => 'Tagudin, Ilocos Sur']))
            ->assertSessionHasNoErrors();
    }

    public function test_the_same_holiday_cannot_be_entered_twice(): void
    {
        $this->actingAs($this->hr)->post(route('admin.holidays.store'), $this->payload())->assertSessionHasNoErrors();
        $this->post(route('admin.holidays.store'), $this->payload())->assertSessionHasErrors('name');

        $this->assertSame(1, Holiday::count());
    }

    public function test_invalid_input_is_refused(): void
    {
        $this->actingAs($this->hr)->post(route('admin.holidays.store'), $this->payload(['category' => 'made_up', 'date' => 'soon', 'name' => '']))
            ->assertSessionHasErrors(['category', 'date', 'name']);
    }

    public function test_only_hr_can_reach_the_calendar(): void
    {
        $college = College::firstOrCreate(['code' => 'CAS'], ['name' => 'College of Arts and Sciences']);
        $holiday = Holiday::create($this->payload());

        foreach (['employee', 'dean', 'campus_director'] as $role) {
            $user = User::factory()->create(['role' => $role, 'status' => 'active', 'college_id' => $college->id]);

            $this->actingAs($user)->get(route('admin.holidays.index'))->assertForbidden();
            $this->post(route('admin.holidays.store'), $this->payload(['name' => 'Sneaky']))->assertForbidden();
            $this->put(route('admin.holidays.update', $holiday), $this->payload(['name' => 'Changed']))->assertForbidden();
            $this->delete(route('admin.holidays.destroy', $holiday))->assertForbidden();
        }

        $this->assertSame('Independence Day', $holiday->fresh()->name);
        $this->assertSame(1, Holiday::count());
    }

    public function test_changing_the_calendar_leaves_filed_leave_alone(): void
    {
        $employee = User::factory()->create(['role' => 'employee', 'status' => 'active']);
        $filed = $employee->leaveApplications()->create([
            'leave_type' => 'VL', 'date_from' => '2026-06-08', 'date_to' => '2026-06-12',
            'days' => 5, 'status' => 'cd_approved',
        ]);

        $this->actingAs($this->hr)->post(route('admin.holidays.store'), $this->payload())->assertSessionHasNoErrors();

        $this->assertEquals(5, $filed->fresh()->days);
    }

    public function test_the_sidebar_links_hr_to_the_calendar(): void
    {
        $this->actingAs($this->hr)->get(route('admin.dashboard'))
            ->assertOk()->assertSee(route('admin.holidays.index'));
    }
}
