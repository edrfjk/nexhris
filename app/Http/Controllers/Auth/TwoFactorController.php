<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\TwoFactorChallenge;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\TwoFactorDeliveryException;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The email one-time-password step. Between password and code the session is
 * half-authenticated: Laravel has a user, but `EnsureTwoFactorVerified` keeps
 * them out of everything except this screen.
 */
class TwoFactorController extends Controller
{
    public function __construct(
        private TwoFactorService $twoFactor,
        private ActivityLogger $log,
    ) {
    }

    public function show(Request $request)
    {
        $challenge = $this->pendingChallenge($request);

        if (! $challenge) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge', [
            'user' => $challenge->user,
            'expiresAt' => $challenge->expires_at,
            'cooldown' => $this->twoFactor->cooldownRemaining($challenge),
        ]);
    }

    public function verify(Request $request)
    {
        $challenge = $this->pendingChallenge($request);

        if (! $challenge) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Your sign-in session expired. Please sign in again.']);
        }

        $data = $request->validate([
            'code' => ['required', 'digits:6'],
        ], [
            'code.digits' => 'The verification code is six digits.',
        ]);

        // Independent of the per-challenge attempt counter, so someone cannot
        // simply request fresh challenges to keep guessing.
        $key = '2fa|' . $challenge->user_id . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            return back()->withErrors([
                'code' => 'Too many attempts. Try again in ' . ceil(RateLimiter::availableIn($key) / 60) . ' minute(s).',
            ]);
        }

        if ($error = $this->twoFactor->verify($challenge, $data['code'])) {
            RateLimiter::hit($key, 900);

            return back()->withErrors(['code' => $error]);
        }

        RateLimiter::clear($key);

        $user = $challenge->user;

        // Promote the session to fully authenticated.
        $request->session()->forget([
            TwoFactorService::SESSION_USER,
            TwoFactorService::SESSION_CHALLENGE,
        ]);
        $request->session()->put(TwoFactorService::SESSION_VERIFIED, now()->timestamp);
        $request->session()->regenerate();

        $this->log->twoFactorPassed($user);
        $this->log->loginSucceeded($user);

        return redirect()->intended($user->homeRoute());
    }

    public function resend(Request $request)
    {
        $challenge = $this->pendingChallenge($request);

        if (! $challenge) {
            return redirect()->route('login');
        }

        if (($wait = $this->twoFactor->cooldownRemaining($challenge)) > 0) {
            return back()->withErrors([
                'code' => "Please wait {$wait} more second(s) before requesting another code.",
            ]);
        }

        try {
            $fresh = $this->twoFactor->issue($challenge->user);
        } catch (TwoFactorDeliveryException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        $request->session()->put(TwoFactorService::SESSION_CHALLENGE, $fresh->id);

        return back()->with('success', 'A new verification code is on its way to your email.');
    }

    /** Abandons the half-authenticated session. */
    public function cancel(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * The challenge this session is currently answering, or null when there
     * is no valid half-authenticated state.
     */
    private function pendingChallenge(Request $request): ?TwoFactorChallenge
    {
        $userId = $request->session()->get(TwoFactorService::SESSION_USER);
        $challengeId = $request->session()->get(TwoFactorService::SESSION_CHALLENGE);

        if (! $userId || ! $challengeId || Auth::id() !== $userId) {
            return null;
        }

        return TwoFactorChallenge::with('user')
            ->where('id', $challengeId)
            ->where('user_id', $userId)
            ->first();
    }
}
