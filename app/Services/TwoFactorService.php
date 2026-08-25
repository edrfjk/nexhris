<?php

namespace App\Services;

use App\Mail\TwoFactorCodeMail;
use App\Models\TwoFactorChallenge;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Email one-time-password second factor.
 *
 * Required for the three privileged roles — HR Administrator, Dean and Campus
 * Director — because those accounts approve leave and edit employee records.
 * Plain employees sign in with a password alone.
 */
class TwoFactorService
{
    public const SESSION_USER = '2fa.user_id';
    public const SESSION_CHALLENGE = '2fa.challenge_id';
    public const SESSION_VERIFIED = '2fa.verified_at';

    /** How long an emailed code stays valid. */
    public const TTL_MINUTES = 10;

    /** Shortest gap between resend requests. */
    public const RESEND_COOLDOWN_SECONDS = 60;

    public function __construct(private ActivityLogger $log)
    {
    }

    /** Roles whose sign-in must clear a second factor. */
    public function isRequiredFor(User $user): bool
    {
        if (! self::isEnabled()) {
            return false;
        }

        return in_array($user->role, ['admin', 'dean', 'campus_director'], true);
    }

    /** Whether the second factor is switched on at all. */
    public static function isEnabled(): bool
    {
        return (bool) config('auth.two_factor_enabled', true);
    }

    /**
     * Issues a fresh code, invalidating any outstanding challenge so only one
     * code is ever live per account.
     */
    public function issue(User $user): TwoFactorChallenge
    {
        TwoFactorChallenge::where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $challenge = TwoFactorChallenge::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'ip_address' => request()->ip(),
        ]);

        // The mailable carries the only plaintext copy of the code. If the
        // send fails the challenge is useless, so it is consumed immediately
        // and the caller told — otherwise the user reaches the code screen
        // with no code and no way forward.
        try {
            Mail::to($user->email)->send(new TwoFactorCodeMail($user, $code, self::TTL_MINUTES));
        } catch (\Throwable $e) {
            $challenge->update(['consumed_at' => now()]);

            report($e);

            $this->log->twoFactorFailed($user, 'verification email could not be sent');

            throw new TwoFactorDeliveryException(
                'We could not send your verification code by email. '
                . 'Please try again, or contact the HR Office if this keeps happening.',
                previous: $e,
            );
        }

        $this->log->twoFactorIssued($user);

        return $challenge;
    }

    /**
     * Checks a submitted code. Returns null on success, or a message
     * explaining why it was rejected.
     */
    public function verify(TwoFactorChallenge $challenge, string $code): ?string
    {
        if ($challenge->isConsumed()) {
            return 'That code has already been used. Request a new one.';
        }

        if ($challenge->isExpired()) {
            return 'That code has expired. Request a new one.';
        }

        if ($challenge->isExhausted()) {
            return 'Too many incorrect attempts. Request a new code.';
        }

        if (! Hash::check($code, $challenge->code_hash)) {
            $challenge->increment('attempts');
            $challenge->refresh();

            $this->log->twoFactorFailed($challenge->user, 'incorrect code');

            return $challenge->isExhausted()
                ? 'Too many incorrect attempts. Request a new code.'
                : "That code is not correct. {$challenge->attemptsLeft()} attempt(s) remaining.";
        }

        $challenge->update(['consumed_at' => now()]);

        return null;
    }

    /** Seconds the user must wait before another code can be sent. */
    public function cooldownRemaining(TwoFactorChallenge $challenge): int
    {
        $elapsed = $challenge->created_at->diffInSeconds(now());

        return max(0, self::RESEND_COOLDOWN_SECONDS - (int) $elapsed);
    }
}
