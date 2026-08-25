<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    private function hr(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    private function department(string $collegeCode, string $name): Department
    {
        $college = College::where('code', $collegeCode)->firstOrFail();

        // Initials, so "Bachelor of Science in Social Work" and "...in
        // Information Technology" do not collide on the same code.
        $code = collect(explode(' ', $name))
            ->reject(fn ($word) => in_array(strtolower($word), ['of', 'in', 'and', 'the']))
            ->map(fn ($word) => strtoupper($word[0]))
            ->implode('');

        return Department::firstOrCreate(
            ['college_id' => $college->id, 'code' => $code],
            ['name' => $name],
        );
    }

    public function test_hr_creates_an_employee_with_a_college_and_department(): void
    {
        $admin = $this->hr();
        $cas = College::where('code', 'CAS')->firstOrFail();
        $bsit = $this->department('CAS', 'Bachelor of Science in Information Technology');

        $this->actingAs($admin)->post(route('admin.employees.store'), [
            'employee_number' => 'EMP-1001',
            'name' => 'Test Employee',
            'email' => 'employee@example.test',
            'position' => 'Instructor',
            'college_id' => $cas->id,
            'department_id' => $bsit->id,
            'role' => 'employee',
            'contact_number' => '09123456789',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('admin.employees.index'));

        // The legacy `department` and `program` strings are derived from the
        // real records, because the ledger card and approval sheet print them.
        $this->assertDatabaseHas('users', [
            'employee_number' => 'EMP-1001',
            'college_id' => $cas->id,
            'department_id' => $bsit->id,
            'department' => 'CAS',
            'program' => 'Bachelor of Science in Information Technology',
        ]);
    }

    public function test_moving_an_employee_carries_both_legacy_strings_across(): void
    {
        $admin = $this->hr();
        $cas = College::where('code', 'CAS')->firstOrFail();
        $cte = College::where('code', 'CTE')->firstOrFail();

        $bsit = $this->department('CAS', 'Bachelor of Science in Information Technology');
        $bee = $this->department('CTE', 'Bachelor of Elementary Education');

        $this->actingAs($admin)->post(route('admin.employees.store'), [
            'employee_number' => 'EMP-1002',
            'name' => 'Test Employee',
            'email' => 'mover@example.test',
            'college_id' => $cas->id,
            'department_id' => $bsit->id,
            'role' => 'employee',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $employee = User::where('employee_number', 'EMP-1002')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.employees.update', $employee), [
            'employee_number' => 'EMP-1002',
            'name' => 'Test Employee',
            'email' => 'mover@example.test',
            'college_id' => $cte->id,
            'department_id' => $bee->id,
            'role' => 'employee',
        ])->assertRedirect(route('admin.employees.show', $employee));

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'college_id' => $cte->id,
            'department_id' => $bee->id,
            'department' => 'CTE',
            'program' => 'Bachelor of Elementary Education',
        ]);
    }

    public function test_a_department_from_another_college_is_rejected(): void
    {
        $admin = $this->hr();
        $cas = College::where('code', 'CAS')->firstOrFail();
        $otherCollegeDepartment = $this->department('CTE', 'Bachelor of Elementary Education');

        // Filing someone under another college's department would put them in
        // the wrong Dean's reporting line, so it must not be accepted.
        $this->actingAs($admin)->post(route('admin.employees.store'), [
            'employee_number' => 'EMP-1003',
            'name' => 'Mismatch',
            'email' => 'mismatch@example.test',
            'college_id' => $cas->id,
            'department_id' => $otherCollegeDepartment->id,
            'role' => 'employee',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('department_id');

        $this->assertDatabaseMissing('users', ['employee_number' => 'EMP-1003']);
    }

    public function test_an_employee_may_have_a_college_but_no_department(): void
    {
        $admin = $this->hr();
        $cas = College::where('code', 'CAS')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.employees.store'), [
            'employee_number' => 'EMP-1004',
            'name' => 'No Department',
            'email' => 'nodept@example.test',
            'college_id' => $cas->id,
            'role' => 'employee',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect();

        // Leave approval routes on the college alone, so this is a valid state.
        $this->assertDatabaseHas('users', [
            'employee_number' => 'EMP-1004',
            'college_id' => $cas->id,
            'department_id' => null,
            'department' => 'CAS',
        ]);
    }

    public function test_the_list_can_be_filtered_by_department(): void
    {
        $admin = $this->hr();
        $cas = College::where('code', 'CAS')->firstOrFail();

        $bsit = $this->department('CAS', 'Bachelor of Science in Information Technology');
        $bssw = $this->department('CAS', 'Bachelor of Science in Social Work');

        User::factory()->create([
            'name' => 'Alice In BSIT', 'role' => 'employee', 'status' => 'active',
            'college_id' => $cas->id, 'department_id' => $bsit->id,
        ]);
        User::factory()->create([
            'name' => 'Bob In BSSW', 'role' => 'employee', 'status' => 'active',
            'college_id' => $cas->id, 'department_id' => $bssw->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.employees.index', ['department' => $bsit->id]))
            ->assertOk()
            ->assertSee('Alice In BSIT')
            ->assertDontSee('Bob In BSSW');
    }
}
