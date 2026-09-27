<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session of an account HR has deactivated.
 *
 * The sign-in screen already refuses an inactive account, but that only runs
 * once. Without this, someone deactivated mid-session keeps full access until
 * their session happens to expire.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->status === 'active') {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $message = 'Your account is inactive. Please contact the HR Office.';

        return $request->expectsJson()
            ? response()->json(['message' => $message], 401)
            : redirect()->route('login')->withErrors(['email' => $message]);
    }
}
