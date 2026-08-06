<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 15;

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

            return back()
                ->withErrors(['email' => "Too many login attempts. Please try again in " . ceil($seconds / 60) . " minute(s)."])
                ->onlyInput('email');
        }

        $user = \App\Models\User::where('email', $credentials['email'])->first();

        // Layer 2: per-account lockout, tracked in the database, survives
        // even if the attacker switches IP addresses.
        if ($user && $user->locked_until && $user->locked_until->isFuture()) {
            $minutesLeft = now()->diffInMinutes($user->locked_until) + 1;

            return back()
                ->withErrors(['email' => "This account is temporarily locked due to repeated failed login attempts. Try again in {$minutesLeft} minute(s), or contact the HR Office."])
                ->onlyInput('email');
        }

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, self::LOCKOUT_MINUTES * 60);

            if ($user) {
                $user->increment('failed_login_attempts');

                if ($user->failed_login_attempts >= self::MAX_ATTEMPTS) {
                    $user->update(['locked_until' => now()->addMinutes(self::LOCKOUT_MINUTES)]);
                }
            }

            // Deliberately generic message — never reveal whether the email
            // exists in the system, to avoid account enumeration.
            return back()
                ->withErrors(['email' => 'The email or password you entered is incorrect.'])
                ->onlyInput('email');
        }

        // Successful login: clear the throttle and any lockout state.
        RateLimiter::clear($throttleKey);
        Auth::user()->update(['failed_login_attempts' => 0, 'locked_until' => null]);

        $request->session()->regenerate();

        if (Auth::user()->status !== 'active') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')->withErrors([
                'email' => 'Your account is inactive. Please contact the HR Office.',
            ]);
        }

        return Auth::user()->isAdmin()
            ? redirect()->intended('/admin/dashboard')
            : redirect()->intended('/dashboard');
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}