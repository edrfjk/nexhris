<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * One password rule, everywhere a password is set.
 *
 * Password reset required letters and numbers; the HR create-employee form,
 * the HR edit form and the profile screen accepted any eight characters. So
 * the weakest password in the system was the one HR typed, on the accounts
 * with the most access — a Dean, the Campus Director, another HR account.
 */
class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hr = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    /** Eight characters, but nothing else — what used to be accepted. */
    private const WEAK = 'passwords';

    private const STRONG = 'passwords9';

    public function test_hr_cannot_create_an_account_with_a_letters_only_password(): void
    {
        $this->actingAs($this->hr)
            ->post(route('admin.employees.store'), [
                'employee_number' => 'E-9001',
                // The ledger card prints a first day of government service, so a
                // new account cannot be created without one.
                'first_day_of_service' => '2020-06-01',
                'name' => 'Test Employee',
                'email' => 'test.employee@example.ph',
                'role' => 'employee',
                'college_id' => College::where('code', 'CAS')->value('id'),
                'password' => self::WEAK,
                'password_confirmation' => self::WEAK,
            ])
            ->assertSessionHasErrors('password');

        $this->assertNull(User::where('email', 'test.employee@example.ph')->first());
    }

    public function test_hr_can_create_an_account_with_a_password_that_meets_the_rule(): void
    {
        $this->actingAs($this->hr)
            ->post(route('admin.employees.store'), [
                'employee_number' => 'E-9002',
                // The ledger card prints a first day of government service, so a
                // new account cannot be created without one.
                'first_day_of_service' => '2020-06-01',
                'name' => 'Test Employee',
                'email' => 'ok.employee@example.ph',
                'role' => 'employee',
                'college_id' => College::where('code', 'CAS')->value('id'),
                'password' => self::STRONG,
                'password_confirmation' => self::STRONG,
            ])
            ->assertSessionHasNoErrors();

        $this->assertNotNull(User::where('email', 'ok.employee@example.ph')->first());
    }

    public function test_the_profile_screen_holds_the_same_rule(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
            'password' => Hash::make('current-password9'),
        ]);

        $this->actingAs($employee)
            ->put(route('profile.password.update'), [
                'current_password' => 'current-password9',
                'password' => self::WEAK,
                'password_confirmation' => self::WEAK,
            ])
            ->assertSessionHasErrors('password');
    }
}
