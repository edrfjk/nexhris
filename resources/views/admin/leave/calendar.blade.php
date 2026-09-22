@extends('layouts.app')

@section('title', 'Leave Calendar')

@section('content')

<x-page-header
    title="Leave Calendar"
    subtitle="View approved and pending employee leaves by month.">

    <x-slot:actions>
        <a href="{{ route('admin.leave.calendar.export', ['month' => $month, 'filename' => \App\Support\DocumentName::leaveCalendar(\Carbon\Carbon::parse($month . '-01'))]) }}" target="_blank"
           class="btn btn-md btn-secondary">
            <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
            Export Month
        </a>
        <a href="{{ route('admin.leave.index') }}"
           class="btn btn-md btn-secondary">
            <x-heroicon-o-arrow-left class="w-4 h-4" />
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

    // Stats are computed from fully approved leaves only — a form still moving
    // through the Dean → HR → Campus Director chain hasn't actually consumed
    // leave-days yet, so counting it would overstate usage.
    $fullyApproved = ['cd_approved', 'completed'];
    $approvedAppsThisMonth = $allAppsThisMonth->whereIn('status', $fullyApproved);
    $vlRequestCount = $approvedAppsThisMonth->where('leave_type', 'VL')->count();
    $slRequestCount = $approvedAppsThisMonth->where('leave_type', 'SL')->count();
    $vlDaysCount = $approvedAppsThisMonth->where('leave_type', 'VL')->sum('days');
    $slDaysCount = $approvedAppsThisMonth->where('leave_type', 'SL')->sum('days');
    $uniqueEmployees = $approvedAppsThisMonth->pluck('user_id')->unique()->count();
    $pendingThisMonth = $allAppsThisMonth->whereNotIn('status', $fullyApproved)->count();

    // $days is only populated for the month currently being viewed, so "today" is
    // only meaningful when that happens to be the month on screen.
    $todayApps = $isCurrentMonth ? ($days[now()->format('Y-m-d')] ?? collect()) : collect();

    // One scoped data set powers the day-details modal. Only applications
    // already allowed by the controller's college boundary reach this view.
    $calendarDetails = collect($days)->mapWithKeys(function ($apps, $date) {
        $dateObj = \Carbon\Carbon::parse($date);

        return [$date => [
            'label' => $dateObj->format('l, F j, Y'),
            'entries' => $apps->map(function ($app) {
                $pending = ! in_array($app->status, ['cd_approved', 'completed'], true);

                return [
                    'id' => $app->id,
                    'employee' => $app->user->name,
                    'employeeNumber' => $app->user->employee_number ?: 'No employee number',
                    'position' => $app->user->position ?: 'Position not set',
                    'college' => $app->user->college?->code ?: 'No college',
                    'department' => $app->user->departmentRecord?->code
                        ?: ($app->user->program ?: 'No department'),
                    'type' => $app->typeLabel(),
                    'typeCode' => $app->leave_type,
                    'status' => $app->currentStageLabel(),
                    'pending' => $pending,
                    'period' => $app->date_from->format('M j, Y') . ' – ' . $app->date_to->format('M j, Y'),
                    'days' => number_format((float) $app->days, 2),
                    'reason' => $app->reason ?: 'No reason provided.',
                    'reviewUrl' => route('admin.leave.review.show', $app),
                    'ledgerUrl' => route('admin.leave.ledger', $app->user),
                ];
            })->values()->all(),
        ]];
    })->all();
@endphp

@if ($isCurrentMonth)
    <!-- Today at a glance -->
    <div class="card p-5 mb-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-sm font-semibold text-sand-800">Today · {{ now()->format('l, F d') }}</h3>
                <p class="text-xs text-sand-400 mt-0.5">Employees currently on leave (solid = approved, dashed = pending)</p>
            </div>
            <span class="text-2xl font-bold {{ $todayApps->count() > 0 ? 'text-maroon-800' : 'text-sand-300' }}">
                {{ $todayApps->count() }}
            </span>
        </div>

        @if ($todayApps->isEmpty())
            <p class="text-sm text-sand-400">No one is on leave today.</p>
        @else
            <div class="flex flex-wrap gap-2">
                @foreach ($todayApps as $app)
                    @php
                        $isPending = ! in_array($app->status, ['cd_approved', 'completed'], true);
                        $statusChipClass = $isPending
                            ? 'bg-gold-50 border-gold-300 text-gold-700'
                            : 'bg-forest-50 border-forest-300 text-forest-700';
                        $statusAvatarClass = $isPending ? 'bg-gold-500' : 'bg-forest-500';
                    @endphp
                    <a href="{{ route('admin.leave.ledger', $app->user) }}"
                       class="flex items-center gap-2 rounded-full pl-1.5 pr-3 py-1.5 text-sm font-medium border transition hover:shadow-soft
                       {{ $isPending ? 'border-dashed' : '' }}
                       {{ $statusChipClass }}">
                        <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold text-white
                            {{ $statusAvatarClass }}">
                            {{ strtoupper(substr($app->user->name, 0, 1)) }}
                        </span>
                        {{ $app->user->name }}
                        <span class="text-xs opacity-70">{{ $app->typeLabel() }}{{ $isPending ? ' · Pending' : '' }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endif

<!-- Monthly summary stat cards (approved leaves only) -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="card ring-1 ring-sand-100 p-4">
        <div class="flex items-start justify-between mb-3">
            <div class="w-9 h-9 rounded-lg bg-sand-50 text-sand-600 flex items-center justify-center">
                <x-heroicon-o-user-circle class="w-4.5 h-4.5" />
            </div>
        </div>
        <p class="text-2xl font-bold text-sand-800 leading-none">{{ $uniqueEmployees }}</p>
        <p class="text-xs text-sand-500 mt-1.5">Employees on Leave This Month</p>
    </div>

    <div class="card ring-1 ring-sky-100 p-4">
        <div class="flex items-start justify-between mb-3">
            <div class="w-9 h-9 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center">
                <x-heroicon-o-clock class="w-4.5 h-4.5" />
            </div>
        </div>
        <p class="text-2xl font-bold text-sand-800 leading-none">{{ number_format($vlDaysCount, 1) }}</p>
        <p class="text-xs text-sand-500 mt-1.5">VL Days Taken <span class="text-sand-400">({{ $vlRequestCount }} {{ Str::plural('request', $vlRequestCount) }})</span></p>
    </div>

    <div class="card ring-1 ring-forest-100 p-4">
        <div class="flex items-start justify-between mb-3">
            <div class="w-9 h-9 rounded-lg bg-forest-50 text-forest-600 flex items-center justify-center">
                <x-heroicon-o-clock class="w-4.5 h-4.5" />
            </div>
        </div>
        <p class="text-2xl font-bold text-sand-800 leading-none">{{ number_format($slDaysCount, 1) }}</p>
        <p class="text-xs text-sand-500 mt-1.5">SL Days Taken <span class="text-sand-400">({{ $slRequestCount }} {{ Str::plural('request', $slRequestCount) }})</span></p>
    </div>

    <a href="{{ route('admin.leave.review.index') }}"
       class="card ring-1 ring-gold-100 p-4 hover:shadow-soft transition">
        <div class="flex items-start justify-between mb-3">
            <div class="w-9 h-9 rounded-lg bg-gold-50 text-gold-600 flex items-center justify-center">
                <x-heroicon-o-arrow-down-tray class="w-4.5 h-4.5" />
            </div>
        </div>
        <p class="text-2xl font-bold text-sand-800 leading-none">{{ $pendingThisMonth }}</p>
        <p class="text-xs text-sand-500 mt-1.5">Pending This Month</p>
    </a>
</div>

<!-- Everything below shares one Alpine scope so the VL/SL toggle can reach every chip on the page -->
@if ($viewerCollege)
    <div class="alert alert-info mb-4">
        <x-heroicon-o-information-circle />
        <span>
            Showing leave for <strong>{{ $viewerCollege->name }}</strong> only —
            the college you are Dean of.
        </span>
    </div>
@endif

<div x-data="{
        typeFilter: 'all',
        selectedDate: null,
        calendarDetails: @js($calendarDetails),
        visibleEntries(date) {
            const entries = this.calendarDetails[date]?.entries ?? [];
            return this.typeFilter === 'all'
                ? entries
                : entries.filter(entry => entry.typeCode === this.typeFilter);
        },
        openDay(date) {
            if (this.visibleEntries(date).length > 0) this.selectedDate = date;
        }
    }"
    x-effect="document.body.style.overflow = selectedDate ? 'hidden' : ''"
    @keydown.escape.window="selectedDate = null">
    <div class="card p-5">

        {{-- Month navigation --}}
        <div class="flex items-center justify-between gap-2 mb-6">
            <a href="{{ route('admin.leave.calendar', ['month' => $current->copy()->subMonth()->format('Y-m'), 'college' => request('college')]) }}"
               class="icon-btn icon-btn-round shrink-0"
               aria-label="Previous month">
                <x-heroicon-o-arrow-left class="w-4 h-4" />
            </a>

            <div class="flex min-w-0 flex-wrap items-center justify-center gap-2 sm:gap-3">
                <!-- Jump straight to a month/year instead of clicking prev/next repeatedly -->
                <form method="GET" action="{{ route('admin.leave.calendar') }}" class="flex min-w-0 flex-wrap items-center justify-center gap-2">
                    <input type="month" name="month" value="{{ $current->format('Y-m') }}" onchange="this.form.submit()"
                           class="input min-w-0 max-w-full sm:text-xl font-semibold cursor-pointer hover:bg-sand-50 -mx-1">

                    {{-- HR and the Campus Director may narrow to one college.
                         A Dean has no picker: their scope is fixed to their own. --}}
                    @if ($colleges->isNotEmpty())
                        <select name="college" onchange="this.form.submit()" class="select select-sm w-auto">
                            <option value="">All colleges</option>
                            @foreach ($colleges as $college)
                                <option value="{{ $college->id }}"
                                        @selected((string) request('college') === (string) $college->id)>
                                    {{ $college->code }}
                                </option>
                            @endforeach
                        </select>
                    @endif
                </form>

                @unless ($isCurrentMonth)
                    <a href="{{ route('admin.leave.calendar', ['month' => now()->format('Y-m'), 'college' => request('college')]) }}"
                       class="btn btn-sm btn-secondary">
                        Today
                    </a>
                @endunless
            </div>

            <a href="{{ route('admin.leave.calendar', ['month' => $current->copy()->addMonth()->format('Y-m'), 'college' => request('college')]) }}"
               class="icon-btn icon-btn-round shrink-0"
               aria-label="Next month">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
            </a>
        </div>

        {{-- Legend + type filter --}}
        <div class="flex flex-wrap items-center justify-between gap-2 mb-4 pb-4 border-b border-sand-100">
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" @click="typeFilter = 'all'"
                        :class="typeFilter === 'all' ? 'bg-sand-800 text-white' : 'bg-sand-50 text-sand-500 hover:bg-sand-100'"
                        class="text-xs font-medium rounded-full px-3 py-1 transition">
                    All
                </button>
                <button type="button" @click="typeFilter = (typeFilter === 'VL' ? 'all' : 'VL')"
                        :class="typeFilter === 'VL' ? 'bg-sky-600 text-white' : 'bg-sky-50 text-sky-700 hover:bg-sky-100'"
                        class="inline-flex items-center gap-1.5 text-xs font-medium rounded-full px-3 py-1 transition">
                    <span class="w-1.5 h-1.5 rounded-full" :class="typeFilter === 'VL' ? 'bg-white' : 'bg-sky-500'"></span>
                    Vacation Leave
                </button>
                <button type="button" @click="typeFilter = (typeFilter === 'SL' ? 'all' : 'SL')"
                        :class="typeFilter === 'SL' ? 'bg-forest-600 text-white' : 'bg-forest-50 text-forest-700 hover:bg-forest-100'"
                        class="inline-flex items-center gap-1.5 text-xs font-medium rounded-full px-3 py-1 transition">
                    <span class="w-1.5 h-1.5 rounded-full" :class="typeFilter === 'SL' ? 'bg-white' : 'bg-forest-500'"></span>
                    Sick Leave
                </button>
                @foreach (array_diff_key(\App\Models\LeaveApplication::TYPES, array_flip(['VL', 'SL'])) as $code => $label)
                    <button type="button" @click="typeFilter = (typeFilter === '{{ $code }}' ? 'all' : '{{ $code }}')"
                            :class="typeFilter === '{{ $code }}' ? 'bg-sand-800 text-white' : 'bg-sand-50 text-sand-600 hover:bg-sand-100'"
                            class="text-xs font-medium rounded-full px-3 py-1 transition">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
            <span class="inline-flex items-center gap-1.5 text-xs text-sand-400">
                <span class="w-3 h-3 rounded-sm bg-gold-100 border border-dashed border-gold-400"></span> Pending
                <span class="w-3 h-3 rounded-sm bg-forest-100 border border-forest-300 ml-2"></span> Approved
            </span>
        </div>

        {{-- Desktop / tablet grid --}}
        <div class="hidden sm:block">
            <div class="grid grid-cols-7 gap-2 mb-2">
                @foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day)
                    <div class="text-center text-xs font-semibold text-sand-400 uppercase tracking-wide py-2">
                        {{ $day }}
                    </div>
                @endforeach
            </div>

            <div class="grid grid-cols-7 gap-2">
                @php $leadingBlanks = $start->dayOfWeek; @endphp
                @for ($i = 0; $i < $leadingBlanks; $i++)
                    <div class="min-h-[130px] rounded bg-sand-50/50"></div>
                @endfor

                @foreach ($days as $date => $apps)
                    @php $dateObj = \Carbon\Carbon::parse($date); @endphp
                    <div class="border rounded min-h-[130px] p-2 transition-all duration-150 hover:shadow-soft flex flex-col relative
                        {{ $apps->count() ? 'cursor-pointer focus:outline-none focus:ring-2 focus:ring-maroon-400 focus:ring-offset-1' : '' }}
                        {{ $dateObj->isToday() ? 'bg-maroon-50 border-maroon-300 shadow-soft' : 'border-sand-200 bg-white' }}
                        {{ $dateObj->isWeekend() && !$dateObj->isToday() ? 'bg-sand-50/60' : '' }}"
                        @if ($apps->count())
                            role="button" tabindex="0"
                            aria-label="View employees on leave on {{ $dateObj->format('F j, Y') }}"
                            @click="openDay('{{ $date }}')"
                            @keydown.enter.prevent="openDay('{{ $date }}')"
                            @keydown.space.prevent="openDay('{{ $date }}')"
                        @endif>

                        <div class="flex justify-between items-center mb-2">
                            <span class="flex items-center justify-center w-6 h-6 rounded-full text-xs font-semibold
                                {{ $dateObj->isToday() ? 'bg-maroon-700 text-white shadow-soft' : 'text-sand-600' }}">
                                {{ $dateObj->day }}
                            </span>
                            @if ($apps->count())
                                <span x-show="visibleEntries('{{ $date }}').length > 0"
                                      x-text="visibleEntries('{{ $date }}').length"
                                      class="text-[10px] font-medium text-sand-400 bg-sand-50 rounded-full px-1.5 py-0.5"></span>
                            @endif
                        </div>

                        <div class="space-y-1 flex-1">
                            @foreach ($apps->take(3) as $app)
                                @php
                                    $isPending = ! in_array($app->status, ['cd_approved', 'completed'], true);
                                    $statusChipClass = $isPending
                                        ? 'bg-gold-50 border-gold-300 text-gold-700'
                                        : 'bg-forest-50 border-forest-300 text-forest-700';
                                    $statusAvatarClass = $isPending ? 'bg-gold-500' : 'bg-forest-500';
                                @endphp
                                <div title="{{ $app->user->name }} ({{ $app->typeLabel() }}{{ $isPending ? ', pending' : '' }})"
                                   x-show="typeFilter === 'all' || typeFilter === '{{ $app->leave_type }}'"
                                   class="flex items-center gap-1.5 rounded-md pl-1 pr-2 py-1 text-[11px] font-medium
                                   {{ $isPending ? 'border border-dashed' : '' }}
                                   {{ $statusChipClass }}">
                                    <span class="flex-shrink-0 w-4 h-4 rounded-full flex items-center justify-center text-[9px] font-bold
                                        {{ $statusAvatarClass }} text-white">
                                        {{ strtoupper(substr($app->user->name, 0, 1)) }}
                                    </span>
                                    <span class="truncate">{{ Str::limit($app->user->name, 10) }} · {{ $app->typeLabel() }}</span>
                                </div>
                            @endforeach

                            @if ($apps->count() > 3)
                                <button type="button" @click.stop="openDay('{{ $date }}')"
                                        class="text-[10px] font-medium text-maroon-700 hover:text-maroon-900 px-2 text-left underline underline-offset-2">
                                    View all <span x-text="visibleEntries('{{ $date }}').length"></span> employees
                                </button>
                            @endif
                        </div>

                    </div>
                @endforeach

                @php
                    $trailingBlanks = (7 - (($leadingBlanks + count($days)) % 7)) % 7;
                @endphp
                @for ($i = 0; $i < $trailingBlanks; $i++)
                    <div class="min-h-[130px] rounded bg-sand-50/50"></div>
                @endfor
            </div>
        </div>

        {{-- Mobile agenda fallback: a 7-wide grid is unreadable on small screens, so list only days with leave --}}
        <div class="sm:hidden space-y-2">
            @php $daysWithLeave = collect($days)->filter(fn ($apps) => $apps->count() > 0); @endphp

            @forelse ($daysWithLeave as $date => $apps)
                @php $dateObj = \Carbon\Carbon::parse($date); @endphp
                <div class="border border-sand-200 rounded p-3 cursor-pointer transition hover:shadow-soft focus:outline-none focus:ring-2 focus:ring-maroon-400
                    {{ $dateObj->isToday() ? 'bg-maroon-50 border-maroon-300' : '' }}"
                    role="button" tabindex="0"
                    @click="openDay('{{ $date }}')"
                    @keydown.enter.prevent="openDay('{{ $date }}')"
                    @keydown.space.prevent="openDay('{{ $date }}')">
                    <div class="flex items-center justify-between gap-3 mb-2">
                        <p class="text-xs font-semibold text-sand-500">{{ $dateObj->format('D, M d') }}</p>
                        <span class="text-[10px] font-medium text-maroon-700">View details</span>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($apps as $app)
                            @php
                                $isPending = ! in_array($app->status, ['cd_approved', 'completed'], true);
                                $statusChipClass = $isPending
                                    ? 'bg-gold-50 border-gold-300 text-gold-700'
                                    : 'bg-forest-50 border-forest-300 text-forest-700';
                            @endphp
                            <span x-show="typeFilter === 'all' || typeFilter === '{{ $app->leave_type }}'"
                               class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium
                               {{ $isPending ? 'border border-dashed' : '' }}
                               {{ $statusChipClass }}">
                                {{ $app->user->name }} · {{ $app->typeLabel() }}{{ $isPending ? ' (Pending)' : '' }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @empty
                <x-empty-state message="No leaves this month." />
            @endforelse
        </div>
    </div>

    {{-- One modal serves every date, so a busy month does not create dozens of
         hidden dialogs. Its data is already restricted by the viewer's role. --}}
    <div x-show="selectedDate" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6"
         role="dialog" aria-modal="true" aria-labelledby="leave-day-modal-title">
        <div class="absolute inset-0 bg-sand-900/60 backdrop-blur-sm"
             @click="selectedDate = null" x-transition.opacity></div>

        <div class="relative w-full max-w-3xl max-h-[88vh] overflow-hidden rounded-xl bg-white shadow-xl border border-sand-200"
             @click.stop x-transition>
            <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-sand-200 bg-sand-50">
                <div>
                    <p class="section-label">Employees on leave</p>
                    <h2 id="leave-day-modal-title" class="text-lg font-semibold text-sand-900"
                        x-text="calendarDetails[selectedDate]?.label ?? ''"></h2>
                    <p class="text-xs text-sand-500 mt-1">
                        <span x-text="visibleEntries(selectedDate).length"></span>
                        <span x-text="visibleEntries(selectedDate).length === 1 ? 'employee' : 'employees'"></span>
                        <span x-show="typeFilter !== 'all'"> under the selected leave filter</span>
                    </p>
                </div>
                <button type="button" class="icon-btn icon-btn-round shrink-0"
                        aria-label="Close day details" @click="selectedDate = null">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                    </svg>
                </button>
            </div>

            <div class="p-4 sm:p-5 overflow-y-auto max-h-[calc(88vh-92px)] bg-sand-50/40">
                <div class="space-y-3">
                    <template x-for="entry in visibleEntries(selectedDate)" :key="entry.id">
                        <article class="rounded-lg border border-sand-200 bg-white p-4 shadow-soft">
                            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h3 class="font-semibold text-sand-900" x-text="entry.employee"></h3>
                                        <span class="badge"
                                              :class="entry.pending ? 'badge-amber' : 'badge-green'"
                                              x-text="entry.pending ? 'Pending' : 'Approved'"></span>
                                        <span class="badge badge-slate" x-text="entry.type"></span>
                                    </div>
                                    <p class="text-xs text-sand-500 mt-1">
                                        <span x-text="entry.employeeNumber"></span>
                                        <span aria-hidden="true"> · </span>
                                        <span x-text="entry.position"></span>
                                    </p>
                                </div>
                                <p class="text-xs font-medium text-sand-600 whitespace-nowrap" x-text="entry.period"></p>
                            </div>

                            <dl class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4 text-xs">
                                <div><dt class="text-sand-400">College</dt><dd class="font-medium text-sand-700 mt-0.5" x-text="entry.college"></dd></div>
                                <div><dt class="text-sand-400">Department</dt><dd class="font-medium text-sand-700 mt-0.5" x-text="entry.department"></dd></div>
                                <div><dt class="text-sand-400">Working days</dt><dd class="font-medium text-sand-700 mt-0.5" x-text="entry.days"></dd></div>
                                <div><dt class="text-sand-400">Workflow status</dt><dd class="font-medium text-sand-700 mt-0.5" x-text="entry.status"></dd></div>
                            </dl>

                            <div class="mt-3 rounded-md bg-sand-50 px-3 py-2 text-xs text-sand-600">
                                <span class="font-medium text-sand-700">Reason:</span>
                                <span x-text="entry.reason"></span>
                            </div>

                            <div class="flex flex-wrap justify-end gap-2 mt-3">
                                <a :href="entry.ledgerUrl" class="btn btn-sm btn-secondary">Open ledger</a>
                                <a :href="entry.reviewUrl" class="btn btn-sm btn-primary">View leave details</a>
                            </div>
                        </article>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
