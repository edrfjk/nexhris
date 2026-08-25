@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')

@php
    $greeting = now()->hour < 12 ? 'Good morning' : (now()->hour < 18 ? 'Good afternoon' : 'Good evening');
    $h = $data['headline'];
@endphp

<x-page-header
    :title="$greeting . ', ' . auth()->user()->name"
    :subtitle="'Campus-wide activity for ' . $year . ' · ' . now()->format('l, j F Y')">
    <x-slot:actions>
        <a href="{{ route('admin.employees.create') }}" class="btn btn-sm btn-secondary">
            <x-heroicon-o-user-plus />Add Employee
        </a>
        <a href="{{ route('admin.announcements.index') }}" class="btn btn-sm btn-secondary">
            <x-heroicon-o-megaphone />Post Announcement
        </a>
        <a href="{{ route('admin.pds.index') }}" class="btn btn-sm btn-primary">
            <x-heroicon-o-document-magnifying-glass />Review PDS
        </a>
    </x-slot:actions>
</x-page-header>

{{-- ---------------- Global search ---------------- --}}
<form method="GET" action="{{ route('admin.employees.index') }}" class="toolbar mb-5">
    <x-heroicon-o-magnifying-glass class="w-4 h-4 text-sand-400" />
    <input type="text" name="search" placeholder="Find an employee by name or number…"
           class="input input-sm flex-1 min-w-[220px] border-0 shadow-none focus:ring-0">
    <button class="btn btn-sm btn-primary">Search</button>
</form>

{{-- ---------------- Blocked routing ---------------- --}}
@if ($data['unrouted']->isNotEmpty())
    <div x-data="{ open: false }" class="alert alert-warning mb-5 block">
        <div class="flex items-start gap-3">
            <x-heroicon-o-exclamation-triangle class="w-5 h-5 shrink-0 mt-0.5" />
            <div class="min-w-0 flex-1">
                <p class="font-semibold">
                    {{ $data['unrouted']->count() }}
                    {{ \Illuminate\Support\Str::plural('person', $data['unrouted']->count()) }}
                    {{ $data['unrouted']->count() === 1 ? 'has' : 'have' }} no college assigned
                </p>
                <p class="text-[13px] mt-0.5 leading-relaxed">
                    Leave approval routes on the college, so a form filed by any of them
                    has no Dean to sign it.
                </p>
                <button type="button" @click="open = !open"
                        class="text-[13px] underline underline-offset-2 mt-1">
                    <span x-show="!open">Show who</span>
                    <span x-show="open" x-cloak>Hide</span>
                </button>

                <ul x-show="open" x-cloak class="mt-3 space-y-1.5">
                    @foreach ($data['unrouted'] as $person)
                        <li class="flex items-center justify-between gap-3 text-[13px]">
                            <span class="truncate">
                                {{ $person->name }}
                                <span class="text-sand-500">· {{ $person->employee_number ?: 'no number' }}</span>
                            </span>
                            <a href="{{ route('admin.employees.show', $person) }}"
                               class="btn btn-xs btn-secondary shrink-0">Assign</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endif

{{-- ---------------- Headline figures ---------------- --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <x-stat-card label="Employees" :value="$h['staff']" :hint="$h['active'] . ' active'"
                 icon="users" :href="route('admin.employees.index')" />
    <x-stat-card label="Leave In Review" :value="$h['inFlight']" color="amber"
                 hint="moving through the chain" icon="clock"
                 :href="route('admin.leave.review.index')" />
    <x-stat-card label="Awaiting Ledger Posting" :value="$h['awaitingLedger']"
                 :color="$h['awaitingLedger'] > 0 ? 'red' : 'gray'"
                 hint="approved, not yet recorded" icon="book-open"
                 :href="route('admin.leave.review.index')" />
    <x-stat-card label="PDS Compliance" :value="$data['compliance']['percent'] . '%'" color="green"
                 :hint="$data['compliance']['submitted'] . ' of ' . $data['compliance']['total'] . ' submitted'"
                 icon="document-check" :href="route('admin.pds.index')" />
</div>

{{-- ---------------- Bottleneck ---------------- --}}
<p class="section-label mb-3">Where forms are waiting</p>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    @foreach ($data['bottleneck'] as $stage)
        <x-card>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-[11px] font-medium text-sand-500 uppercase tracking-wide">
                        {{ $stage['label'] }}
                    </p>
                    <p class="text-2xl font-semibold text-sand-900 mt-1 tabular">{{ $stage['count'] }}</p>
                    <p class="text-[11px] text-sand-400 mt-0.5">
                        @if ($stage['count'] > 0 && $stage['oldest'])
                            oldest waiting {{ \Carbon\Carbon::parse($stage['oldest'])->diffForHumans(null, true) }}
                        @else
                            nothing waiting
                        @endif
                    </p>
                </div>
                <div @class([
                    'w-9 h-9 rounded border flex items-center justify-center shrink-0',
                    'bg-red-50 text-red-700 border-red-200' => $stage['count'] > 3,
                    'bg-gold-50 text-gold-700 border-gold-200' => $stage['count'] > 0 && $stage['count'] <= 3,
                    'bg-forest-50 text-forest-700 border-forest-200' => $stage['count'] === 0,
                ])>
                    <x-heroicon-o-inbox-arrow-down class="w-[18px] h-[18px]" />
                </div>
            </div>
        </x-card>
    @endforeach
</div>

{{-- ---------------- Charts ---------------- --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">

    <x-card title="Leave type distribution">
        @if (array_sum($data['leaveTypes']['counts']) === 0)
            <x-empty-state message="No approved leave recorded yet." icon="chart-pie" />
        @else
            <x-chart id="leave-types" type="doughnut"
                     :labels="$data['leaveTypes']['labels']"
                     :datasets="[['label' => 'Applications', 'data' => $data['leaveTypes']['counts']]]" />
        @endif
    </x-card>

    <x-card title="Leave volume, last six months" class="lg:col-span-2">
        @if (array_sum($data['trend']['counts']) === 0)
            <x-empty-state message="No approved leave in the last six months." icon="chart-bar" />
        @else
            <x-chart id="leave-trend" type="line"
                     :labels="$data['trend']['labels']"
                     :datasets="[['label' => 'Days taken', 'data' => $data['trend']['days']]]" />
        @endif
    </x-card>
</div>

{{-- ---------------- Per college ---------------- --}}
<x-card title="Breakdown by college" :padded="false" class="mb-6">
    @if (empty($data['byCollege']))
        <x-empty-state message="No colleges have been set up yet." icon="building-library" />
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>College</th>
                        <th class="num">Staff</th>
                        <th class="num">Leave in review</th>
                        <th class="num">Days taken ({{ $year }})</th>
                        <th>PDS compliance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['byCollege'] as $row)
                        <tr>
                            <td>
                                <span class="badge badge-slate">{{ $row['code'] }}</span>
                                <span class="ml-2 text-sand-800">{{ $row['name'] }}</span>
                            </td>
                            <td class="num">{{ $row['headcount'] }}</td>
                            <td class="num">
                                @if ($row['pending'] > 0)
                                    <span class="badge badge-amber">{{ $row['pending'] }}</span>
                                @else
                                    <span class="text-sand-400">—</span>
                                @endif
                            </td>
                            <td class="num">{{ number_format($row['leaveDays'], 2) }}</td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-1.5 rounded-full bg-sand-200 overflow-hidden min-w-[4rem]">
                                        <div class="h-full rounded-full
                                            {{ $row['compliance'] >= 80 ? 'bg-forest-600' : ($row['compliance'] >= 50 ? 'bg-gold-500' : 'bg-red-500') }}"
                                             style="width: {{ $row['compliance'] }}%"></div>
                                    </div>
                                    <span class="text-xs tabular text-sand-600 w-9 text-right">{{ $row['compliance'] }}%</span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-card>

{{-- ---------------- Panels ---------------- --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    {{-- Ledger queue --}}
    <x-card title="Awaiting ledger posting">
        <x-slot:actions>
            <a href="{{ route('admin.leave.review.index') }}" class="btn btn-xs btn-secondary">Open</a>
        </x-slot:actions>

        @forelse ($data['ledgerQueue'] as $application)
            <div class="flex items-center justify-between gap-3 py-2.5 {{ ! $loop->last ? 'border-b border-sand-100' : '' }}">
                <div class="min-w-0">
                    <p class="text-[13px] font-medium text-sand-900 truncate">{{ $application->user->name }}</p>
                    <p class="text-[11px] text-sand-400">
                        {{ $application->leave_type }} ·
                        {{ rtrim(rtrim(number_format((float) $application->days, 2), '0'), '.') }} day(s)
                    </p>
                </div>
                <a href="{{ route('admin.leave.review.show', $application) }}" class="btn btn-xs btn-primary">Post</a>
            </div>
        @empty
            <x-empty-state title="You're all caught up"
                           message="Every approved leave has been recorded on a ledger card."
                           icon="check-circle" />
        @endforelse
    </x-card>

    {{-- Onboarding --}}
    <x-card title="New staff without a PDS">
        @forelse ($data['onboarding'] as $person)
            <div class="flex items-center justify-between gap-3 py-2.5 {{ ! $loop->last ? 'border-b border-sand-100' : '' }}">
                <div class="min-w-0">
                    <p class="text-[13px] font-medium text-sand-900 truncate">{{ $person->name }}</p>
                    <p class="text-[11px] text-sand-400">added {{ $person->created_at->diffForHumans() }}</p>
                </div>
                <a href="{{ route('admin.pds.show', $person) }}" class="btn btn-xs btn-secondary">View</a>
            </div>
        @empty
            <x-empty-state title="Nothing outstanding"
                           message="Everyone added in the last 90 days has filed a PDS."
                           icon="check-circle" />
        @endforelse
    </x-card>

    {{-- Policy acknowledgment --}}
    <x-card title="Latest policy acknowledgment">
        @if ($data['policyTracker'])
            @php $t = $data['policyTracker']; @endphp
            <p class="text-[13px] font-medium text-sand-900">{{ $t['policy']->title }}</p>
            <p class="text-[11px] text-sand-400 mb-4">
                posted {{ $t['policy']->created_at->diffForHumans() }}
            </p>

            <div class="flex items-end justify-between mb-2">
                <span class="text-2xl font-semibold text-sand-900 tabular">{{ $t['percent'] }}%</span>
                <span class="text-[11px] text-sand-500">{{ $t['read'] }} of {{ $t['total'] }} staff</span>
            </div>

            <div class="h-2 rounded-full bg-sand-200 overflow-hidden">
                <div class="h-full rounded-full bg-maroon-800" style="width: {{ $t['percent'] }}%"></div>
            </div>

            <a href="{{ route('admin.policies.compliance', $t['policy']) }}"
               class="mt-4 inline-block text-xs font-medium text-maroon-700 hover:text-maroon-900">
                See who has read it →
            </a>
        @else
            <x-empty-state message="No policy has been published yet." icon="clipboard-document-list" />
        @endif
    </x-card>
</div>

{{-- ---------------- Activity feed ---------------- --}}
<x-card title="Recent activity" :padded="false" class="mt-5">
    <x-slot:actions>
        <a href="{{ route('admin.activity-logs.index') }}" class="btn btn-xs btn-secondary">Full audit trail</a>
    </x-slot:actions>

    @if ($data['recentActivity']->isEmpty())
        <x-empty-state message="Nothing has been recorded yet." icon="clock" />
    @else
        <ul class="divide-y divide-sand-100">
            @foreach ($data['recentActivity'] as $log)
                <li class="px-5 py-2.5 flex items-center gap-3">
                    <x-badge :color="$log->tone()">{{ $log->actionLabel() }}</x-badge>
                    <span class="text-[13px] text-sand-700 truncate flex-1">{{ $log->description }}</span>
                    <span class="text-[11px] text-sand-400 shrink-0">{{ $log->created_at->diffForHumans() }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>

@endsection
