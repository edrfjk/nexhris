<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            // The ledger card prints a first day of government service, so a
            // new account cannot be created without one.
            'first_day_of_service' => '2020-06-01',
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

    public function test_structured_name_is_saved_and_supplies_the_ledger_parts(): void
    {
        $admin = $this->hr();
        $cas = College::where('code', 'CAS')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.employees.store'), [
            'employee_number' => 'EMP-STRUCTURED',
            'first_name' => 'Maria Clara',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'email' => 'maria@example.test',
            'position' => 'Instructor I',
            'college_id' => $cas->id,
            'role' => 'employee',
            'first_day_of_service' => '2020-06-01',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('admin.employees.index'));

        $employee = User::where('employee_number', 'EMP-STRUCTURED')->firstOrFail();

        $this->assertSame('Maria Clara Santos Dela Cruz', $employee->name);
        $this->assertSame([
            'family' => 'DELA CRUZ',
            'first' => 'MARIA CLARA',
            'middle' => 'S.',
        ], $employee->nameParts());
    }

    public function test_program_head_is_automatically_assigned_to_the_selected_department(): void
    {
        $admin = $this->hr();
        $cas = College::where('code', 'CAS')->firstOrFail();
        $bsit = $this->department('CAS', 'Bachelor of Science in Information Technology');

        $this->actingAs($admin)->post(route('admin.employees.store'), [
            'employee_number' => 'BSIT-HEAD',
            'first_name' => 'Jamie',
            'last_name' => 'Santos',
            'email' => 'jamie.santos@example.test',
            'position' => 'Program Chair / Program Head',
            'college_id' => $cas->id,
            'department_id' => $bsit->id,
            'role' => 'employee',
            'first_day_of_service' => '2020-06-01',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('admin.employees.index'));

        $this->assertSame(
            User::where('employee_number', 'BSIT-HEAD')->value('id'),
            $bsit->fresh()->head_id,
        );
    }

    public function test_college_dean_position_automatically_appoints_the_employee_and_grants_dean_role(): void
    {
        $admin = $this->hr();
        $cas = College::where('code', 'CAS')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.employees.store'), [
            'employee_number' => 'CAS-DEAN',
            'first_name' => 'Alex',
            'last_name' => 'Reyes',
            'email' => 'alex.reyes@example.test',
            'position' => 'College Dean',
            'college_id' => $cas->id,
            'role' => 'employee',
            'first_day_of_service' => '2020-06-01',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('admin.employees.index'));

        $dean = User::where('employee_number', 'CAS-DEAN')->firstOrFail();
        $this->assertSame($dean->id, $cas->fresh()->dean_id);
        $this->assertSame('dean', $dean->fresh()->role);
    }

    public function test_replacing_an_existing_program_head_requires_confirmation_and_keeps_their_account(): void
    {
        $admin = $this->hr();
        $cas = College::where('code', 'CAS')->firstOrFail();
        $bsit = $this->department('CAS', 'Bachelor of Science in Information Technology');
        $previous = User::factory()->create(['role' => 'employee', 'college_id' => $cas->id]);
        $bsit->update(['head_id' => $previous->id]);

        $payload = [
            'employee_number' => 'NEW-BSIT-HEAD', 'first_name' => 'Taylor', 'last_name' => 'Cruz',
            'email' => 'taylor.cruz@example.test', 'position' => 'Program Chair / Program Head',
            'college_id' => $cas->id, 'department_id' => $bsit->id, 'role' => 'employee',
            'first_day_of_service' => '2020-06-01', 'password' => 'password123', 'password_confirmation' => 'password123',
        ];

        $this->actingAs($admin)->post(route('admin.employees.store'), $payload)
            ->assertSessionHasErrors('position');
        $this->assertSame($previous->id, $bsit->fresh()->head_id);

        $this->actingAs($admin)->post(route('admin.employees.store'), $payload + ['confirm_replace_program_head' => true])
            ->assertRedirect(route('admin.employees.index'));
        $this->assertNotSame($previous->id, $bsit->fresh()->head_id);
        $this->assertDatabaseHas('users', ['id' => $previous->id]);
    }

    public function test_replacing_a_college_dean_requires_confirmation_and_removes_the_previous_dean_role(): void
    {
        $admin = $this->hr();
        $cas = College::where('code', 'CAS')->firstOrFail();
        $previous = User::factory()->create(['role' => 'dean', 'college_id' => $cas->id]);
        $cas->update(['dean_id' => $previous->id]);

        $payload = [
            'employee_number' => 'NEW-CAS-DEAN', 'first_name' => 'Morgan', 'last_name' => 'Flores',
            'email' => 'morgan.flores@example.test', 'position' => 'College Dean',
            'college_id' => $cas->id, 'role' => 'employee', 'first_day_of_service' => '2020-06-01',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ];

        $this->actingAs($admin)->post(route('admin.employees.store'), $payload)
            ->assertSessionHasErrors('position');
        $this->assertSame($previous->id, $cas->fresh()->dean_id);

        $this->actingAs($admin)->post(route('admin.employees.store'), $payload + ['confirm_replace_dean' => true])
            ->assertRedirect(route('admin.employees.index'));

        $replacement = User::where('employee_number', 'NEW-CAS-DEAN')->firstOrFail();
        $this->assertSame($replacement->id, $cas->fresh()->dean_id);
        $this->assertSame('dean', $replacement->fresh()->role);
        $this->assertSame('employee', $previous->fresh()->role);
    }

    public function test_hr_can_manage_categories_and_the_position_catalogue(): void
    {
        $admin = $this->hr();

        $this->actingAs($admin)->post(route('admin.positions.store'), [
            'name' => 'ICT Officer',
            'new_category' => 'Information Technology Office',
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $position = Position::where('name', 'ICT Officer')->firstOrFail();
        $this->assertSame('Information Technology Office', $position->category);

        $this->actingAs($admin)->get(route('admin.employees.index'))
            ->assertOk()
            ->assertSee('add-employee', false)
            ->assertSee('ICT Officer');

        $this->actingAs($admin)->put(route('admin.positions.update', $position), [
            'name' => 'Information and Communications Technology Officer',
            'category' => 'Information Technology Office',
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('positions', ['name' => 'Information and Communications Technology Officer']);
    }

    public function test_hr_can_optionally_replace_a_photo_while_editing_an_account(): void
    {
        Storage::fake('public');
        $admin = $this->hr();
        $employee = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
            'employee_number' => 'PHOTO-100',
            'profile_photo_path' => 'profile-photos/old.jpg',
        ]);
        Storage::disk('public')->put('profile-photos/old.jpg', 'old photo');

        $this->actingAs($admin)->put(route('admin.employees.update', $employee), [
            'employee_number' => $employee->employee_number,
            'name' => $employee->name,
            'email' => $employee->email,
            'photo' => UploadedFile::fake()->image('new-photo.jpg'),
        ])->assertRedirect(route('admin.employees.show', $employee));

        $newPath = $employee->fresh()->profile_photo_path;
        $this->assertNotSame('profile-photos/old.jpg', $newPath);
        Storage::disk('public')->assertMissing('profile-photos/old.jpg');
        Storage::disk('public')->assertExists($newPath);
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
            // The ledger card prints a first day of government service, so a
            // new account cannot be created without one.
            'first_day_of_service' => '2020-06-01',
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
            // The ledger card prints a first day of government service, so a
            // new account cannot be created without one.
            'first_day_of_service' => '2020-06-01',
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
            // The ledger card prints a first day of government service, so a
            // new account cannot be created without one.
            'first_day_of_service' => '2020-06-01',
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
            // The ledger card prints a first day of government service, so a
            // new account cannot be created without one.
            'first_day_of_service' => '2020-06-01',
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
