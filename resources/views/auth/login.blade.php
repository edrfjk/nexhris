@extends('layouts.guest')

@section('title', 'Sign in')
@section('heading', 'Sign in')
@section('subheading', 'Use the credentials issued by the HR Office.')

@section('form')
    @unless (\App\Services\TwoFactorService::isEnabled())
        {{-- Deliberately loud. A disabled security control that nobody can see
             is the kind that reaches production by accident. --}}
        <div class="alert alert-warning mb-5">
            <x-heroicon-o-shield-exclamation />
            <div>
                <p class="font-semibold">Two-factor verification is switched OFF</p>
                <p class="mt-0.5 text-[13px] leading-relaxed">
                    Development mode — HR, Deans and the Campus Director are signing in
                    with a password alone. Set <code class="text-xs">TWO_FACTOR_ENABLED=true</code>
                    in <code class="text-xs">.env</code> to restore it.
                </p>
            </div>
        </div>
    @endunless

    {{-- No autocomplete="off" on the form: the fields name themselves as
         username and current-password, which is what lets a password manager
         fill them. --}}
    <form method="POST" action="{{ route('login') }}" class="space-y-4"
          x-data="{ agreed: {{ old('terms') ? 'true' : 'false' }}, showTerms: false, submitting: false }"
          @submit="submitting = true">
        @csrf

        {{-- Every sign-in failure is reported against the email, and the
             message never says which field was wrong, so both are marked. --}}
        @php($failed = $errors->has('email') || $errors->has('password'))

        <div>
            <label for="email" class="label">Email address</label>
            <div class="relative">
                <x-heroicon-o-envelope
                    class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-sand-400" />
                <input type="email" id="email" name="email" value="{{ old('email') }}"
                       required autocomplete="username"
                       @if (! old('email')) autofocus @endif
                       @if ($failed) aria-invalid="true" @endif
                       class="input pl-9 @if ($failed) input-error @endif" placeholder="you@ispsc.edu.ph">
            </div>
        </div>

        {{-- Caps Lock is the commonest reason a correct password is rejected,
             and the field hides the evidence. --}}
        <div x-data="{ show: false, caps: false }">
            <label for="password" class="label">Password</label>

            <div class="relative">
                <x-heroicon-o-lock-closed
                    class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-sand-400" />

                {{-- After a failed attempt the email is kept, so the cursor
                     goes where the retyping happens. --}}
                <input :type="show ? 'text' : 'password'" id="password" name="password"
                       required autocomplete="current-password"
                       @if (old('email')) autofocus @endif
                       @if ($failed) aria-invalid="true" @endif
                       @keyup="caps = $event.getModifierState && $event.getModifierState('CapsLock')"
                       @keydown="caps = $event.getModifierState && $event.getModifierState('CapsLock')"
                       class="input pl-9 pr-11 @if ($failed) input-error @endif" placeholder="••••••••">

                <button type="button" @click="show = !show"
                        class="absolute inset-y-0 right-0 flex items-center px-3 text-sand-400 transition-colors hover:text-maroon-800"
                        :aria-label="show ? 'Hide password' : 'Show password'">
                    <x-heroicon-o-eye x-show="!show" class="h-4 w-4" />
                    <x-heroicon-o-eye-slash x-show="show" x-cloak class="h-4 w-4" />
                </button>
            </div>

            <p x-show="caps" x-cloak
               class="hint flex items-center gap-1.5 text-gold-800">
                <x-heroicon-o-exclamation-triangle class="h-3.5 w-3.5" />
                Caps Lock is on.
            </p>
        </div>

        {{-- For a privileged account the cookie is issued only after the
             emailed code is verified — see LoginController. --}}
        <div class="flex items-center justify-between gap-3 pt-1">
            <label class="flex cursor-pointer items-center gap-2 text-[13px] text-sand-700">
                <input type="checkbox" name="remember" value="1"
                       @if (old('remember')) checked @endif
                       class="h-4 w-4 rounded border-sand-300 text-maroon-800 focus:ring-maroon-500">
                Keep me signed in
            </label>

            <a href="{{ route('password.request') }}"
               class="text-[13px] font-medium text-maroon-700 transition-colors hover:text-maroon-900">
                Forgot password?
            </a>
        </div>

        {{-- The agreement. Required by the server as well, so turning the
             attribute off in the browser buys nothing. --}}
        <div class="rounded-lg border border-sand-200 bg-sand-50 p-3.5">
            <label class="flex cursor-pointer items-start gap-2.5 text-[13px] leading-relaxed text-sand-700">
                <input type="checkbox" name="terms" value="1" x-model="agreed" required
                       @if (old('terms')) checked @endif
                       class="mt-0.5 h-4 w-4 shrink-0 rounded border-sand-300 text-maroon-800 focus:ring-maroon-500">
                <span>
                    I agree with the
                    {{-- Opens the dialog when Alpine is running; falls back to
                         the standalone page in a new tab when it is not, so the
                         terms stay reachable either way and nothing typed into
                         this form is lost. --}}
                    <a href="{{ route('terms') }}" target="_blank" rel="noopener"
                       @click.prevent="showTerms = true"
                       class="font-semibold text-maroon-700 underline underline-offset-2 transition-colors hover:text-maroon-900">Terms
                        and Conditions</a>
                </span>
            </label>
        </div>

        {{-- Disabled until the box is ticked, and once pressed, so a second
             click cannot send a second attempt into the lockout counter. --}}
        <div>
            <button type="submit" class="btn btn-lg btn-primary w-full" :disabled="!agreed || submitting">
                <x-heroicon-o-arrow-right-end-on-rectangle x-show="!submitting" />
                <svg x-show="submitting" x-cloak class="animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" class="opacity-25" />
                    <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                </svg>
                <span x-text="submitting ? 'Signing in…' : 'Sign in'">Sign in</span>
            </button>

            {{-- A muted button with no reason given reads as a broken one. --}}
            <p x-show="!agreed" x-cloak class="hint text-center">
                Accept the Terms and Conditions to continue.
            </p>
        </div>

        {{-- --------------------------------------------------------------
             Terms dialog. Same wording as /terms — one partial, included in
             both places.
             -------------------------------------------------------------- --}}
        <div x-show="showTerms" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
             role="dialog" aria-modal="true" aria-labelledby="terms-title"
             @keydown.escape.window="showTerms = false">

            <div class="absolute inset-0 bg-sand-900/50" @click="showTerms = false"></div>

            <div class="relative flex max-h-full w-full max-w-2xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl">

                <div class="flex items-start justify-between gap-4 border-b border-sand-200 px-6 py-4">
                    <div>
                        <h3 id="terms-title" class="text-base font-semibold text-sand-900">
                            Terms and Conditions
                        </h3>
                        <p class="mt-0.5 text-xs text-sand-500">
                            NexHRIS &middot; ISPSC Tagudin Campus
                        </p>
                    </div>

                    <button type="button" @click="showTerms = false"
                            class="-mr-1.5 rounded-lg p-1.5 text-sand-400 transition-colors hover:bg-sand-100 hover:text-sand-700"
                            aria-label="Close">
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>

                <div class="overflow-y-auto px-6 py-5">
                    @include('legal.partials.terms-body')
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-sand-200 bg-sand-50 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <a href="{{ route('terms') }}" target="_blank" rel="noopener"
                       class="text-[13px] font-medium text-maroon-700 underline underline-offset-2 hover:text-maroon-900">
                        Open as a printable page
                    </a>

                    <div class="flex gap-2">
                        <button type="button" @click="showTerms = false" class="btn btn-md btn-secondary">
                            Close
                        </button>
                        <button type="button" @click="agreed = true; showTerms = false" class="btn btn-md btn-primary">
                            <x-heroicon-o-check />
                            I agree
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    {{-- Who the account belongs to, so nobody wonders which login this is. --}}
    <p class="mt-6 flex items-start gap-2 border-t border-sand-100 pt-5 text-[12px] leading-relaxed text-sand-500">
        <x-heroicon-o-shield-check class="mt-0.5 h-4 w-4 shrink-0 text-forest-700" />
        <span>
            For employees, Deans, the Campus Director and the HR Office of
            ISPSC Tagudin Campus.
            @if (\App\Services\TwoFactorService::isEnabled())
                Deans, the Campus Director and HR will also be asked for a code
                sent to their email.
            @endif
        </span>
    </p>
@endsection

@section('footer')
    Accounts are issued by the HR Office. Contact them if you need access or a password reset.
@endsection

{{-- Alpine comes from the bundle the guest layout already loads. --}}
