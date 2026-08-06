<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'NexHRIS') | ISPSC Tagudin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script defer src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/3.13.5/cdn.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-gray-50 text-gray-800">
<div class="flex min-h-screen" x-data="{ sidebarOpen: false }" x-cloak>

    <!-- Mobile overlay -->
    <div x-show="sidebarOpen" @click="sidebarOpen = false"
         class="fixed inset-0 bg-black/40 z-30 lg:hidden" x-transition.opacity></div>

    <!-- Sidebar -->
    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
           class="fixed inset-y-0 left-0 z-40 w-64 h-screen bg-maroon-900 text-white flex flex-col transition-transform duration-200">

        <div class="flex flex-col items-center text-center gap-2 px-4 py-6 border-b border-maroon-800 flex-shrink-0">
            <img src="{{ asset('images/ispsc-logo.png') }}" alt="ISPSC Seal"
                 class="w-20 h-20 rounded-full object-cover drop-shadow-lg">
            <div class="leading-tight">
                <p class="font-bold text-base">NexHRIS</p>
                <p class="text-[11px] text-gray-300">ISPSC Tagudin Campus</p>
            </div>
        </div>

        <nav class="flex-1 px-3 py-4 space-y-6 text-sm overflow-y-auto">
            @if (auth()->user()->isAdmin())
                <div>
                    <p class="px-3 text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1.5">Overview</p>
                    <a href="{{ route('admin.dashboard') }}"
                       class="flex items-center gap-2.5 px-3 py-2 rounded-lg hover:bg-maroon-800 transition {{ request()->routeIs('admin.dashboard') ? 'bg-maroon-800' : '' }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/></svg>
                        Dashboard
                    </a>
                </div>

                <div>
                    <p class="px-3 text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1.5">People</p>
                    <a href="{{ route('admin.employees.index') }}"
                       class="flex items-center gap-2.5 px-3 py-2 rounded-lg hover:bg-maroon-800 transition {{ request()->routeIs('admin.employees.*') ? 'bg-maroon-800' : '' }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                        Employee Accounts
                    </a>
                </div>

                <div>
                    <p class="px-3 text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1.5">Records</p>
                    <a href="{{ route('admin.pds.index') }}"
                       class="flex items-center gap-2.5 px-3 py-2 rounded-lg hover:bg-maroon-800 transition {{ request()->routeIs('admin.pds.*') ? 'bg-maroon-800' : '' }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        PDS Requests
                    </a>
                    <a href="{{ route('admin.leave.index') }}"
                       class="flex items-center gap-2.5 px-3 py-2 rounded-lg hover:bg-maroon-800 transition {{ request()->routeIs('admin.leave.*') ? 'bg-maroon-800' : '' }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Leave Management
                    </a>
                    <a href="{{ route('admin.policies.index') }}"
                       class="flex items-center gap-2.5 px-3 py-2 rounded-lg hover:bg-maroon-800 transition {{ request()->routeIs('admin.policies.*') ? 'bg-maroon-800' : '' }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                        HR Policies
                    </a>
                </div>
            @else
                <div>
                    <p class="px-3 text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1.5">Menu</p>
                    <a href="{{ route('employee.dashboard') }}"
                       class="flex items-center gap-2.5 px-3 py-2 rounded-lg hover:bg-maroon-800 transition {{ request()->routeIs('employee.dashboard') ? 'bg-maroon-800' : '' }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6z"/></svg>
                        Dashboard
                    </a>
                    <a href="{{ route('pds.edit') }}"
                       class="flex items-center gap-2.5 px-3 py-2 rounded-lg hover:bg-maroon-800 transition {{ request()->routeIs('pds.*') ? 'bg-maroon-800' : '' }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Personal Data Sheet
                    </a>
                    <a href="{{ route('leave.index') }}"
                       class="flex items-center gap-2.5 px-3 py-2 rounded-lg hover:bg-maroon-800 transition {{ request()->routeIs('leave.*') ? 'bg-maroon-800' : '' }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        My Leave
                    </a>
                    <a href="#" class="flex items-center gap-2.5 px-3 py-2 rounded-lg hover:bg-maroon-800 transition">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                        HR Policies
                    </a>
                </div>
            @endif
        </nav>

        <form method="POST" action="{{ route('logout') }}" class="p-3 border-t border-maroon-800 flex-shrink-0">
            @csrf
            <button class="w-full flex items-center gap-2.5 text-left text-sm px-3 py-2 rounded-lg hover:bg-maroon-800 transition">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.25 9V5.25A2.25 2.25 0 0110.5 3h6a2.25 2.25 0 012.25 2.25v13.5A2.25 2.25 0 0116.5 21h-6a2.25 2.25 0 01-2.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/></svg>
                Log Out
            </button>
        </form>
    </aside>

    <!-- Main content -->
    <div class="flex-1 flex flex-col min-w-0 lg:ml-64">
        <header class="bg-white border-b border-gray-100 px-6 py-3.5 flex justify-between items-center sticky top-0 z-20">
            <div class="flex items-center gap-4 min-w-0">
                <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden text-gray-500 hover:text-gray-700 flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/>
                    </svg>
                </button>

                @stack('page-title')
            </div>

            <!-- Profile block, top right -->
            <div class="flex items-center gap-3 flex-shrink-0">
                <div class="w-10 h-10 rounded-full bg-maroon-800 text-white flex items-center justify-center text-sm font-semibold flex-shrink-0">
                    {{ strtoupper(substr(auth()->user()->name ?? '?', 0, 1)) }}
                </div>
                <div class="text-left hidden sm:block">
                    <p class="text-sm font-semibold text-gray-800 leading-tight">{{ auth()->user()->name ?? '' }}</p>
                    <span class="inline-block mt-0.5 text-[11px] font-medium px-2 py-0.5 rounded-full
                                {{ auth()->user()->isAdmin() ? 'bg-maroon-50 text-maroon-800' : 'bg-blue-50 text-blue-700' }}">
                        {{ auth()->user()->isAdmin() ? 'HR Administrator' : 'Employee' }}
                    </span>
                </div>
            </div>
        </header>

        <main class="p-6">
            @if (session('success'))
                <div data-flash class="mb-4 flex items-center gap-2 rounded-lg bg-green-50 border border-green-200 px-4 py-2.5 text-sm text-green-700">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.5 12.75l6 6 9-13.5"/></svg>
                    {{ session('success') }}
                </div>
            @endif
            @if (session('error'))
                <div data-flash class="mb-4 flex items-center gap-2 rounded-lg bg-red-50 border border-red-200 px-4 py-2.5 text-sm text-red-700">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<script>
    setTimeout(() => {
        document.querySelectorAll('[data-flash]').forEach(el => {
            el.style.transition = 'opacity 0.4s ease';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 400);
        });
    }, 4000);
</script>
</body>
</html>