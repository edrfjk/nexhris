<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\TwoFactorDeliveryException;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 15;

    public function __construct(
        private TwoFactorService $twoFactor,
        private ActivityLogger $log,
    ) {
    }

    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $throttleKey = Str::lower($credentials['email']) . '|' . $request->ip();

        // Layer 1: IP + email based rate limiting (blocks rapid brute force
        // even before we look the account up in the database).
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            $this->log->loginFailed($credentials['email'], 'rate limited');

            return back()
                ->withErrors(['email' => 'Too many login attempts. Please try again in ' . ceil($seconds / 60) . ' minute(s).'])
                ->onlyInput('email');
        }

        $user = User::where('email', $credentials['email'])->first();

        // Layer 2: per-account lockout, tracked in the database, survives
        // even if the attacker switches IP addresses.
        if ($user && $user->locked_until && $user->locked_until->isFuture()) {
            $minutesLeft = now()->diffInMinutes($user->locked_until) + 1;

            $this->log->loginFailed($credentials['email'], 'account locked');

            return back()
                ->withErrors(['email' => "This account is temporarily locked due to repeated failed login attempts. Try again in {$minutesLeft} minute(s), or contact the HR Office."])
                ->onlyInput('email');
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, self::LOCKOUT_MINUTES * 60);

            if ($user) {
                $user->increment('failed_login_attempts');

                if ($user->failed_login_attempts >= self::MAX_ATTEMPTS) {
                    $user->update(['locked_until' => now()->addMinutes(self::LOCKOUT_MINUTES)]);
                    $this->log->accountLocked($user, self::LOCKOUT_MINUTES);
                }
            }

            $this->log->loginFailed($credentials['email'], 'invalid credentials');

            // Deliberately generic message — never reveal whether the email
            // exists in the system, to avoid account enumeration.
            return back()
                ->withErrors(['email' => 'The email or password you entered is incorrect.'])
                ->onlyInput('email');
        }

        $user = Auth::user();

        if ($user->status !== 'active') {
            $this->log->loginFailed($credentials['email'], 'inactive account');

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')->withErrors([
                'email' => 'Your account is inactive. Please contact the HR Office.',
            ]);
        }

        // Password accepted: clear the throttle and any lockout state.
        RateLimiter::clear($throttleKey);
        $user->update(['failed_login_attempts' => 0, 'locked_until' => null]);
        $request->session()->regenerate();

        // Privileged roles must still clear the emailed second factor. The
        // session is held in a half-authenticated state until they do.
        if ($this->twoFactor->isRequiredFor($user)) {
            try {
                $challenge = $this->twoFactor->issue($user);
            } catch (TwoFactorDeliveryException $e) {
                // No code was delivered, so there is nothing to verify. Sign
                // them back out rather than parking them on the code screen.
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors(['email' => $e->getMessage()]);
            }

            $request->session()->put(TwoFactorService::SESSION_USER, $user->id);
            $request->session()->put(TwoFactorService::SESSION_CHALLENGE, $challenge->id);

            return redirect()->route('two-factor.challenge');
        }

        $this->log->loginSucceeded($user);

        return redirect()->intended($user->homeRoute());
    }

    public function destroy(Request $request)
    {
        if ($user = Auth::user()) {
            $this->log->logout($user);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('success', 'You have been signed out.');
    }
}
