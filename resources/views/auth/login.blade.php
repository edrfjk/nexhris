<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | NexHRIS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        html, body { height: 100%; margin: 0; }
        .icon { width: 16px; height: 16px; flex-shrink: 0; }
    </style>
</head>
<body class="font-sans bg-[#f3f2ee] text-gray-800">

    <div class="min-h-screen flex flex-col">

        <!-- Top bar: seal + system identity, like a real agency header -->
        <header class="w-full bg-white border-b border-gray-200">
            <div class="max-w-5xl mx-auto flex items-center gap-4 px-6 py-3">
                <img src="{{ asset('images/ispsc-logo.png') }}" alt="ISPSC Seal" class="w-11 h-11 object-contain">
                <div class="leading-tight">
                    <p class="text-[13px] font-semibold text-gray-800">Ilocos Sur Polytechnic State College</p>
                    <p class="text-[11px] text-gray-500">Tagudin Campus &middot; Human Resource Information System</p>
                </div>
                <div class="ml-auto hidden sm:block text-right">
                    <p class="text-[11px] text-gray-400">NexHRIS v1.0</p>
                </div>
            </div>
        </header>

        <!-- Official tricolor strip, thin and flat -->
        <div class="w-full h-[3px] flex">
            <div class="flex-1 bg-[#7a1f2b]"></div>
            <div class="flex-1 bg-[#d4a017]"></div>
            <div class="flex-1 bg-[#2f5233]"></div>
        </div>

        <!-- Main content -->
        <main class="flex-1 flex items-center justify-center px-4 py-10">
            <div class="w-full max-w-sm">

                <div class="bg-white border border-gray-200 rounded-md">
                    <div class="px-8 pt-8 pb-6">
                        <h1 class="text-lg font-semibold text-gray-800">Employee login</h1>
                        <p class="text-[13px] text-gray-500 mt-1">
                            Access your HR records using your official ISPSC credentials.
                        </p>
                    </div>

                    @if ($errors->any())
                        <div class="mx-8 mb-4 flex items-start gap-2 text-[13px] text-[#7a1f2b] bg-[#fbeeee] border border-[#f0d5d5] rounded px-3 py-2">
                            <svg class="icon mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>{{ $errors->first() }}</span>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}" class="px-8 pb-8 space-y-4">
                        @csrf

                        <div>
                            <label class="block text-[13px] font-medium text-gray-700 mb-1">Email address</label>
                            <input type="email" name="email" value="{{ old('email') }}" required autofocus
                                   placeholder="you@ispsc.edu.ph"
                                   class="w-full border border-gray-300 rounded px-3 py-2 text-sm text-gray-800
                                          placeholder:text-gray-400
                                          focus:outline-none focus:ring-1 focus:ring-[#7a1f2b] focus:border-[#7a1f2b]
                                          transition">
                        </div>

                        <div>
                            <label class="block text-[13px] font-medium text-gray-700 mb-1">Password</label>
                            <input type="password" name="password" required
                                   placeholder="Enter your password"
                                   class="w-full border border-gray-300 rounded px-3 py-2 text-sm text-gray-800
                                          placeholder:text-gray-400
                                          focus:outline-none focus:ring-1 focus:ring-[#7a1f2b] focus:border-[#7a1f2b]
                                          transition">
                        </div>

                        <div class="flex items-center justify-between text-[13px] pt-1">
                            <label class="flex items-center gap-2 text-gray-600 cursor-pointer">
                                <input type="checkbox" name="remember" class="rounded border-gray-300 text-[#7a1f2b] focus:ring-[#7a1f2b]">
                                Remember me
                            </label>
                            <a href="#" class="text-[#7a1f2b] hover:underline">Forgot password?</a>
                        </div>

                        <button type="submit"
                                class="w-full bg-[#7a1f2b] text-white py-2 rounded text-sm font-medium
                                       hover:bg-[#611723] transition">
                            Sign in
                        </button>
                    </form>
                </div>

                <!-- Institutional notices below the card, not inside it -->
                <div class="mt-5 text-center space-y-1.5">
                    <p class="text-[12px] text-gray-500">
                        For access issues, contact the MIS Office at
                        <span class="text-gray-700">mis@ispsc-tagudin.edu.ph</span>
                    </p>
                    <p class="text-[11px] text-gray-400">
                        This system is for authorized ISPSC personnel only. Unauthorized access is prohibited under RA 10175.
                    </p>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="w-full border-t border-gray-200 bg-white">
            <div class="max-w-5xl mx-auto px-6 py-4 flex flex-col sm:flex-row items-center justify-between gap-2">
                <p class="text-[11px] text-gray-400">
                    &copy; {{ date('Y') }} Ilocos Sur Polytechnic State College — Tagudin Campus
                </p>
                <div class="flex gap-4 text-[11px] text-gray-400">
                    <a href="#" class="hover:text-gray-600">Data Privacy Notice</a>
                    <a href="#" class="hover:text-gray-600">Terms of Use</a>
                    <a href="#" class="hover:text-gray-600">Help Desk</a>
                </div>
            </div>
        </footer>
    </div>

</body>
</html>