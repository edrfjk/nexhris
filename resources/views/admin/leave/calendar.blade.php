@extends('layouts.app')

@section('title', 'Leave Calendar')

@section('content')

<x-page-header
    title="Leave Calendar"
    subtitle="View approved and pending employee leaves by month.">

    <x-slot:actions>
        <a href="{{ route('admin.leave.calendar.export', ['month' => $month]) }}" target="_blank"
           class="inline-flex items-center gap-1.5 bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-800 transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9.75L12 3.75m0 6l3-3m-3 3l-3-3M3.75 15.75v3a2.25 2.25 0 002.25 2.25h12a2.25 2.25 0 002.25-2.25v-3"/></svg>
            Export Month
        </a>
        <a href="{{ route('admin.leave.index') }}"
           class="inline-flex items-center gap-1.5 text-sm text-gray-600 border border-gray-300 rounded-lg px-3 py-2 hover:bg-gray-50 transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
            </svg>
            Back to Leave Management
        </a>
    </x-slot:actions>

</x-page-header>

@php
    $current = \Carbon\Carbon::parse($month);
    $isCurrentMonth = $current->isSameMonth(now());

    // NOTE: don't use ->flatten() here — LeaveApplication models implement Arrayable,
    // so flatten() recurses into each model's attributes instead of keeping it as an
    // object, which silently corrupts the collection. merge() keeps models intact.
    $allAppsThisMonth = collect($days)->reduce(fn ($carry, $dayApps) => $carry->merge($dayApps), collect())->unique('id');

    // Stats are computed from approved leaves only — a pending request hasn't
    // actually consumed leave-days yet, so counting it would overstate usage.
    $approvedAppsThisMonth = $allAppsThisMonth->where('status', 'approved');
    $vlRequestCount = $approvedAppsThisMonth->where('leave_type', 'VL')->count();
    $slRequestCount = $approvedAppsThisMonth->where('leave_type', 'SL')->count();
    $vlDaysCount = $approvedAppsThisMonth->where('leave_type', 'VL')->sum('days');
    $slDaysCount = $approvedAppsThisMonth->where('leave_type', 'SL')->sum('days');
    $uniqueEmployees = $approvedAppsThisMonth->pluck('user_id')->unique()->count();
    $pendingThisMonth = $allAppsThisMonth->where('status', 'pending')->count();

    // $days is only populated for the month currently being viewed, so "today" is
    // only meaningful when that happens to be the month on screen.
    $todayApps = $isCurrentMonth ? ($days[now()->format('Y-m-d')] ?? collect()) : collect();
@endphp

@if ($isCurrentMonth)
    <!-- Today at a glance -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-sm font-semibold text-gray-800">Today · {{ now()->format('l, F d') }}</h3>
                <p class="text-xs text-gray-400 mt-0.5">Employees currently on leave (solid = approved, dashed = pending)</p>
            </div>
            <span class="text-2xl font-bold {{ $todayApps->count() > 0 ? 'text-maroon-800' : 'text-gray-300' }}">
                {{ $todayApps->count() }}
            </span>
        </div>

        @if ($todayApps->isEmpty())
            <p class="text-sm text-gray-400">No one is on leave today.</p>
        @else
            <div class="flex flex-wrap gap-2">
                @foreach ($todayApps as $app)
                    @php $isPending = $app->status === 'pending'; @endphp
                    <a href="{{ route('admin.leave.ledger', $app->user) }}"
                       class="flex items-center gap-2 rounded-full pl-1.5 pr-3 py-1.5 text-sm font-medium border transition hover:shadow-sm
                       {{ $isPending ? 'border-dashed' : '' }}
                       {{ $app->leave_type === 'VL' ? 'bg-blue-50 border-blue-100 text-blue-700' : 'bg-green-50 border-green-100 text-green-700' }}">
                        <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold text-white
                            {{ $app->leave_type === 'VL' ? 'bg-blue-500' : 'bg-green-500' }}">
                            {{ strtoupper(substr($app->user->name, 0, 1)) }}
                        </span>
                        {{ $app->user->name }}
                        <span class="text-xs opacity-70">{{ $app->leave_type }}{{ $isPending ? ' · Pending' : '' }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endif

<!-- Monthly summary stat cards (approved leaves only) -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 ring-1 ring-gray-100 p-4">
        <div class="flex items-start justify-between mb-3">
            <div class="w-9 h-9 rounded-lg bg-gray-50 text-gray-600 flex items-center justify-center">
                <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                </svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-800 leading-none">{{ $uniqueEmployees }}</p>
        <p class="text-xs text-gray-500 mt-1.5">Employees on Leave This Month</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 ring-1 ring-blue-100 p-4">
        <div class="flex items-start justify-between mb-3">
            <div class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-800 leading-none">{{ number_format($vlDaysCount, 1) }}</p>
        <p class="text-xs text-gray-500 mt-1.5">VL Days Taken <span class="text-gray-400">({{ $vlRequestCount }} {{ Str::plural('request', $vlRequestCount) }})</span></p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 ring-1 ring-green-100 p-4">
        <div class="flex items-start justify-between mb-3">
            <div class="w-9 h-9 rounded-lg bg-green-50 text-green-600 flex items-center justify-center">
                <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-800 leading-none">{{ number_format($slDaysCount, 1) }}</p>
        <p class="text-xs text-gray-500 mt-1.5">SL Days Taken <span class="text-gray-400">({{ $slRequestCount }} {{ Str::plural('request', $slRequestCount) }})</span></p>
    </div>

    <a href="{{ route('admin.leave.pending') }}"
       class="bg-white rounded-xl shadow-sm border border-gray-100 ring-1 ring-yellow-100 p-4 hover:shadow-md hover:-translate-y-0.5 transition">
        <div class="flex items-start justify-between mb-3">
            <div class="w-9 h-9 rounded-lg bg-yellow-50 text-yellow-600 flex items-center justify-center">
                <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9.75L12 3.75m0 6l3-3m-3 3l-3-3M3.75 15.75v3a2.25 2.25 0 002.25 2.25h12a2.25 2.25 0 002.25-2.25v-3"/>
                </svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-800 leading-none">{{ $pendingThisMonth }}</p>
        <p class="text-xs text-gray-500 mt-1.5">Pending This Month</p>
    </a>
</div>

<!-- Everything below shares one Alpine scope so the VL/SL toggle can reach every chip on the page -->
<div x-data="{ typeFilter: 'all' }">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">

        {{-- Month navigation --}}
        <div class="flex items-center justify-between mb-6">
            <a href="{{ route('admin.leave.calendar', ['month' => $current->copy()->subMonth()->format('Y-m')]) }}"
               class="inline-flex items-center justify-center w-10 h-10 rounded-full border border-gray-200 hover:bg-gray-50 hover:border-gray-300 text-gray-500 transition">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            </a>

            <div class="flex items-center gap-3">
                <!-- Jump straight to a month/year instead of clicking prev/next repeatedly -->
                <form method="GET" action="{{ route('admin.leave.calendar') }}">
                    <input type="month" name="month" value="{{ $current->format('Y-m') }}" onchange="this.form.submit()"
                           class="text-lg sm:text-xl font-semibold text-gray-800 border-0 bg-transparent focus:ring-2 focus:ring-maroon-700 rounded-lg cursor-pointer hover:bg-gray-50 px-1 -mx-1 transition">
                </form>

                @unless ($isCurrentMonth)
                    <a href="{{ route('admin.leave.calendar', ['month' => now()->format('Y-m')]) }}"
                       class="text-xs font-medium text-maroon-800 border border-maroon-200 bg-maroon-50 rounded-full px-2.5 py-1 hover:bg-maroon-100 transition">
                        Today
                    </a>
                @endunless
            </div>

            <a href="{{ route('admin.leave.calendar', ['month' => $current->copy()->addMonth()->format('Y-m')]) }}"
               class="inline-flex items-center justify-center w-10 h-10 rounded-full border border-gray-200 hover:bg-gray-50 hover:border-gray-300 text-gray-500 transition">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
            </a>
        </div>

        {{-- Legend + type filter --}}
        <div class="flex flex-wrap items-center justify-between gap-2 mb-4 pb-4 border-b border-gray-100">
            <div class="flex items-center gap-2">
                <button type="button" @click="typeFilter = 'all'"
                        :class="typeFilter === 'all' ? 'bg-gray-800 text-white' : 'bg-gray-50 text-gray-500 hover:bg-gray-100'"
                        class="text-xs font-medium rounded-full px-3 py-1 transition">
                    All
                </button>
                <button type="button" @click="typeFilter = (typeFilter === 'VL' ? 'all' : 'VL')"
                        :class="typeFilter === 'VL' ? 'bg-blue-600 text-white' : 'bg-blue-50 text-blue-700 hover:bg-blue-100'"
                        class="inline-flex items-center gap-1.5 text-xs font-medium rounded-full px-3 py-1 transition">
                    <span class="w-1.5 h-1.5 rounded-full" :class="typeFilter === 'VL' ? 'bg-white' : 'bg-blue-500'"></span>
                    Vacation Leave
                </button>
                <button type="button" @click="typeFilter = (typeFilter === 'SL' ? 'all' : 'SL')"
                        :class="typeFilter === 'SL' ? 'bg-green-600 text-white' : 'bg-green-50 text-green-700 hover:bg-green-100'"
                        class="inline-flex items-center gap-1.5 text-xs font-medium rounded-full px-3 py-1 transition">
                    <span class="w-1.5 h-1.5 rounded-full" :class="typeFilter === 'SL' ? 'bg-white' : 'bg-green-500'"></span>
                    Sick Leave
                </button>
            </div>
            <span class="inline-flex items-center gap-1.5 text-xs text-gray-400">
                <span class="w-3 h-0 border-t-2 border-dashed border-gray-400"></span> Dashed = pending approval
            </span>
        </div>

        {{-- Desktop / tablet grid --}}
        <div class="hidden sm:block" x-data="{ expanded: null }" @click.outside="expanded = null">
            <div class="grid grid-cols-7 gap-2 mb-2">
                @foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day)
                    <div class="text-center text-xs font-semibold text-gray-400 uppercase tracking-wide py-2">
                        {{ $day }}
                    </div>
                @endforeach
            </div>

            <div class="grid grid-cols-7 gap-2">
                @php $leadingBlanks = $start->dayOfWeek; @endphp
                @for ($i = 0; $i < $leadingBlanks; $i++)
                    <div class="min-h-[130px] rounded-xl bg-gray-50/50"></div>
                @endfor

                @foreach ($days as $date => $apps)
                    @php $dateObj = \Carbon\Carbon::parse($date); @endphp
                    <div class="border rounded-xl min-h-[130px] p-2 transition-all duration-150 hover:shadow-md hover:-translate-y-0.5 flex flex-col relative
                        {{ $dateObj->isToday() ? 'bg-gradient-to-br from-maroon-50 to-white border-maroon-300 shadow-sm' : 'border-gray-200 bg-white' }}
                        {{ $dateObj->isWeekend() && !$dateObj->isToday() ? 'bg-gray-50/60' : '' }}">

                        <div class="flex justify-between items-center mb-2">
                            <span class="flex items-center justify-center w-6 h-6 rounded-full text-xs font-semibold
                                {{ $dateObj->isToday() ? 'bg-maroon-700 text-white shadow-sm' : 'text-gray-600' }}">
                                {{ $dateObj->day }}
                            </span>
                            @if ($apps->count())
                                <span class="text-[10px] font-medium text-gray-400 bg-gray-50 rounded-full px-1.5 py-0.5">{{ $apps->count() }}</span>
                            @endif
                        </div>

                        <div class="space-y-1 flex-1">
                            @foreach ($apps->take(3) as $app)
                                @php $isPending = $app->status === 'pending'; @endphp
                                <a href="{{ route('admin.leave.ledger', $app->user) }}"
                                   title="{{ $app->user->name }} ({{ $app->leave_type }}{{ $isPending ? ', pending' : '' }})"
                                   x-show="typeFilter === 'all' || typeFilter === '{{ $app->leave_type }}'"
                                   class="flex items-center gap-1.5 rounded-md pl-1 pr-2 py-1 text-[11px] font-medium transition hover:opacity-80
                                   {{ $isPending ? 'border border-dashed' : '' }}
                                   {{ $app->leave_type === 'VL' ? ($isPending ? 'bg-blue-50 border-blue-300 text-blue-600' : 'bg-blue-100 text-blue-700') : ($isPending ? 'bg-green-50 border-green-300 text-green-600' : 'bg-green-100 text-green-700') }}">
                                    <span class="flex-shrink-0 w-4 h-4 rounded-full flex items-center justify-center text-[9px] font-bold
                                        {{ $app->leave_type === 'VL' ? 'bg-blue-500 text-white' : 'bg-green-500 text-white' }}">
                                        {{ strtoupper(substr($app->user->name, 0, 1)) }}
                                    </span>
                                    <span class="truncate">{{ Str::limit($app->user->name, 10) }}</span>
                                </a>
                            @endforeach

                            @if ($apps->count() > 3)
                                <button type="button" @click="expanded = (expanded === '{{ $date }}' ? null : '{{ $date }}')"
                                        class="text-[10px] font-medium text-maroon-700 hover:text-maroon-900 px-2 underline underline-offset-2">
                                    +{{ $apps->count() - 3 }} more
                                </button>
                            @endif
                        </div>

                        @if ($apps->count() > 3)
                            <div x-show="expanded === '{{ $date }}'" x-cloak @click.stop
                                 class="absolute z-10 top-full left-0 mt-1 w-56 bg-white border border-gray-200 rounded-xl shadow-lg p-2 space-y-1">
                                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-1.5 py-1">{{ $dateObj->format('M d') }} · {{ $apps->count() }} on leave</p>
                                @foreach ($apps as $app)
                                    @php $isPending = $app->status === 'pending'; @endphp
                                    <a href="{{ route('admin.leave.ledger', $app->user) }}"
                                       x-show="typeFilter === 'all' || typeFilter === '{{ $app->leave_type }}'"
                                       class="flex items-center gap-1.5 rounded-md px-1.5 py-1 text-xs font-medium transition hover:bg-gray-50
                                       {{ $app->leave_type === 'VL' ? 'text-blue-700' : 'text-green-700' }}">
                                        <span class="flex-shrink-0 w-4 h-4 rounded-full flex items-center justify-center text-[9px] font-bold text-white
                                            {{ $app->leave_type === 'VL' ? 'bg-blue-500' : 'bg-green-500' }}">
                                            {{ strtoupper(substr($app->user->name, 0, 1)) }}
                                        </span>
                                        {{ $app->user->name }}
                                        <span class="ml-auto text-[10px] opacity-60">{{ $app->leave_type }}{{ $isPending ? ' · Pending' : '' }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach

                @php
                    $trailingBlanks = (7 - (($leadingBlanks + count($days)) % 7)) % 7;
                @endphp
                @for ($i = 0; $i < $trailingBlanks; $i++)
                    <div class="min-h-[130px] rounded-xl bg-gray-50/50"></div>
                @endfor
            </div>
        </div>

        {{-- Mobile agenda fallback: a 7-wide grid is unreadable on small screens, so list only days with leave --}}
        <div class="sm:hidden space-y-2">
            @php $daysWithLeave = collect($days)->filter(fn ($apps) => $apps->count() > 0); @endphp

            @forelse ($daysWithLeave as $date => $apps)
                @php $dateObj = \Carbon\Carbon::parse($date); @endphp
                <div class="border border-gray-200 rounded-xl p-3 {{ $dateObj->isToday() ? 'bg-maroon-50 border-maroon-300' : '' }}">
                    <p class="text-xs font-semibold text-gray-500 mb-2">{{ $dateObj->format('D, M d') }}</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($apps as $app)
                            @php $isPending = $app->status === 'pending'; @endphp
                            <a href="{{ route('admin.leave.ledger', $app->user) }}"
                               x-show="typeFilter === 'all' || typeFilter === '{{ $app->leave_type }}'"
                               class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium
                               {{ $isPending ? 'border border-dashed' : '' }}
                               {{ $app->leave_type === 'VL' ? ($isPending ? 'bg-blue-50 border-blue-300 text-blue-600' : 'bg-blue-100 text-blue-700') : ($isPending ? 'bg-green-50 border-green-300 text-green-600' : 'bg-green-100 text-green-700') }}">
                                {{ $app->user->name }}{{ $isPending ? ' (Pending)' : '' }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @empty
                <x-empty-state message="No leaves this month." />
            @endforelse
        </div>
    </div>
</div>
@endsection