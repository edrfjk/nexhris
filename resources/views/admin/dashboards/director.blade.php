@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')

@php
    $greeting = now()->hour < 12 ? 'Good morning' : (now()->hour < 18 ? 'Good afternoon' : 'Good evening');
@endphp

<x-page-header
    :title="$greeting . ', ' . auth()->user()->name"
    subtitle="Campus-wide leave oversight">
    <x-slot:actions>
        <a href="{{ route('admin.leave.calendar') }}" class="btn btn-sm btn-secondary">
            <x-heroicon-o-calendar-days />Leave Calendar
        </a>
        <a href="{{ route('admin.leave.review.index') }}" class="btn btn-sm btn-primary">
            <x-heroicon-o-inbox-arrow-down />Review Leave
        </a>
    </x-slot:actions>
</x-page-header>

{{-- ---------------- Primary widget ---------------- --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
    <a href="{{ route('admin.leave.review.index') }}"
       class="lg:col-span-2 card card-interactive p-6 flex items-center gap-5">
        <div @class([
            'w-14 h-14 rounded-lg border flex items-center justify-center shrink-0',
            'bg-gold-50 text-gold-700 border-gold-200' => $data['pending'] > 0,
            'bg-forest-50 text-forest-700 border-forest-200' => $data['pending'] === 0,
        ])>
            <x-heroicon-o-inbox-arrow-down class="w-7 h-7" />
        </div>

        <div class="min-w-0">
            <p class="text-3xl font-semibold text-sand-900 tabular leading-none">{{ $data['pending'] }}</p>
            <p class="text-[13px] font-medium text-sand-800 mt-1.5">
                application{{ $data['pending'] === 1 ? '' : 's' }} awaiting your final approval
            </p>
            <p class="text-[11px] text-sand-500 mt-0.5">
                {{ $data['inFlight'] }} in the chain campus-wide ·
                {{ $data['onLeaveToday']->count() }} on leave today
            </p>

            @if ($data['oldestWaiting'])
                @php $waited = $data['oldestWaiting']->daysWaiting(); @endphp
                <p @class([
                    'text-[11px] mt-1 font-medium',
                    'text-red-700' => $waited >= 14,
                    'text-gold-700' => $waited >= 5 && $waited < 14,
                    'text-sand-500' => $waited < 5,
                ])>
                    Longest waiting: {{ $data['oldestWaiting']->user->name }} ·
                    {{ $waited === 0 ? 'today' : $waited . ' day' . ($waited === 1 ? '' : 's') }}
                </p>
            @endif
        </div>
    </a>

    <x-card title="On leave today">
        @if ($data['onLeaveToday']->isEmpty())
            <x-empty-state message="Nobody is on leave today." icon="check-circle" />
        @else
            <div class="flex flex-wrap gap-1.5">
                @foreach ($data['onLeaveToday'] as $application)
                    <span class="badge badge-blue">{{ $application->user->name }}</span>
                @endforeach
            </div>
        @endif
    </x-card>
</div>

{{-- ---------------- The Director's own leave ---------------- --}}
@if ($data['myApplication'])
    @php $mine = $data['myApplication']; @endphp
    <x-card title="Your own leave application" class="mb-5">
        <x-slot:actions>
            <a href="{{ route('leave.index') }}" class="btn btn-xs btn-secondary">My leave</a>
        </x-slot:actions>

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="min-w-0">
                <p class="text-[13px] font-medium text-sand-900">
                    {{ $mine->leave_type === 'VL' ? 'Vacation' : 'Sick' }} leave ·
                    {{ rtrim(rtrim(number_format((float) $mine->days, 2), '0'), '.') }} day(s)
                </p>
                <p class="text-[11px] text-sand-500 mt-0.5">
                    {{ $mine->date_from?->format('M j, Y') }}
                    @if ($mine->date_to && ! $mine->date_to->eq($mine->date_from))
                        – {{ $mine->date_to->format('M j, Y') }}
                    @endif
                    · {{ $mine->currentStageLabel() }}
                </p>
            </div>
            <x-leave.status-pill :application="$mine" />
        </div>

        {{-- Your own Campus Director stage is skipped: HR gives the final
             signature on it, so the stepper shows that stage as N/A. --}}
        <div class="mt-4 pt-4 border-t border-sand-100">
            <x-leave.stepper :application="$mine" />
        </div>
    </x-card>
@endif

{{-- ---------------- Trend ---------------- --}}
<x-card title="Campus-wide leave volume, last six months" class="mb-5">
    @if (array_sum($data['trend']['counts']) === 0)
        <x-empty-state message="No approved leave in the last six months." icon="chart-bar" />
    @else
        <x-chart id="director-trend" type="line"
                 :labels="$data['trend']['labels']"
                 :datasets="[['label' => 'Days taken', 'data' => $data['trend']['days']]]" />
    @endif
</x-card>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    {{-- Approvals by college --}}
    <x-card title="Pending approvals by college" :padded="false" class="lg:col-span-2">
        @if (empty($data['byCollege']))
            <x-empty-state message="No colleges have been set up yet." icon="building-library" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>College</th>
                            <th class="num">Staff</th>
                            <th class="num">In review</th>
                            <th class="num">Days taken</th>
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
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    {{-- Recent decisions --}}
    <x-card title="Your recent decisions">
        @forelse ($data['recentDecisions'] as $application)
            <div class="py-2.5 {{ ! $loop->last ? 'border-b border-sand-100' : '' }}">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-[13px] font-medium text-sand-900 truncate">{{ $application->user->name }}</p>
                    <span class="text-[11px] text-sand-400 shrink-0">
                        {{ $application->updated_at->diffForHumans(null, true) }}
                    </span>
                </div>
                <div class="mt-1">
                    <x-leave.status-pill :application="$application" />
                </div>
            </div>
        @empty
            <x-empty-state message="You have not given a final approval yet." icon="check-circle" />
        @endforelse
    </x-card>
</div>

@endsection
