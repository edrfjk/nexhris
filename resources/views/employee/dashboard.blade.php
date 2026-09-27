@extends('layouts.app')
@section('title', 'My Dashboard')

@section('content')

@php
    $me = auth()->user();
    $greeting = now()->hour < 12 ? 'Good morning' : (now()->hour < 18 ? 'Good afternoon' : 'Good evening');
    $firstName = explode(' ', trim(str_replace(',', ' ', $me->name)))[0];

    $balance = $data['balance'];
    $pds = $data['pds'];
    $pdsStatus = $pds->status ?? 'not_started';
    $active = $data['activeApplication'];
@endphp

<x-page-header
    :title="$greeting . ', ' . $firstName"
    :subtitle="$me->position ? $me->position . ' · ' . ($me->college->name ?? 'ISPSC Tagudin Campus') : 'Employee record overview'" />

{{-- ---------------- Attention first ---------------- --}}
{{-- Anything a reviewer sent back, one row each: what it is, who returned
     it and why, and the one button that fixes it. --}}
@php
    $returnedForms = $data['returnedForms'] ?? collect();
    $attentionCount = $data['returned'] + ($pdsStatus === 'returned' ? 1 : 0);
    $returnedBy = [
        'dean_returned' => 'the Dean',
        'hr_returned' => 'HR',
        'cd_returned' => 'the Campus Director',
    ];
@endphp

@if ($attentionCount > 0)
    <section class="card mb-6 overflow-hidden border-red-200" aria-labelledby="attention-title">
        <div class="flex items-center gap-3 border-b border-red-100 bg-red-50 px-5 py-3">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-700">
                <x-heroicon-s-exclamation-triangle class="w-4 h-4" />
            </span>
            <div class="min-w-0">
                <h2 id="attention-title" class="text-sm font-semibold text-red-900">Needs your attention</h2>
                <p class="text-xs text-red-700">
                    {{ $attentionCount }} {{ $attentionCount === 1 ? 'item was' : 'items were' }} returned to you for correction.
                </p>
            </div>
        </div>

        <ul class="divide-y divide-sand-100">
            @if ($pdsStatus === 'returned')
                <li class="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-start">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-maroon-50 text-maroon-700">
                        <x-heroicon-o-document-text class="w-5 h-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-sand-900">Personal Data Sheet ({{ $pds->applicable_year }})</p>
                        <p class="text-xs text-sand-500">
                            Returned by HR{{ $pds->reviewed_at ? ' · ' . $pds->reviewed_at->format('M j, Y') : '' }}
                        </p>
                        @if (filled($pds->return_remarks))
                            <blockquote class="mt-2 rounded-md border-l-2 border-red-300 bg-sand-50 px-3 py-2 text-sm text-sand-700">
                                {{ $pds->return_remarks }}
                            </blockquote>
                        @endif
                    </div>
                    <a href="{{ route('pds.editor') }}" class="btn btn-sm btn-primary shrink-0 self-start">
                        Correct my PDS
                    </a>
                </li>
            @endif

            @foreach ($returnedForms as $form)
                @php
                    $dates = match (true) {
                        ! $form->date_from => null,
                        ! $form->date_to || $form->date_to->isSameDay($form->date_from) => $form->date_from->format('M j, Y'),
                        default => $form->date_from->format('M j') . ' – ' . $form->date_to->format('M j, Y'),
                    };
                @endphp
                <li class="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-start">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-700">
                        <x-heroicon-o-calendar-days class="w-5 h-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-sand-900">
                            {{ $form->typeLabel() }}
                            @if ($dates)
                                <span class="font-normal text-sand-500">· {{ $dates }}</span>
                            @endif
                        </p>
                        <p class="text-xs text-sand-500">
                            Returned by {{ $returnedBy[$form->status] ?? 'a reviewer' }} · {{ $form->updated_at->format('M j, Y') }}
                        </p>
                        @if (filled($form->remarks))
                            <blockquote class="mt-2 rounded-md border-l-2 border-red-300 bg-sand-50 px-3 py-2 text-sm text-sand-700">
                                {{ $form->remarks }}
                            </blockquote>
                        @endif
                    </div>
                    <a href="{{ route('leave.index') }}" class="btn btn-sm btn-primary shrink-0 self-start">
                        Correct and re-upload
                    </a>
                </li>
            @endforeach

            @if ($data['returned'] > $returnedForms->count())
                <li class="px-5 py-3 text-sm">
                    <a href="{{ route('leave.index') }}" class="font-medium text-maroon-700 hover:underline">
                        See all {{ $data['returned'] }} returned leave forms →
                    </a>
                </li>
            @endif
        </ul>
    </section>
@endif

{{-- ---------------- Balances ---------------- --}}
<p class="section-label mb-3">My leave credits</p>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <x-stat-card label="Vacation Leave" :value="number_format((float) ($balance->vl_balance ?? 0), 2)"
                 hint="days available" icon="calendar-days" />
    <x-stat-card label="Sick Leave" color="blue" :value="number_format((float) ($balance->sl_balance ?? 0), 2)"
                 hint="days available" icon="heart" />
    <x-stat-card label="Service Credits" color="green"
                 :value="number_format((float) ($balance->service_balance ?? 0), 2)"
                 hint="days available" icon="trophy" />
    <x-stat-card label="Used This Year" color="amber"
                 :value="number_format((float) $data['usedThisYear'], 2)"
                 hint="approved days" icon="clock" :href="route('leave.index')" />
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    {{-- ---------------- Leave in progress ---------------- --}}
    <x-card title="My leave application" class="lg:col-span-2">
        <x-slot:actions>
            <a href="{{ route('leave.index') }}" class="btn btn-xs btn-secondary">All applications</a>
        </x-slot:actions>

        @if ($active)
            <div class="flex items-center justify-between gap-3 flex-wrap mb-5">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <x-badge :color="$active->leave_type === 'VL' ? 'blue' : 'violet'">
                            {{ $active->typeLabel() }}
                        </x-badge>
                        <span class="text-[13px] font-semibold text-sand-900">
                            {{ $active->date_from?->format('M j, Y') }}
                            @if ($active->date_to && ! $active->date_to->eq($active->date_from))
                                – {{ $active->date_to->format('M j, Y') }}
                            @endif
                        </span>
                    </div>
                </div>
                <x-leave.status-pill :application="$active" />
            </div>

            <x-leave.stepper :application="$active" />

            @if ($active->isFullyApproved())
                <a href="{{ route('leave.print', $active) }}" target="_blank"
                   class="btn btn-sm btn-success mt-5">
                    <x-heroicon-o-printer />
                    Print approval sheet
                </a>
            @endif
        @else
            <x-empty-state title="No leave in progress"
                           message="Download the official form, fill it in, and upload it for review.">
                <x-slot:action>
                    <a href="{{ route('leave.index') }}" class="btn btn-md btn-primary">File a leave application</a>
                </x-slot:action>
            </x-empty-state>
        @endif
    </x-card>

    {{-- ---------------- Records ---------------- --}}
    <x-card title="My records">
        <dl class="space-y-3.5 text-[13px]">
            <div class="flex items-center justify-between gap-3">
                <dt class="text-sand-500">PDS {{ now()->year }}</dt>
                <dd>
                    <x-badge :color="$pds?->statusTone() ?? 'slate'">
                        {{ $pds?->statusLabel() ?? 'Not started' }}
                    </x-badge>
                </dd>
            </div>
            <div class="flex items-center justify-between gap-3">
                <dt class="text-sand-500">Unread policies</dt>
                <dd>
                    @if ($data['policiesUnread'] > 0)
                        <a href="{{ route('policies.index') }}" class="badge badge-amber">
                            {{ $data['policiesUnread'] }} to read
                        </a>
                    @else
                        <span class="badge badge-green">All read</span>
                    @endif
                </dd>
            </div>
            <div class="flex items-center justify-between gap-3">
                <dt class="text-sand-500">Employee number</dt>
                <dd class="font-medium text-sand-800 tabular">{{ $me->employee_number ?: '—' }}</dd>
            </div>
            <div class="flex items-center justify-between gap-3">
                <dt class="text-sand-500">College / Office</dt>
                <dd class="font-medium text-sand-800 text-right">{{ $me->college->name ?? '—' }}</dd>
            </div>
            <div class="flex items-center justify-between gap-3">
                <dt class="text-sand-500">Date hired</dt>
                <dd class="font-medium text-sand-800 tabular">
                    {{ ($me->date_hired ?? $me->first_day_of_service)?->format('M j, Y') ?: '—' }}
                </dd>
            </div>
        </dl>

        <div class="mt-4 pt-4 border-t border-sand-100 grid grid-cols-2 gap-2">
            <a href="{{ route('leave.ledger.mine') }}" target="_blank" class="btn btn-sm btn-secondary">
                My ledger
            </a>
            {{-- Only once there is a workbook to convert: the export route
                 turns an empty PDS back with a message, which reads as a dead
                 button to anyone who has not filed one yet. --}}
            @if ($pds?->file_path)
                <a href="{{ route('pds.export', [
                        \App\Support\DocumentName::personalDataSheet(auth()->user(), $pds->applicable_year),
                   ]) }}"
                   target="_blank" class="btn btn-sm btn-secondary">
                    My PDS
                </a>
            @else
                {{-- Filled in on screen or uploaded — My PDS offers both. --}}
                <a href="{{ route('pds.editor') }}" class="btn btn-sm btn-secondary">
                    File my PDS
                </a>
            @endif
        </div>
    </x-card>
</div>

{{-- ---------------- Announcements ---------------- --}}
<x-card title="Latest announcements" class="mt-5">
    <x-slot:actions>
        <a href="{{ route('announcements.index') }}" class="btn btn-xs btn-secondary">View all</a>
    </x-slot:actions>

    @forelse ($data['announcements'] as $announcement)
        <div class="py-3 {{ ! $loop->last ? 'border-b border-sand-100' : '' }}">
            <div class="flex items-center gap-2 flex-wrap">
                @if ($announcement->is_pinned)
                    <span class="badge badge-maroon">Pinned</span>
                @endif
                <p class="text-[13px] font-medium text-sand-900">{{ $announcement->title }}</p>
                <span class="text-[11px] text-sand-400">{{ $announcement->published_at?->format('M j') }}</span>
            </div>
            <p class="text-xs text-sand-600 mt-1">{{ $announcement->excerpt(24) }}</p>
        </div>
    @empty
        <x-empty-state message="No announcements have been posted yet." icon="megaphone" />
    @endforelse
</x-card>

@endsection
