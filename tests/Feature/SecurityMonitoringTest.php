<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Worksheet 4.1 scenarios, each proved with a test account and in-memory
 * SQLite: suspicious sign-ins, denied admin requests, error context, and the
 * audit trail behind employee account changes.
 */
class SecurityMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private function login(User $user, string $password)
    {
        return $this->post('/login', ['email' => $user->email, 'password' => $password, 'terms' => '1']);
    }

    // ------------------------------------------------------------------
    // Scenario 1: repeated failed logins
    // ------------------------------------------------------------------

    public function test_a_success_after_repeated_failures_is_flagged(): void
    {
        $user = User::factory()->create(['role' => 'employee', 'status' => 'active', 'password' => 'correct-horse-battery']);

        foreach (range(1, 3) as $_) {
            $this->login($user, 'wrong-guess');
        }

        $this->login($user, 'correct-horse-battery')->assertRedirect('/dashboard');

        $this->assertSame(3, ActivityLog::where('action', 'auth.login_failed')->count());
        $flag = ActivityLog::where('action', 'auth.login_after_failures')->sole();
        $this->assertSame($user->id, $flag->user_id);
        $this->assertSame(3, $flag->properties['failed_attempts']);
        $this->assertSame(0, $user->fresh()->failed_login_attempts);
    }

    public function test_a_single_typo_is_not_flagged(): void
    {
        $user = User::factory()->create(['role' => 'employee', 'status' => 'active', 'password' => 'correct-horse-battery']);

        $this->login($user, 'typo');
        $this->login($user, 'correct-horse-battery')->assertRedirect('/dashboard');

        $this->assertDatabaseMissing('activity_logs', ['action' => 'auth.login_after_failures']);
    }

    public function test_the_fifth_failure_locks_the_account_even_with_the_right_password(): void
    {
        $user = User::factory()->create(['role' => 'employee', 'status' => 'active', 'password' => 'correct-horse-battery']);

        foreach (range(1, 5) as $_) {
            $this->login($user, 'wrong-guess');
        }

        $this->login($user, 'correct-horse-battery')->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertNotNull($user->fresh()->locked_until);
        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.locked', 'user_id' => $user->id]);
    }

    // ------------------------------------------------------------------
    // Scenario 2: unauthorized access to an admin function
    // ------------------------------------------------------------------

    public function test_an_employee_is_refused_hr_screens_and_the_attempt_is_recorded(): void
    {
        $employee = User::factory()->create(['role' => 'employee', 'status' => 'active']);

        $this->actingAs($employee)->get('/admin/employees/create')->assertForbidden();

        $entry = ActivityLog::where('action', 'access.denied')->sole();
        $this->assertSame($employee->id, $entry->user_id);
        $this->assertSame('admin/employees/create', $entry->properties['path']);
        $this->assertSame('employee', $entry->properties['role']);
    }

    public function test_a_dean_cannot_create_accounts_but_hr_can(): void
    {
        $dean = User::factory()->create(['role' => 'dean', 'status' => 'active']);
        $hr = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($dean)->get('/admin/employees/create')->assertForbidden();
        $this->assertDatabaseHas('activity_logs', ['action' => 'access.denied', 'user_id' => $dean->id]);

        // HR is let through: the old create address forwards to the directory.
        $this->actingAs($hr)->get('/admin/employees/create')
            ->assertRedirect(route('admin.employees.index', ['add' => 1]));
        $this->assertDatabaseMissing('activity_logs', ['action' => 'access.denied', 'user_id' => $hr->id]);
    }

    // ------------------------------------------------------------------
    // Scenario 3: repeated application errors
    // ------------------------------------------------------------------

    public function test_a_server_error_is_logged_with_the_request_behind_it(): void
    {
        Route::middleware('web')->get('/_test/boom', fn () => throw new \RuntimeException('simulated failure'));
        $user = User::factory()->create(['role' => 'employee', 'status' => 'active']);

        Log::shouldReceive('error')->once()->withArgs(function ($message, $context) use ($user) {
            return str_contains($message, 'simulated failure')
                && $context['user_id'] === $user->id
                && str_ends_with($context['url'], '/_test/boom')
                && $context['method'] === 'GET';
        });

        $this->actingAs($user)->get('/_test/boom')->assertStatus(500);
    }

    // ------------------------------------------------------------------
    // Scenario 4: unexpected changes to important records
    // ------------------------------------------------------------------

    public function test_an_employee_edit_records_who_changed_what_from_what(): void
    {
        $hr = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $employee = User::factory()->create([
            'role' => 'employee', 'status' => 'active', 'employee_number' => 'AUDIT-001', 'contact_number' => '09170000000',
        ]);

        $this->actingAs($hr)->put(route('admin.employees.update', $employee), [
            'employee_number' => 'AUDIT-001',
            'name' => $employee->name,
            'email' => $employee->email,
            'contact_number' => '09179999999',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertRedirect(route('admin.employees.show', $employee));

        $entry = ActivityLog::where('action', 'employee.updated')->sole();
        $this->assertSame($hr->id, $entry->user_id);
        $this->assertSame(User::class, $entry->subject_type);
        $this->assertSame($employee->id, $entry->subject_id);
        $this->assertContains('contact_number', $entry->properties['changed']);
        $this->assertContains('password', $entry->properties['changed']);
        $this->assertSame('09170000000', $entry->properties['before']['contact_number']);
        $this->assertSame('09179999999', $entry->properties['after']['contact_number']);
        // The password is named as changed, never stored, hashed or not.
        $this->assertArrayNotHasKey('password', $entry->properties['before']);
        $this->assertArrayNotHasKey('password', $entry->properties['after']);
    }

    public function test_deactivating_an_account_is_traced_to_hr(): void
    {
        $hr = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $employee = User::factory()->create(['role' => 'employee', 'status' => 'active']);

        $this->actingAs($hr)->patch(route('admin.employees.status.update', $employee), ['status' => 'inactive']);

        $entry = ActivityLog::where('action', 'employee.deactivated')->sole();
        $this->assertSame($hr->id, $entry->user_id);
        $this->assertSame($employee->id, $entry->subject_id);
        $this->assertSame('active', $entry->properties['before']['status']);
    }

    public function test_the_audit_trail_screen_shows_the_new_entries_to_hr_only(): void
    {
        $hr = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $employee = User::factory()->create(['role' => 'employee', 'status' => 'active']);

        $this->actingAs($hr)->patch(route('admin.employees.status.update', $employee), ['status' => 'inactive']);

        $this->actingAs($hr)->get(route('admin.activity-logs.index', ['action' => 'employee.deactivated']))
            ->assertOk()
            ->assertSee("Set the account of {$employee->name} to inactive.");
    }
}
