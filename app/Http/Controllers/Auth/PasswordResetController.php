<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Forgot-password and reset, on Laravel's own token broker.
 *
 * Both endpoints answer identically whether or not the address exists, so the
 * form cannot be used to discover which staff emails are registered.
 */
class PasswordResetController extends Controller
{
    public function __construct(private ActivityLogger $log)
    {
    }

    public function requestForm()
    {
        return view('auth.forgot-password');
    }

    public function sendLink(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $this->log->passwordResetRequested($data['email']);

        $user = User::where('email', $data['email'])->first();

        // An inactive account should not be recoverable by self-service.
        if ($user && $user->status === 'active') {
            Password::sendResetLink(['email' => $data['email']]);
        }

        return back()->with('success',
            'If that email belongs to a NexHRIS account, a password reset link is on its way. '
            . 'The link is valid for 60 minutes.');
    }

    public function resetForm(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ], [
            'password.confirmed' => 'The two passwords do not match.',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                    // A successful reset also clears any standing lockout.
                    'failed_login_attempts' => 0,
                    'locked_until' => null,
                ])->save();

                $this->log->passwordReset($user);

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __($status)]);
        }

        return redirect()->route('login')
            ->with('success', 'Your password has been changed. You can sign in now.');
    }
}
