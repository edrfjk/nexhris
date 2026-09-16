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
            {{-- The server prints a correct starting value, so the page is right
                 even with scripts off; the script below keeps it moving. --}}
            <span class="hint" data-expiry data-seconds="{{ $expiresIn }}">
                @if ($expiresIn > 0)
                    Expires in <span data-clock>{{ intdiv($expiresIn, 60) }}:{{ str_pad($expiresIn % 60, 2, '0', STR_PAD_LEFT) }}</span>.
                    The code can be used once.
                @else
                    This code has expired. Request a new one below.
                @endif
            </span>
        </div>

        <button type="submit" class="btn btn-lg btn-primary w-full">
            Verify and continue
        </button>
    </form>

    <div class="mt-5 pt-5 border-t border-sand-200 flex items-center justify-between gap-3">
        <form method="POST" action="{{ route('two-factor.resend') }}">
            @csrf
            <button type="submit" class="btn btn-sm btn-secondary"
                    data-resend data-seconds="{{ $cooldown }}"
                    @disabled($cooldown > 0)>
                <x-heroicon-o-arrow-path />
                <span data-label>{{ $cooldown > 0 ? "Resend in {$cooldown}s" : 'Resend code' }}</span>
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

@push('scripts')
<script>
    // Both timers used to be printed once by the server and never move, so the
    // page said "Resend in 58s" for ever and the button stayed disabled until
    // the page was reloaded.
    //
    // Each counts down against a deadline fixed at page load rather than by
    // subtracting one per tick. Browsers throttle timers in background tabs,
    // so a tick-counter left in another tab for a minute comes back still
    // saying 55s; a deadline comes back right.
    (function () {
        var started = Date.now();

        function remaining(el) {
            var total = parseInt(el.getAttribute('data-seconds'), 10) || 0;
            return Math.max(0, total - Math.floor((Date.now() - started) / 1000));
        }

        var resend = document.querySelector('[data-resend]');
        var expiry = document.querySelector('[data-expiry]');

        function tick() {
            if (resend) {
                var wait = remaining(resend);
                var label = resend.querySelector('[data-label]');

                resend.disabled = wait > 0;

                if (label) {
                    label.textContent = wait > 0 ? 'Resend in ' + wait + 's' : 'Resend code';
                }
            }

            if (expiry) {
                var left = remaining(expiry);

                if (left > 0) {
                    var clock = expiry.querySelector('[data-clock]');
                    var text = Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0');

                    if (clock) {
                        clock.textContent = text;
                    }
                } else if (! expiry.hasAttribute('data-done')) {
                    expiry.setAttribute('data-done', '');
                    expiry.textContent = 'This code has expired. Request a new one below.';
                }
            }

            var resendDone = ! resend || remaining(resend) === 0;
            var expiryDone = ! expiry || remaining(expiry) === 0;

            if (resendDone && expiryDone) {
                clearInterval(timer);
            }
        }

        var timer = setInterval(tick, 1000);
        tick();
    })();
</script>
@endpush
