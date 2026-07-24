<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'NexHRIS') | ISPSC Tagudin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-800">
<div class="flex min-h-screen">
    <!-- Sidebar -->
    <aside class="w-64 bg-maroon-800 text-white flex flex-col">
        <div class="flex items-center gap-3 px-4 py-5 border-b border-maroon-700">
            <img src="{{ asset('images/ispsc-logo.png') }}" alt="ISPSC Seal" class="w-12 h-12 rounded-full bg-white p-1">
            <div class="leading-tight">
                <p class="font-bold text-sm">NexHRIS</p>
                <p class="text-xs text-gray-200">ISPSC Tagudin Campus</p>
            </div>
        </div>

        <nav class="flex-1 px-2 py-4 space-y-1 text-sm">
            @if (auth()->user()->isAdmin())
                {{-- ===== ADMIN NAV ===== --}}
                <a href="{{ route('admin.dashboard') }}"
                   class="block px-4 py-2 rounded hover:bg-maroon-700 {{ request()->routeIs('admin.dashboard') ? 'bg-maroon-700' : '' }}">
                    Dashboard
                </a>
                <a href="{{ route('admin.employees.index') }}"
                   class="block px-4 py-2 rounded hover:bg-maroon-700 {{ request()->routeIs('admin.employees.*') ? 'bg-maroon-700' : '' }}">
                    Employee Accounts
                </a>
                <a href="#" class="block px-4 py-2 rounded hover:bg-maroon-700">PDS Requests</a>
                <a href="{{ route('admin.leave.pending') }}"
                   class="block px-4 py-2 rounded hover:bg-maroon-700 {{ request()->routeIs('admin.leave.*') ? 'bg-maroon-700' : '' }}">
                    Leave Applications
                </a>
                <a href="#" class="block px-4 py-2 rounded hover:bg-maroon-700">HR Policies</a>
            @else
                {{-- ===== EMPLOYEE NAV ===== --}}
                <a href="{{ route('employee.dashboard') }}"
                   class="block px-4 py-2 rounded hover:bg-maroon-700 {{ request()->routeIs('employee.dashboard') ? 'bg-maroon-700' : '' }}">
                    Dashboard
                </a>
                <a href="{{ route('pds.edit') }}"
                   class="block px-4 py-2 rounded hover:bg-maroon-700 {{ request()->routeIs('pds.*') ? 'bg-maroon-700' : '' }}">
                    Personal Data Sheet
                </a>
                <a href="{{ route('leave.index') }}"
                   class="block px-4 py-2 rounded hover:bg-maroon-700 {{ request()->routeIs('leave.*') ? 'bg-maroon-700' : '' }}">
                    My Leave
                </a>
                <a href="#" class="block px-4 py-2 rounded hover:bg-maroon-700">HR Policies</a>
            @endif
        </nav>

        <form method="POST" action="{{ route('logout') }}" class="p-4 border-t border-maroon-700">
            @csrf
            <button class="w-full text-left text-sm px-4 py-2 rounded hover:bg-maroon-700">Log Out</button>
        </form>
    </aside>

    <!-- Main content -->
    <div class="flex-1 flex flex-col">
        <header class="bg-white shadow px-6 py-4 flex justify-between items-center">
            <h1 class="text-lg font-semibold">@yield('title', 'Dashboard')</h1>
            <span class="text-sm text-gray-500">{{ auth()->user()->name ?? '' }}</span>
        </header>

        <main class="p-6">
            @if (session('success'))
                <div class="mb-4 rounded bg-green-100 text-green-800 px-4 py-2 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>
</body>
</html>