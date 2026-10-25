{{-- Shared shell for every signed-out screen: login, two-factor challenge,
     forgot password and reset. Keeps them visually identical. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Sign in') | NexHRIS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.favicons')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-screen overflow-hidden">
<div class="auth-shell">

    {{-- ------------------------------------------------------------------
         Branding panel. The institution leads and the system follows, which
         is the order a college portal is read in.
         ------------------------------------------------------------------ --}}
    <div class="auth-brand hidden lg:flex lg:w-[58%] xl:w-[60%] flex-col px-16 py-12">

        {{-- The seal again, large and faint, the way it sits behind the text
             on a certificate. It fills the half of the panel the text does
             not reach. --}}
        <img src="{{ asset('images/ispsc-seal-640.png') }}" alt="" aria-hidden="true"
             class="auth-watermark">

        {{-- The institutional lockup: seal and name side by side, as a
             letterhead carries them. --}}
        <div class="flex items-start justify-between gap-8">
            <div class="flex items-center gap-5">
                <img src="{{ asset('images/ispsc-seal-640.png') }}" alt="ISPSC seal"
                     class="auth-seal h-[72px] w-[72px] shrink-0">

                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-gold-400">
                        Republic of the Philippines
                    </p>
                    <h1 class="mt-1 text-xl font-bold leading-tight tracking-tight">
                        Ilocos Sur Polytechnic State College
                    </h1>
                    <p class="mt-0.5 text-[13px] text-white/70">Tagudin Campus, Ilocos Sur</p>
                </div>
            </div>

            {{-- Philippine Standard Time, as government sites carry it. The
                 server's clock, not the visitor's: the offset is taken once
                 at load, so a wrong clock on a shared office PC still shows
                 the right time. Rendered on the server first, so it reads
                 correctly before Alpine starts. --}}
            <div class="hidden shrink-0 text-right xl:block"
                 x-data="{
                     offset: {{ now()->getTimestampMs() }} - Date.now(),
                     now: null,
                     tick() { this.now = new Date(Date.now() + this.offset) },
                     init() { this.tick(); setInterval(() => this.tick(), 1000) },
                 }">
                <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-gold-400">
                    Philippine Standard Time
                </p>
                <p class="mt-1 text-xl font-bold leading-tight tabular-nums"
                   x-text="now && now.toLocaleTimeString('en-US', { timeZone: 'Asia/Manila', hour: 'numeric', minute: '2-digit' })">
                    {{ now()->format('g:i A') }}
                </p>
                <p class="mt-0.5 text-[13px] text-white/70"
                   x-text="now && now.toLocaleDateString('en-US', { timeZone: 'Asia/Manila', weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })">
                    {{ now()->format('l, F j, Y') }}
                </p>
            </div>
        </div>

        <div class="auth-divider my-8"></div>

        {{-- The system, and what it holds. Centred in what is left so the
             panel has a middle to read from instead of two far edges. --}}
        <div class="flex flex-1 flex-col justify-center">
            <div class="mb-4 flex items-center gap-3">
                <span class="auth-rule"></span>
                <span class="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/60">
                    Official Campus System
                </span>
            </div>

            {{-- The system's own lockup: mark, then the wordmark with HRIS in
                 the seal's gold. --}}
            <div class="flex items-center gap-4">
                <img src="{{ asset('images/nexhris/nexhris-icon-on-maroon.svg') }}" alt=""
                     class="auth-seal h-16 w-16 shrink-0">
                <div>
                    <h2 class="text-[42px] font-extrabold leading-none tracking-tight">
                        Nex<span class="text-gold-400">HRIS</span>
                    </h2>
                    <p class="mt-1.5 text-[11px] font-semibold uppercase tracking-[0.2em] text-white/60">
                        Human Resource Information System
                    </p>
                </div>
            </div>

            {{-- No database query on this page: the sign-in screen has to
                 render even when the database is unreachable. --}}
            <p class="mt-4 max-w-xl text-[14.5px] leading-relaxed text-white/70">
                Employee records, leave and service credits for the staff of the
                campus — filed, approved and kept on the official forms.
            </p>

            <div class="mt-8 grid max-w-2xl grid-cols-2 gap-3">
                <div class="auth-tile">
                    <span class="auth-tile-icon"><x-heroicon-o-document-text /></span>
                    <div>
                        <span class="auth-tile-title">Personal Data Sheet</span>
                        <span class="auth-tile-note">Filed online and reviewed by HR</span>
                    </div>
                </div>

                <div class="auth-tile">
                    <span class="auth-tile-icon"><x-heroicon-o-check-badge /></span>
                    <div>
                        <span class="auth-tile-title">Leave approvals</span>
                        <span class="auth-tile-note">Dean, HR and the Campus Director</span>
                    </div>
                </div>

                <div class="auth-tile">
                    <span class="auth-tile-icon"><x-heroicon-o-book-open /></span>
                    <div>
                        <span class="auth-tile-title">Ledger cards</span>
                        <span class="auth-tile-note">Leave and service credits, to the official form</span>
                    </div>
                </div>

                <div class="auth-tile">
                    <span class="auth-tile-icon"><x-heroicon-o-identification /></span>
                    <div>
                        <span class="auth-tile-title">Digital ID</span>
                        <span class="auth-tile-note">With a QR code anyone can verify</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="auth-divider mb-5 mt-8"></div>

        {{-- New tabs, so a half-filled sign-in form is not thrown away. --}}
        <div class="flex items-center justify-between gap-6 text-xs text-white/60">
            <p>&copy; {{ date('Y') }} ISPSC Tagudin Campus · Human Resource Management Office</p>

            <nav class="flex shrink-0 items-center gap-4" aria-label="Legal">
                <a href="{{ route('terms') }}#privacy" target="_blank" rel="noopener" class="auth-footer-link">
                    <x-heroicon-o-lock-closed class="h-3.5 w-3.5" />
                    Data Privacy
                </a>
                <a href="{{ route('terms') }}" target="_blank" rel="noopener" class="auth-footer-link">
                    Terms and Conditions
                </a>
            </nav>
        </div>
    </div>

    {{-- ------------------------------------------------------------------
         Form panel
         ------------------------------------------------------------------ --}}
    <div class="auth-page auth-scroll flex w-full justify-center px-5 py-10 sm:px-12 lg:w-[42%] xl:w-[40%]">
        <div class="my-auto w-full max-w-[26rem]">

            {{-- Compact lockup for small screens, where the panel is hidden. --}}
            <div class="mb-8 flex flex-col items-center text-center lg:hidden">
                <img src="{{ asset('images/nexhris/nexhris-icon.svg') }}" alt="NexHRIS logo"
                     class="mb-3 h-16 w-16">
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-maroon-700">
                    ISPSC Tagudin Campus
                </p>
                <h1 class="mt-1 text-lg font-bold text-sand-900">NexHRIS</h1>
                <p class="text-xs text-sand-500">Human Resource Information System</p>
            </div>

            <div class="auth-card p-7 sm:p-8">
                <h2 class="text-xl font-semibold text-sand-900">@yield('heading')</h2>
                <p class="mb-6 mt-1.5 text-[13px] text-sand-500">@yield('subheading')</p>

                @if (session('success'))
                    <div class="alert alert-success mb-5">
                        <x-heroicon-o-check-circle />
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-error mb-5">
                        <x-heroicon-o-exclamation-triangle />
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                @yield('form')
            </div>

            @hasSection('footer')
                <div class="mt-5 text-center text-[13px] leading-relaxed text-sand-500">
                    @yield('footer')
                </div>
            @endif

            <p class="mt-6 text-center text-[11px] text-sand-400 lg:hidden">
                &copy; {{ date('Y') }} ISPSC Tagudin Campus
            </p>
        </div>
    </div>
</div>

@stack('scripts')
</body>
</html>
