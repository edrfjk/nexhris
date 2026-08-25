@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')

@php
    $greeting = now()->hour < 12 ? 'Good morning' : (now()->hour < 18 ? 'Good afternoon' : 'Good evening');
    $college = $data['college'];
@endphp

<x-page-header
    :title="$greeting . ', ' . auth()->user()->name"
    :subtitle="$college ? $college->name : 'No college assigned yet'">
    <x-slot:actions>
        <a href="{{ route('admin.leave.calendar') }}" class="btn btn-sm btn-secondary">
            <x-heroicon-o-calendar-days />Leave Calendar
        </a>
        <a href="{{ route('admin.leave.review.index') }}" class="btn btn-sm btn-primary">
            <x-heroicon-o-inbox-arrow-down />Review Leave
        </a>
    </x-slot:actions>
</x-page-header>

@unless ($college)
    <div class="alert alert-warning mb-5">
        <x-heroicon-o-exclamation-triangle />
        <div>
            <p class="font-medium">You are not assigned to a college</p>
            <p class="text-[13px] mt-0.5">
                Leave forms are routed to a Dean by college, so nothing will reach you
                until HR assigns you one.
            </p>
        </div>
    </div>
@endunless

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
                leave application{{ $data['pending'] === 1 ? '' : 's' }} pending your approval
            </p>
            <p class="text-[11px] text-sand-500 mt-0.5">
                {{ $data['pending'] > 0 ? 'Review them to keep the chain moving.' : "You're all caught up." }}
            </p>
        </div>
    </a>

    <x-card title="College headcount">
        <div class="grid grid-cols-2 gap-4 text-center">
            <div>
                <p class="text-2xl font-semibold text-sand-900 tabular">{{ $data['activeHeadcount'] }}</p>
                <p class="text-[11px] text-sand-500 mt-0.5">Active staff</p>
            </div>
            <div>
                <p class="text-2xl font-semibold {{ $data['onLeaveToday']->count() > 0 ? 'text-gold-700' : 'text-sand-900' }} tabular">
                    {{ $data['onLeaveToday']->count() }}
                </p>
                <p class="text-[11px] text-sand-500 mt-0.5">On leave today</p>
            </div>
        </div>

        @if ($data['onLeaveToday']->isNotEmpty())
            <div class="mt-4 pt-4 border-t border-sand-100 flex flex-wrap gap-1.5">
                @foreach ($data['onLeaveToday'] as $application)
                    <span class="badge badge-blue">{{ $application->user->name }}</span>
                @endforeach
            </div>
        @endif
    </x-card>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    {{-- Upcoming leave, the mini calendar preview --}}
    <x-card title="Upcoming leave in your college" class="lg:col-span-2">
        <x-slot:actions>
            <a href="{{ route('admin.leave.calendar') }}" class="btn btn-xs btn-secondary">Full calendar</a>
        </x-slot:actions>

        @forelse ($data['upcoming'] as $application)
            <div class="flex items-center gap-3 py-2.5 {{ ! $loop->last ? 'border-b border-sand-100' : '' }}">
                <div class="w-11 shrink-0 text-center">
                    <p class="text-[10px] uppercase text-sand-400 leading-none">
                        {{ $application->date_from->format('M') }}
                    </p>
                    <p class="text-lg font-semibold text-sand-900 leading-tight tabular">
                        {{ $application->date_from->format('j') }}
                    </p>
                </div>

                <div class="min-w-0 flex-1">
                    <p class="text-[13px] font-medium text-sand-900 truncate">{{ $application->user->name }}</p>
                    <p class="text-[11px] text-sand-500">
                        {{ $application->leave_type === 'VL' ? 'Vacation' : 'Sick' }} leave ·
                        {{ rtrim(rtrim(number_format((float) $application->days, 2), '0'), '.') }} day(s)
                        @if ($application->date_to && ! $application->date_to->eq($application->date_from))
                            · until {{ $application->date_to->format('M j') }}
                        @endif
                    </p>
                </div>

                <x-leave.status-pill :application="$application" />
            </div>
        @empty
            <x-empty-state title="Nothing scheduled"
                           message="No approved leave is coming up in your college."
                           icon="calendar-days" />
        @endforelse
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
            <x-empty-state message="You have not reviewed any leave yet." icon="check-circle" />
        @endforelse
    </x-card>
</div>

@endsection
