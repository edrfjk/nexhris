<?php

namespace Tests\Feature;

use App\Mail\TwoFactorCodeMail;
use App\Models\ActivityLog;
use App\Models\TwoFactorChallenge;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, array $overrides = []): User
    {
        return User::create(array_merge([
            'employee_number' => 'E' . fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Dela Cruz, Juan M.',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'correct-horse-battery',
            'role' => $role,
            'status' => 'active',
            'department' => 'CAS',
            'college_id' => \App\Models\College::firstOrCreate(
                ['code' => 'CAS'], ['name' => 'College of Arts and Sciences'])->id,
        ], $overrides));
    }

    // ------------------------------------------------------------------
    // Password sign-in
    // ------------------------------------------------------------------

    public function test_employee_signs_in_with_a_password_alone(): void
    {
        $user = $this->user('employee');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => 'auth.login']);
    }

    public function test_wrong_password_is_rejected_without_revealing_the_account(): void
    {
        $user = $this->user('employee');

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();

        // The message must not distinguish "no such user" from "bad password".
        $this->assertSame(
            'The email or password you entered is incorrect.',
            session('errors')->first('email')
        );

        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.login_failed']);
    }

    public function test_inactive_account_cannot_sign_in(): void
    {
        $user = $this->user('employee', ['status' => 'inactive']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
        ])->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_repeated_failures_lock_the_account(): void
    {
        $user = $this->user('employee');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->assertNotNull($user->fresh()->locked_until);
        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.locked', 'user_id' => $user->id]);
    }

    // ------------------------------------------------------------------
    // Two-factor
    // ------------------------------------------------------------------

    public static function privilegedRoles(): array
    {
        return [
            'HR Administrator' => ['admin'],
            'Dean' => ['dean'],
            'Campus Director' => ['campus_director'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('privilegedRoles')]
    public function test_privileged_roles_are_held_at_the_two_factor_challenge(string $role): void
    {
        Mail::fake();
        $user = $this->user($role);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
        ])->assertRedirect(route('two-factor.challenge'));

        Mail::assertSent(TwoFactorCodeMail::class, fn ($m) => $m->hasTo($user->email));

        $this->assertDatabaseHas('two_factor_challenges', ['user_id' => $user->id, 'consumed_at' => null]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.2fa_issued', 'user_id' => $user->id]);

        // Half-authenticated: any other page bounces back to the challenge.
        $this->get('/admin/dashboard')->assertRedirect(route('two-factor.challenge'));
    }

    public function test_a_failed_code_email_signs_the_user_back_out(): void
    {
        // A privileged login that cannot deliver its code must not leave the
        // user parked on the verification screen with nothing to enter.
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP refused the connection'));

        $user = $this->user('admin');

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();

        // The unusable challenge is closed rather than left open.
        $challenge = TwoFactorChallenge::first();
        $this->assertNotNull($challenge->consumed_at);

        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.2fa_failed']);
    }

    public function test_employee_never_receives_a_two_factor_challenge(): void
    {
        Mail::fake();
        $user = $this->user('employee');

        $this->post('/login', ['email' => $user->email, 'password' => 'correct-horse-battery']);

        Mail::assertNothingSent();
        $this->assertDatabaseCount('two_factor_challenges', 0);
    }

    /** Signs in and returns the plaintext code that was emailed. */
    private function startChallenge(User $user): string
    {
        $code = null;
        Mail::fake();

        $this->post('/login', ['email' => $user->email, 'password' => 'correct-horse-battery']);

        Mail::assertSent(TwoFactorCodeMail::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return $code;
    }

    public function test_correct_code_completes_sign_in(): void
    {
        $user = $this->user('admin');
        $code = $this->startChallenge($user);

        $this->post(route('two-factor.verify'), ['code' => $code])
            ->assertRedirect('/admin/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->get('/admin/dashboard')->assertOk();

        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.2fa_passed', 'user_id' => $user->id]);
        $this->assertNotNull(TwoFactorChallenge::first()->consumed_at);
    }

    public function test_wrong_code_is_rejected_and_counted(): void
    {
        $user = $this->user('admin');
        $this->startChallenge($user);

        $this->post(route('two-factor.verify'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertSame(1, TwoFactorChallenge::first()->attempts);
        $this->get('/admin/dashboard')->assertRedirect(route('two-factor.challenge'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.2fa_failed']);
    }

    public function test_a_code_cannot_be_used_twice(): void
    {
        $user = $this->user('admin');
        $code = $this->startChallenge($user);

        $this->post(route('two-factor.verify'), ['code' => $code])->assertRedirect();
        $this->post('/logout');

        // Re-open a challenge, then try the old code against it.
        $this->startChallenge($user);

        $this->post(route('two-factor.verify'), ['code' => $code])
            ->assertSessionHasErrors('code');
    }

    public function test_an_expired_code_is_refused(): void
    {
        $user = $this->user('admin');
        $code = $this->startChallenge($user);

        TwoFactorChallenge::first()->update(['expires_at' => now()->subMinute()]);

        $this->post(route('two-factor.verify'), ['code' => $code])
            ->assertSessionHasErrors('code');

        $this->get('/admin/dashboard')->assertRedirect(route('two-factor.challenge'));
    }

    public function test_a_challenge_dies_after_five_wrong_guesses(): void
    {
        $user = $this->user('admin');
        $code = $this->startChallenge($user);

        for ($i = 0; $i < TwoFactorChallenge::MAX_ATTEMPTS; $i++) {
            $this->post(route('two-factor.verify'), ['code' => '111111']);
        }

        // Even the genuine code is now useless.
        $this->post(route('two-factor.verify'), ['code' => $code])
            ->assertSessionHasErrors('code');

        $this->get('/admin/dashboard')->assertRedirect(route('two-factor.challenge'));
    }

    public function test_issuing_a_new_code_invalidates_the_previous_one(): void
    {
        $user = $this->user('admin');
        $first = $this->startChallenge($user);

        // Resend is rate limited by a cooldown, so issue directly.
        $this->travel(70)->seconds();
        Mail::fake();
        $this->post(route('two-factor.resend'))->assertRedirect();

        $this->post(route('two-factor.verify'), ['code' => $first])
            ->assertSessionHasErrors('code');
    }

    public function test_cancelling_the_challenge_signs_out(): void
    {
        $user = $this->user('admin');
        $this->startChallenge($user);

        $this->post(route('two-factor.cancel'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    // ------------------------------------------------------------------
    // Password reset
    // ------------------------------------------------------------------

    public function test_reset_link_is_emailed_for_an_active_account(): void
    {
        Notification::fake();
        $user = $this->user('employee');

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('success');

        Notification::assertSentTo($user, ResetPassword::class);
        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.password_reset_requested']);
    }

    public function test_forgot_password_does_not_reveal_whether_an_email_exists(): void
    {
        Notification::fake();

        $known = $this->user('employee');

        $a = $this->post('/forgot-password', ['email' => $known->email]);
        $b = $this->post('/forgot-password', ['email' => 'nobody@example.test']);

        $this->assertSame(session()->get('success'), session()->get('success'));
        $a->assertSessionHas('success');
        $b->assertSessionHas('success');

        Notification::assertSentTo($known, ResetPassword::class);
    }

    public function test_inactive_account_gets_no_reset_link(): void
    {
        Notification::fake();
        $user = $this->user('employee', ['status' => 'inactive']);

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('success');

        Notification::assertNothingSent();
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        $user = $this->user('employee', ['locked_until' => now()->addHour(), 'failed_login_attempts' => 5]);
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'brand-new-pass1',
            'password_confirmation' => 'brand-new-pass1',
        ])->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue(Hash::check('brand-new-pass1', $user->password));

        // A successful reset also clears the lockout.
        $this->assertNull($user->locked_until);
        $this->assertSame(0, (int) $user->failed_login_attempts);

        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.password_reset', 'user_id' => $user->id]);
    }

    public function test_reset_rejects_a_weak_password(): void
    {
        $user = $this->user('employee');

        $this->post('/reset-password', [
            'token' => Password::createToken($user),
            'email' => $user->email,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }

    // ------------------------------------------------------------------
    // Role guards
    // ------------------------------------------------------------------

    public function test_only_hr_reaches_the_activity_log(): void
    {
        foreach (['dean', 'campus_director'] as $role) {
            $user = $this->user($role);
            $code = $this->startChallenge($user);
            $this->post(route('two-factor.verify'), ['code' => $code]);

            $this->get(route('admin.activity-logs.index'))->assertForbidden();
            $this->post('/logout');
        }

        $hr = $this->user('admin');
        $code = $this->startChallenge($hr);
        $this->post(route('two-factor.verify'), ['code' => $code]);

        $this->get(route('admin.activity-logs.index'))->assertOk();
    }

    public function test_employee_cannot_reach_admin_area(): void
    {
        $this->actingAs($this->user('employee'))
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_logout_is_recorded(): void
    {
        $user = $this->user('employee');
        $this->actingAs($user)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.logout', 'user_id' => $user->id]);
    }

    public function test_activity_log_records_the_ip_address(): void
    {
        $user = $this->user('employee');
        $this->actingAs($user)->post('/logout');

        $log = ActivityLog::where('action', 'auth.logout')->first();

        $this->assertNotNull($log->ip_address);
    }
}
