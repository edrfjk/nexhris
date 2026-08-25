<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollegeManagementTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, array $overrides = []): User
    {
        return User::create(array_merge([
            'employee_number' => 'E' . fake()->unique()->numberBetween(1000, 9999),
            'name' => ucfirst($role) . ' User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret-password',
            'role' => $role,
            'status' => 'active',
        ], $overrides));
    }

    public function test_the_migration_seeds_the_configured_colleges(): void
    {
        foreach (array_keys(config('colleges')) as $code) {
            $this->assertDatabaseHas('colleges', ['code' => $code]);
        }
    }

    public function test_hr_creates_a_college(): void
    {
        $this->actingAs($this->user('admin'))
            ->post(route('admin.colleges.store'), [
                'code' => 'CCS',
                'name' => 'College of Computing Studies',
            ])->assertRedirect();

        $this->assertDatabaseHas('colleges', ['code' => 'CCS', 'is_active' => true]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'college.created']);
    }

    public function test_college_codes_are_unique(): void
    {
        $this->actingAs($this->user('admin'))
            ->post(route('admin.colleges.store'), ['code' => 'CAS', 'name' => 'Duplicate'])
            ->assertSessionHasErrors('code');
    }

    public function test_assigning_a_dean_moves_them_onto_that_college(): void
    {
        $hr = $this->user('admin');
        $dean = $this->user('dean');
        $cas = College::where('code', 'CAS')->firstOrFail();

        $this->actingAs($hr)->put(route('admin.colleges.update', $cas), [
            'code' => $cas->code,
            'name' => $cas->name,
            'dean_id' => $dean->id,
        ])->assertRedirect();

        $this->assertSame($dean->id, $cas->fresh()->dean_id);
        $this->assertSame($cas->id, $dean->fresh()->college_id);
    }

    public function test_a_dean_signs_for_only_one_college(): void
    {
        $hr = $this->user('admin');
        $dean = $this->user('dean');

        $cas = College::where('code', 'CAS')->firstOrFail();
        $cte = College::where('code', 'CTE')->firstOrFail();

        foreach ([$cas, $cte] as $college) {
            $this->actingAs($hr)->put(route('admin.colleges.update', $college), [
                'code' => $college->code,
                'name' => $college->name,
                'dean_id' => $dean->id,
            ]);
        }

        // The first college must have been released when the Dean moved.
        $this->assertNull($cas->fresh()->dean_id);
        $this->assertSame($dean->id, $cte->fresh()->dean_id);
        $this->assertSame($cte->id, $dean->fresh()->college_id);
    }

    public function test_a_college_with_employees_is_deactivated_rather_than_deleted(): void
    {
        $cas = College::where('code', 'CAS')->firstOrFail();
        $this->user('employee', ['college_id' => $cas->id]);

        $this->actingAs($this->user('admin'))
            ->delete(route('admin.colleges.destroy', $cas))
            ->assertRedirect();

        $this->assertDatabaseHas('colleges', ['id' => $cas->id, 'is_active' => false]);
    }

    public function test_an_empty_college_is_deleted(): void
    {
        $empty = College::create(['code' => 'TMP', 'name' => 'Temporary Office']);

        $this->actingAs($this->user('admin'))
            ->delete(route('admin.colleges.destroy', $empty))
            ->assertRedirect();

        $this->assertDatabaseMissing('colleges', ['id' => $empty->id]);
    }

    public function test_only_hr_manages_colleges(): void
    {
        $cas = College::where('code', 'CAS')->firstOrFail();

        foreach (['dean', 'campus_director'] as $role) {
            $actor = $this->user($role, ['college_id' => $cas->id]);

            $this->actingAs($actor)->get(route('admin.colleges.index'))->assertForbidden();
            $this->actingAs($actor)->post(route('admin.colleges.store'), [
                'code' => 'XYZ', 'name' => 'Nope',
            ])->assertForbidden();
        }

        $this->actingAs($this->user('employee'))
            ->get(route('admin.colleges.index'))
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // The Dean's data boundary, enforced server-side
    // ------------------------------------------------------------------

    public function test_dean_only_sees_their_own_colleges_employees_in_the_directory(): void
    {
        $cas = College::where('code', 'CAS')->firstOrFail();
        $cte = College::where('code', 'CTE')->firstOrFail();

        $dean = $this->user('dean', ['college_id' => $cas->id, 'name' => 'Dean Of CAS']);
        $mine = $this->user('employee', ['college_id' => $cas->id, 'name' => 'Alice In CAS']);
        $theirs = $this->user('employee', ['college_id' => $cte->id, 'name' => 'Bob In CTE']);

        $this->actingAs($dean)
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->assertSee('Alice In CAS')
            ->assertDontSee('Bob In CTE');
    }

    public function test_hr_and_campus_director_see_every_college(): void
    {
        $cas = College::where('code', 'CAS')->firstOrFail();
        $cte = College::where('code', 'CTE')->firstOrFail();

        $this->user('employee', ['college_id' => $cas->id, 'name' => 'Alice In CAS']);
        $this->user('employee', ['college_id' => $cte->id, 'name' => 'Bob In CTE']);

        foreach (['admin', 'campus_director'] as $role) {
            $this->actingAs($this->user($role))
                ->get(route('admin.employees.index'))
                ->assertOk()
                ->assertSee('Alice In CAS')
                ->assertSee('Bob In CTE');
        }
    }

    public function test_a_dean_with_no_college_covers_nobody(): void
    {
        $cas = College::where('code', 'CAS')->firstOrFail();
        $this->user('employee', ['college_id' => $cas->id, 'name' => 'Alice In CAS']);

        // Failing open here would leak the whole campus to an unassigned Dean.
        $orphanDean = $this->user('dean', ['college_id' => null]);

        $this->actingAs($orphanDean)
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->assertDontSee('Alice In CAS');
    }

    public function test_employees_can_be_filtered_by_college(): void
    {
        $cas = College::where('code', 'CAS')->firstOrFail();
        $cte = College::where('code', 'CTE')->firstOrFail();

        $this->user('employee', ['college_id' => $cas->id, 'name' => 'Alice In CAS']);
        $this->user('employee', ['college_id' => $cte->id, 'name' => 'Bob In CTE']);

        $this->actingAs($this->user('admin'))
            ->get(route('admin.employees.index', ['college' => $cas->id]))
            ->assertOk()
            ->assertSee('Alice In CAS')
            ->assertDontSee('Bob In CTE');
    }
}
