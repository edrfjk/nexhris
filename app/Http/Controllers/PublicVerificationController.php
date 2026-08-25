<?php

namespace App\Http\Controllers;

use App\Models\User;

/**
 * The page a digital ID's QR code points at.
 *
 * Deliberately reachable without signing in, and deliberately thin: name,
 * position, college and whether the account is active. Nothing that could be
 * used against the person — no email, contact number, employee number, leave
 * balance or PDS data.
 *
 * Addressed by an unguessable token rather than the employee id, so the page
 * cannot be used to enumerate staff.
 */
class PublicVerificationController extends Controller
{
    public function show(string $token)
    {
        $employee = User::with('college')
            ->where('verification_token', $token)
            ->first();

        // A wrong token gets the same page as a real one, minus the record —
        // no hint about whether the token merely expired or never existed.
        return response()->view('public.verify', [
            'employee' => $employee,
        ], $employee ? 200 : 404);
    }
}
