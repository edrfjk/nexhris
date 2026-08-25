{{-- Shared shell for every signed-out screen: login, two-factor challenge,
     forgot password and reset. Keeps them visually identical. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Sign in') | NexHRIS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
<div class="flex min-h-screen">

    {{-- Branding panel --}}
    <div class="hidden lg:flex lg:w-[45%] bg-maroon-900 flex-col items-center justify-center text-white px-12 relative">
        <div class="flex flex-col items-center text-center max-w-sm">
            <img src="{{ asset('images/ispsc-logo.png') }}" alt="ISPSC Seal"
                 class="w-28 h-28 rounded-full bg-white p-1.5 shadow-lift mb-7">

            <h1 class="text-3xl font-bold tracking-tight mb-2">NexHRIS</h1>
            <p class="text-sm text-white/70 leading-relaxed">
                Human Resource Information System
            </p>

            <div class="w-12 h-0.5 bg-gold-400 my-7 rounded-full"></div>

            <p class="text-sm text-white/60 leading-relaxed">
                Ilocos Sur Polytechnic State College<br>
                Tagudin Campus, Ilocos Sur
            </p>
        </div>

        <p class="absolute bottom-8 text-xs text-white/40">
            &copy; {{ date('Y') }} ISPSC Tagudin Campus
        </p>
    </div>

    {{-- Form panel --}}
    <div class="w-full lg:w-[55%] flex items-center justify-center bg-sand-100 px-5 py-10 sm:px-12">
        <div class="w-full max-w-[26rem]">

            {{-- Compact branding for small screens --}}
            <div class="flex lg:hidden flex-col items-center mb-8">
                <img src="{{ asset('images/ispsc-logo.png') }}" alt="ISPSC Seal" class="w-16 h-16 mb-3">
                <h1 class="text-lg font-bold text-maroon-800">NexHRIS</h1>
                <p class="text-xs text-sand-500">ISPSC Tagudin Campus</p>
            </div>

            <div class="card p-7 sm:p-8">
                <h2 class="text-xl font-semibold text-sand-900">@yield('heading')</h2>
                <p class="text-[13px] text-sand-500 mt-1.5 mb-6">@yield('subheading')</p>

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

            @hasSection("footer")
                <div class="mt-5 text-center text-[13px] text-sand-500">
                    @yield('footer')
                </div>
            @endif
        </div>
    </div>
</div>

@stack('scripts')
</body>
</html>
