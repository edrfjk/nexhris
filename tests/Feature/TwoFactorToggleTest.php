<?php

namespace Tests\Feature;

use App\Mail\TwoFactorCodeMail;
use App\Models\College;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The second factor can be switched off for local development. These pin the
 * behaviour of that switch, so turning it off cannot quietly weaken anything
 * beyond the sign-in step itself — and so it defaults back on.
 */
class TwoFactorToggleTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create([
            'employee_number' => 'E' . fake()->unique()->numberBetween(1000, 9999),
            'name' => ucfirst(str_replace('_', ' ', $role)) . ' User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'correct-horse-battery',
            'role' => $role,
            'status' => 'active',
            'college_id' => College::where('code', 'CAS')->value('id'),
        ]);
    }

    private function disable(): void
    {
        config(['auth.two_factor_enabled' => false]);
    }

    public function test_it_defaults_to_enabled(): void
    {
        // A missing or misspelt env var must never silently disable it.
        $this->assertTrue(
            (bool) config('auth.two_factor_enabled'),
            'Two-factor must default to on.'
        );
    }

    public static function privilegedRoles(): array
    {
        return [
            'HR Administrator' => ['admin', '/admin/dashboard'],
            'Dean' => ['dean', '/admin/leave/review'],
            'Campus Director' => ['campus_director', '/admin/leave/review'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('privilegedRoles')]
    public function test_when_disabled_a_privileged_role_signs_straight_in(string $role, string $home): void
    {
        $this->disable();
        Mail::fake();

        $user = $this->user($role);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
        ])->assertRedirect($home);

        $this->assertAuthenticatedAs($user);

        // No code issued, no email sent, no challenge left dangling.
        Mail::assertNothingSent();
        $this->assertDatabaseCount('two_factor_challenges', 0);

        // And they can actually reach the pages behind the gate.
        $this->get($home)->assertOk();
    }

    public function test_the_sign_in_screen_says_so_while_it_is_off(): void
    {
        $this->disable();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Two-factor verification is switched OFF');
    }

    public function test_the_warning_is_absent_when_it_is_on(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('Two-factor verification is switched OFF');
    }

    public function test_turning_it_off_does_not_weaken_anything_else(): void
    {
        $this->disable();

        // Wrong credentials are still refused.
        $user = $this->user('admin');
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        // An inactive account is still refused.
        $inactive = $this->user('admin');
        $inactive->update(['status' => 'inactive']);
        $this->post('/login', ['email' => $inactive->email, 'password' => 'correct-horse-battery']);
        $this->assertGuest();

        // Role boundaries still hold.
        $this->actingAs($this->user('employee'))->get('/admin/dashboard')->assertForbidden();

        // Sign out first: the login route sits behind `guest`, so an already
        // authenticated session would be redirected before it ever ran.
        $this->post('/logout');

        // And sign-ins are still recorded.
        $this->post('/login', ['email' => $user->email, 'password' => 'correct-horse-battery']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.login', 'user_id' => $user->id]);
    }

    public function test_switching_it_back_on_restores_the_challenge(): void
    {
        Mail::fake();
        $user = $this->user('admin');

        // Enabled is the default, so no config change is needed here.
        $this->assertTrue(app(TwoFactorService::class)->isRequiredFor($user));

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
        ])->assertRedirect(route('two-factor.challenge'));

        Mail::assertSent(TwoFactorCodeMail::class);
    }

    public function test_an_employee_is_unaffected_either_way(): void
    {
        foreach ([true, false] as $enabled) {
            config(['auth.two_factor_enabled' => $enabled]);

            $employee = $this->user('employee');

            $this->assertFalse(
                app(TwoFactorService::class)->isRequiredFor($employee),
                'Plain employees never face the second factor.'
            );

            $this->post('/logout');
        }
    }
}
