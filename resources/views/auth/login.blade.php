<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | NexHRIS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-screen overflow-hidden">
<div class="flex h-screen w-screen">

    <!-- LEFT: Branding panel -->
    <div class="hidden lg:flex lg:w-1/2 bg-maroon-900 relative flex-col items-center justify-center text-white px-12">
        <div class="absolute inset-0 bg-gradient-to-br from-maroon-900 via-maroon-800 to-maroon-900"></div>

        <div class="relative z-10 flex flex-col items-center text-center max-w-md">
            <img src="{{ asset('images/ispsc-logo.png') }}" alt="ISPSC Seal"
                 class="w-28 h-28 rounded-full bg-white p-2 shadow-lg mb-6">

            <h1 class="text-3xl font-bold tracking-wide mb-1">NexHRIS</h1>
            <p class="text-sm text-gray-200 mb-6">Smart and Next-Generation Human Resource<br>Information System</p>

            <div class="w-16 h-0.5 bg-ispscgold mb-6"></div>

            <p class="text-sm text-gray-300 leading-relaxed">
                Ilocos Sur Polytechnic State College<br>
                Tagudin Campus, Tagudin, Ilocos Sur
            </p>
        </div>

        <p class="absolute bottom-6 text-xs text-gray-400 z-10">
            &copy; {{ date('Y') }} ISPSC Tagudin Campus. All rights reserved.
        </p>
    </div>

    <!-- RIGHT: Login form -->
    <div class="w-full lg:w-1/2 flex items-center justify-center bg-gray-50 px-6 sm:px-12">
        <div class="w-full max-w-sm">

            <!-- Mobile-only compact branding (shown when left panel is hidden) -->
            <div class="flex lg:hidden flex-col items-center mb-8">
                <img src="{{ asset('images/ispsc-logo.png') }}" alt="ISPSC Seal" class="w-16 h-16 mb-2">
                <h1 class="text-lg font-bold text-maroon-800">NexHRIS</h1>
            </div>

            <h2 class="text-2xl font-bold text-gray-800 mb-1">Welcome back</h2>
            <p class="text-sm text-gray-500 mb-6">Sign in to your HR account to continue.</p>

            @if ($errors->any())
                <div class="mb-4 flex items-start gap-2 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                    <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                    </svg>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            @if (session('status'))
                <div class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-4" autocomplete="off">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus
                           autocomplete="username"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-maroon-700 focus:border-transparent transition"
                           placeholder="you@ispsc.edu.ph">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <div class="relative">
                        <input :type="show ? 'text' : 'password'" name="password" required
                               x-data
                               autocomplete="current-password"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-maroon-700 focus:border-transparent transition"
                               placeholder="••••••••"
                               id="password-input">
                        <button type="button" id="toggle-password"
                                class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600">
                            <svg id="eye-icon" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" name="remember" class="rounded border-gray-300 text-maroon-700 focus:ring-maroon-700">
                        Remember me
                    </label>
                </div>

                <button type="submit"
                        class="w-full bg-maroon-800 text-white py-2.5 rounded-lg text-sm font-semibold hover:bg-maroon-900 transition">
                    Log In
                </button>
            </form>

            <p class="mt-6 text-xs text-gray-400 text-center">
                Access is provided by the HR Office. Contact HR if you don't have an account or need a password reset.
            </p>
        </div>
    </div>
</div>

<script>
    document.getElementById('toggle-password').addEventListener('click', function () {
        const input = document.getElementById('password-input');
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
    });
</script>
</body>
</html>