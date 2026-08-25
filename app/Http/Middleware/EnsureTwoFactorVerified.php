<?php

namespace App\Http\Middleware;

use App\Services\TwoFactorService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Holds a half-authenticated session at the OTP screen.
 *
 * A privileged user who has entered a correct password but not yet the emailed
 * code is signed in as far as Laravel is concerned, so without this they could
 * simply navigate past the challenge.
 */
class EnsureTwoFactorVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $pending = $request->session()->has(TwoFactorService::SESSION_USER);

        if ($pending) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Two-factor verification required.'], 423)
                : redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }
}
