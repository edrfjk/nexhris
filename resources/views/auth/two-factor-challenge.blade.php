@extends('layouts.guest')

@section('title', 'Verification required')
@section('heading', 'Check your email')
@section('subheading', 'A six-digit verification code was sent to ' . \Illuminate\Support\Str::mask($user->email, '*', 2, max(1, strpos($user->email, '@') - 3)) . '.')

@section('form')
    <div class="alert alert-info mb-5">
        <x-heroicon-o-shield-check />
        <div>
            <p class="font-medium">Extra verification for {{ $user->roleLabel() }}s</p>
            <p class="text-[13px] mt-0.5 leading-relaxed">
                This account can approve leave and change employee records, so a
                second step is required at every sign-in.
            </p>
        </div>
    </div>

    <form method="POST" action="{{ route('two-factor.verify') }}" class="space-y-4">
        @csrf

        <div>
            <label for="code" class="label">Verification code</label>
            <input type="text" id="code" name="code"
                   inputmode="numeric" pattern="[0-9]*" maxlength="6"
                   required autofocus autocomplete="one-time-code"
                   class="input text-center text-2xl font-semibold tracking-[0.5em] tabular"
                   placeholder="······">
            <span class="hint">
                Expires {{ $expiresAt->diffForHumans() }}. The code can be used once.
            </span>
        </div>

        <button type="submit" class="btn btn-lg btn-primary w-full">
            Verify and continue
        </button>
    </form>

    <div class="mt-5 pt-5 border-t border-sand-200 flex items-center justify-between gap-3">
        <form method="POST" action="{{ route('two-factor.resend') }}">
            @csrf
            <button type="submit" class="btn btn-sm btn-secondary" @disabled($cooldown > 0)>
                <x-heroicon-o-arrow-path />
                {{ $cooldown > 0 ? "Resend in {$cooldown}s" : 'Resend code' }}
            </button>
        </form>

        <form method="POST" action="{{ route('two-factor.cancel') }}">
            @csrf
            <button type="submit" class="btn btn-sm btn-ghost">Sign in as someone else</button>
        </form>
    </div>
@endsection

@section('footer')
    Didn't get the email? Check your spam folder, or contact the HR Office.
@endsection
