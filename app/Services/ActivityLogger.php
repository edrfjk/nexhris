<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

/**
 * Writes the audit trail.
 *
 * Logging must never break the action it is recording, so every write is
 * wrapped — a failure here is reported to the application log rather than
 * thrown at the user mid-request.
 */
class ActivityLogger
{
    public function log(
        string $action,
        ?string $description = null,
        ?Model $subject = null,
        array $properties = [],
        ?User $actor = null,
    ): ?ActivityLog {
        try {
            return ActivityLog::create([
                'user_id' => $actor?->id ?? auth()->id(),
                'action' => $action,
                'description' => $description,
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'ip_address' => Request::ip(),
                // Long agent strings are truncated to the column width rather
                // than throwing on insert.
                'user_agent' => substr((string) Request::userAgent(), 0, 512),
                'properties' => $properties ?: null,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    // ------------------------------------------------------------------
    // Authentication events
    // ------------------------------------------------------------------

    public function loginSucceeded(User $user): void
    {
        $this->log('auth.login', "{$user->name} signed in.", $user, actor: $user);
    }

    public function loginFailed(string $email, string $reason): void
    {
        // No actor: the credentials did not identify anyone we trust.
        $this->log('auth.login_failed', "Failed sign-in for {$email}: {$reason}.", null, [
            'email' => $email,
            'reason' => $reason,
        ]);
    }

    public function accountLocked(User $user, int $minutes): void
    {
        $this->log('auth.locked', "{$user->name} locked out for {$minutes} minute(s).", $user,
            ['minutes' => $minutes], actor: $user);
    }

    public function logout(User $user): void
    {
        $this->log('auth.logout', "{$user->name} signed out.", $user, actor: $user);
    }

    public function twoFactorIssued(User $user): void
    {
        $this->log('auth.2fa_issued', "Verification code emailed to {$user->email}.", $user, actor: $user);
    }

    public function twoFactorPassed(User $user): void
    {
        $this->log('auth.2fa_passed', "{$user->name} completed two-factor verification.", $user, actor: $user);
    }

    public function twoFactorFailed(User $user, string $reason): void
    {
        $this->log('auth.2fa_failed', "Two-factor verification failed: {$reason}.", $user,
            ['reason' => $reason], actor: $user);
    }

    public function passwordResetRequested(string $email): void
    {
        $this->log('auth.password_reset_requested', "Password reset requested for {$email}.", null,
            ['email' => $email]);
    }

    public function passwordReset(User $user): void
    {
        $this->log('auth.password_reset', "{$user->name} reset their password.", $user, actor: $user);
    }
}
