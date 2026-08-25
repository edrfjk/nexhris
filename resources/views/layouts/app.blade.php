@php
    $user = auth()->user();
    $reviewQueue = 0;

    // Badge on the review link, so a Dean or the Campus Director can see at a
    // glance that something is waiting on them.
    if ($user?->isReviewer()) {
        $reviewQueue = app(\App\Services\LeaveWorkflowService::class)->queueFor($user)->count();
    }

@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'NexHRIS') | ISPSC Tagudin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script defer src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/3.13.5/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body>
<div class="flex min-h-screen" x-data="{ sidebarOpen: false }" x-cloak>

    <!-- Mobile overlay -->
    <div x-show="sidebarOpen" @click="sidebarOpen = false"
         class="fixed inset-0 bg-sand-900/50 backdrop-blur-sm z-30 lg:hidden" x-transition.opacity></div>

    <!-- ============================================================
         SIDEBAR
         ============================================================ -->
    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
           class="fixed inset-y-0 left-0 z-40 w-64 h-screen flex flex-col
                  transition-transform duration-300 ease-smooth bg-maroon-900 text-white">

        {{-- Seal, system name, campus — the standard institutional lockup. --}}
        <div class="shrink-0 flex items-center gap-3 px-5 h-16 border-b border-white/10">
            <img src="{{ asset('images/ispsc-logo.png') }}" alt="ISPSC Seal"
                 class="w-10 h-10 rounded-full object-cover bg-white p-0.5 shadow-soft">
            <div class="min-w-0 leading-tight">
                <p class="text-sm font-bold tracking-wide">NexHRIS</p>
                <p class="text-[10px] text-white/60 truncate">ISPSC Tagudin Campus</p>
            </div>
        </div>

        {{-- Ordered the same way for every role: what is waiting on you,
             then what you manage, then your own records, then shared
             resources, then your account. --}}
        <nav class="nav-scroll flex-1 py-2 overflow-y-auto">

            @if ($user->isAdmin())
                {{-- ---------------- HR ADMINISTRATOR ---------------- --}}
                {{-- This is the system administrator's account, not a member
                     of staff: no My Leave, ledger or PDS here. --}}
                <x-nav.section label="Overview">
                    <x-nav.item :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')" icon="squares-2x2">
                        Dashboard
                    </x-nav.item>
                </x-nav.section>

                <x-nav.section label="People">
                    <x-nav.item :href="route('admin.employees.index')" :active="request()->routeIs('admin.employees.*')" icon="users">
                        Employee Accounts
                    </x-nav.item>
                    <x-nav.item :href="route('admin.colleges.index')" :active="request()->routeIs('admin.colleges.*')" icon="building-library">
                        Colleges &amp; Departments
                    </x-nav.item>
                </x-nav.section>

                <x-nav.section label="Leave Management">
                    <x-nav.item :href="route('admin.leave.review.index')" :active="request()->routeIs('admin.leave.review.*')" icon="inbox-arrow-down" :badge="$reviewQueue">
                        Leave Reviews
                    </x-nav.item>
                    <x-nav.item :href="route('admin.leave.index')" :active="request()->routeIs('admin.leave.index') || request()->routeIs('admin.leave.ledger')" icon="book-open">
                        Ledger Cards
                    </x-nav.item>
                    <x-nav.item :href="route('admin.leave.calendar')" :active="request()->routeIs('admin.leave.calendar')" icon="calendar-days">
                        Leave Calendar
                    </x-nav.item>
                </x-nav.section>

                <x-nav.section label="Records">
                    <x-nav.item :href="route('admin.pds.index')" :active="request()->routeIs('admin.pds.*')" icon="document-text">
                        PDS Requests
                    </x-nav.item>
                    <x-nav.item :href="route('admin.leave.templates.index')" :active="request()->routeIs('admin.leave.templates.*')" icon="arrow-up-tray">
                        Form Templates
                    </x-nav.item>
                </x-nav.section>

                <x-nav.section label="Publishing">
                    <x-nav.item :href="route('admin.announcements.index')" :active="request()->routeIs('admin.announcements.*')" icon="megaphone">
                        Announcements
                    </x-nav.item>
                    <x-nav.item :href="route('admin.policies.index')" :active="request()->routeIs('admin.policies.*')" icon="clipboard-document-list">
                        HR Policies
                    </x-nav.item>
                </x-nav.section>

                <x-nav.section label="System">
                    <x-nav.item :href="route('admin.activity-logs.index')" :active="request()->routeIs('admin.activity-logs.*')" icon="shield-check">
                        Activity Log
                    </x-nav.item>
                    <x-nav.item :href="route('profile.edit')" :active="request()->routeIs('profile.*')" icon="user-circle">
                        My Profile
                    </x-nav.item>
                </x-nav.section>

            @elseif ($user->isDean() || $user->isCampusDirector())
                {{-- ---------------- DEAN / CAMPUS DIRECTOR ---------------- --}}
                <x-nav.section label="Overview">
                    <x-nav.item :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')" icon="squares-2x2">
                        Dashboard
                    </x-nav.item>
                </x-nav.section>

                <x-nav.section label="Needs Review">
                    <x-nav.item :href="route('admin.leave.review.index')" :active="request()->routeIs('admin.leave.review.*')" icon="inbox-arrow-down" :badge="$reviewQueue">
                        Awaiting My Review
                    </x-nav.item>
                </x-nav.section>

                <x-nav.section label="Oversight">
                    {{-- Ledger cards are HR's record. A Dean approves leave
                         but does not hold other people's credit balances. --}}
                    @if ($user->isCampusDirector())
                        <x-nav.item :href="route('admin.leave.index')" :active="request()->routeIs('admin.leave.index') || request()->routeIs('admin.leave.ledger')" icon="book-open">
                            Employee Ledgers
                        </x-nav.item>
                    @endif
                    <x-nav.item :href="route('admin.leave.calendar')" :active="request()->routeIs('admin.leave.calendar')" icon="calendar-days">
                        Leave Calendar
                    </x-nav.item>
                </x-nav.section>

                {{-- A Dean and the Campus Director take leave like anyone
                     else — the approval chain already skips whichever stage
                     is their own — so their own records belong here too. --}}
                <x-nav.section label="My Records">
                    <x-nav.item :href="route('leave.index')" :active="request()->routeIs('leave.index')" icon="calendar-days">
                        My Leave
                    </x-nav.item>
                    <x-nav.item :href="route('leave.ledger.mine')" :active="request()->routeIs('leave.ledger.*')" icon="book-open">
                        My Leave Ledger
                    </x-nav.item>
                    <x-nav.item :href="route('pds.edit')" :active="request()->routeIs('pds.*')" icon="document-text">
                        My Personal Data Sheet
                    </x-nav.item>
                </x-nav.section>

                <x-nav.section label="Resources">
                    <x-nav.item :href="route('announcements.index')" :active="request()->routeIs('announcements.*')" icon="megaphone">
                        Announcements
                    </x-nav.item>
                    <x-nav.item :href="route('policies.index')" :active="request()->routeIs('policies.*')" icon="clipboard-document-list">
                        HR Policies
                    </x-nav.item>
                </x-nav.section>

                <x-nav.section label="Account">
                    <x-nav.item :href="route('my-id.show')" :active="request()->routeIs('my-id.*')" icon="identification">
                        My Digital ID
                    </x-nav.item>
                    <x-nav.item :href="route('profile.edit')" :active="request()->routeIs('profile.*')" icon="user-circle">
                        My Profile
                    </x-nav.item>
                </x-nav.section>

            @else
                {{-- ---------------- EMPLOYEE ---------------- --}}
                <x-nav.section label="Overview">
                    <x-nav.item :href="route('employee.dashboard')" :active="request()->routeIs('employee.dashboard')" icon="squares-2x2">
                        Dashboard
                    </x-nav.item>
                </x-nav.section>

                {{-- An employee's own records are the whole point of their
                     account, so they sit directly under the dashboard. --}}
                <x-nav.section label="My Records">
                    <x-nav.item :href="route('leave.index')" :active="request()->routeIs('leave.index')" icon="calendar-days">
                        My Leave
                    </x-nav.item>
                    <x-nav.item :href="route('leave.ledger.mine')" :active="request()->routeIs('leave.ledger.*')" icon="book-open">
                        My Leave Ledger
                    </x-nav.item>
                    <x-nav.item :href="route('pds.edit')" :active="request()->routeIs('pds.*')" icon="document-text">
                        My Personal Data Sheet
                    </x-nav.item>
                </x-nav.section>

                <x-nav.section label="Resources">
                    <x-nav.item :href="route('announcements.index')" :active="request()->routeIs('announcements.*')" icon="megaphone">
                        Announcements
                    </x-nav.item>
                    <x-nav.item :href="route('policies.index')" :active="request()->routeIs('policies.*')" icon="clipboard-document-list">
                        HR Policies
                    </x-nav.item>
                </x-nav.section>

                <x-nav.section label="Account">
                    <x-nav.item :href="route('my-id.show')" :active="request()->routeIs('my-id.*')" icon="identification">
                        My Digital ID
                    </x-nav.item>
                    <x-nav.item :href="route('profile.edit')" :active="request()->routeIs('profile.*')" icon="user-circle">
                        My Profile
                    </x-nav.item>
                </x-nav.section>
            @endif
        </nav>

        <form method="POST" action="{{ route('logout') }}" class="shrink-0 border-t border-white/10 py-2">
            @csrf
            <button class="nav-link w-full text-left">
                <x-heroicon-o-arrow-left-on-rectangle />
                Log Out
            </button>
        </form>
    </aside>

    <!-- ============================================================
         MAIN
         ============================================================ -->
    <div class="flex-1 flex flex-col min-w-0 lg:ml-64">
        {{-- Fixed-height application bar: page title on the left, the signed-in
             account on the right. Same height as the sidebar's brand block. --}}
        <header class="sticky top-0 z-20 bg-white/90 backdrop-blur-sm border-b border-sand-200">
            <div class="h-16 px-4 sm:px-6 flex justify-between items-center gap-4">

                <div class="flex items-center gap-3 min-w-0">
                    <button @click="sidebarOpen = !sidebarOpen"
                            class="lg:hidden text-sand-500 hover:text-sand-900 shrink-0">
                        <x-heroicon-o-bars-3 class="w-6 h-6" />
                    </button>

                    {{-- Marked so the duplicate-heading test can inspect just
                         this slot, rather than the whole bar (which also holds
                         the notification bell's own dropdown). --}}
                    <div data-app-bar-title class="min-w-0">
                        @stack('page-title')
                    </div>
                </div>

                <div class="flex items-center gap-2.5 shrink-0">
                    <x-notification-bell />

                    <div class="text-right hidden sm:block leading-tight">
                        <p class="text-[13px] font-medium text-sand-900">{{ $user->name }}</p>
                        <p class="text-[11px] text-sand-500">{{ $user->roleLabel() }}</p>
                    </div>

                    @if ($user->profile_photo_path)
                        <img src="{{ Storage::url($user->profile_photo_path) }}" alt=""
                             class="w-9 h-9 rounded-full object-cover border border-sand-200">
                    @else
                        <div class="w-9 h-9 rounded-full flex items-center justify-center shrink-0
                                    bg-maroon-800 text-white text-sm font-semibold shadow-soft">
                            {{ strtoupper(substr($user->name ?? '?', 0, 1)) }}
                        </div>
                    @endif
                </div>
            </div>
        </header>

        <main class="p-4 sm:p-5 flex-1">
            @if (session('success'))
                <div data-flash class="alert alert-success mb-4">
                    <x-heroicon-o-check-circle />
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if (session('warning'))
                <div data-flash class="alert alert-warning mb-4">
                    <x-heroicon-o-exclamation-triangle />
                    <span>{{ session('warning') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div data-flash class="alert alert-error mb-4">
                    <x-heroicon-o-exclamation-triangle />
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-error mb-4">
                    <x-heroicon-o-exclamation-circle />
                    <div>
                        <p class="font-semibold mb-1">Please correct the following:</p>
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
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
    }, 5000);
</script>

@stack('scripts')
</body>
</html>
