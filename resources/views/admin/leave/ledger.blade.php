@extends('layouts.app')
@section('title', 'Leave Ledger Card')

@section('content')

@php
    $me = auth()->user();
    $isHr = $me->isAdmin();
    $num = fn ($v) => (float) $v > 0 ? number_format((float) $v, 2) : '—';
@endphp

<x-page-header
    :title="$employee->name"
    :subtitle="'Leave ledger card · ' . ($employee->employee_number ?: 'No employee number')">
    <x-slot:actions>
        <a href="{{ route('admin.leave.index') }}"
           class="btn btn-md btn-secondary">
            ← All employees
        </a>
        {{-- The official card, laid out from the campus template and
             rendered from the posted ledger entries. --}}
        <a href="{{ route('admin.leave.ledger.pdf', $employee) }}" target="_blank"
           class="btn btn-md btn-primary">
            <x-heroicon-o-printer />
            Print ledger card
        </a>
    </x-slot:actions>
</x-page-header>

{{-- ------------------------------------------------------------------
     Balances
     ------------------------------------------------------------------ --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-maroon-800 text-white rounded p-5 shadow-soft">
        <p class="text-[11px] font-medium text-white/70 uppercase tracking-wide">Vacation Leave</p>
        <p class="text-3xl font-bold mt-1">{{ number_format((float) ($balance->vl_balance ?? 0), 2) }}</p>
    </div>
    <div class="card p-5">
        <p class="section-label">Sick Leave</p>
        <p class="text-3xl font-bold mt-1 text-sand-800">{{ number_format((float) ($balance->sl_balance ?? 0), 2) }}</p>
    </div>
    <div class="card p-5">
        <p class="section-label">Service Credits</p>
        <p class="text-3xl font-bold mt-1 text-sand-800">{{ number_format((float) ($balance->service_balance ?? 0), 2) }}</p>
    </div>
</div>

{{-- ------------------------------------------------------------------
     HR entry forms
     ------------------------------------------------------------------ --}}
@if ($isHr)
    <div x-data="{ tab: 'earned' }" class="card mb-6 overflow-hidden">
        <div class="flex border-b border-sand-100">
            <button @click="tab = 'earned'"
                    :class="tab === 'earned' ? 'border-maroon-800 text-maroon-800' : 'border-transparent text-sand-500 hover:text-sand-700'"
                    class="px-5 py-3 text-sm font-medium border-b-2 transition">
                Post earned credits
            </button>
            <button @click="tab = 'adjust'"
                    :class="tab === 'adjust' ? 'border-maroon-800 text-maroon-800' : 'border-transparent text-sand-500 hover:text-sand-700'"
                    class="px-5 py-3 text-sm font-medium border-b-2 transition">
                Manual adjustment
            </button>
        </div>

        {{-- Earned --}}
        <form method="POST" action="{{ route('admin.leave.earned.store', $employee) }}"
              x-show="tab === 'earned'" class="p-5">
            @csrf
            {{-- This tab posts to the leave card. Service credits are earned
                 per event, so they are recorded in their own section below. --}}
            <input type="hidden" name="ledger" value="leave">
            <div class="grid grid-cols-2 lg:grid-cols-6 gap-3 items-end">
                <label class="block">
                    <span class="label">Period from</span>
                    <input type="date" name="period_from" required value="{{ now()->startOfMonth()->format('Y-m-d') }}"
                           class="input mt-1">
                </label>
                <label class="block">
                    <span class="label">Period to</span>
                    <input type="date" name="period_to" required value="{{ now()->endOfMonth()->format('Y-m-d') }}"
                           class="input mt-1">
                </label>
                <label class="block">
                    <span class="label">VL earned</span>
                    <input type="number" step="0.01" min="0" name="vl_earned" value="1.25"
                           class="input mt-1">
                </label>
                <label class="block">
                    <span class="label">SL earned</span>
                    <input type="number" step="0.01" min="0" name="sl_earned" value="1.25"
                           class="input mt-1">
                </label>
                <button class="btn btn-md btn-primary">
                    Post
                </button>
            </div>
            <label class="block mt-3">
                <span class="label">Remarks</span>
                <input type="text" name="remarks" maxlength="255"
                       placeholder="e.g. Service credits earned during the 2025 National Elections"
                       class="input mt-1">
            </label>
        </form>

        {{-- Adjustment --}}
        <form method="POST" action="{{ route('admin.leave.adjust.store', $employee) }}"
              x-show="tab === 'adjust'" x-cloak class="p-5">
            @csrf
            <p class="text-xs text-sand-500 mb-3 leading-relaxed">
                Use a positive number to add credits and a negative one to deduct them.
                Days taken with no credits left belong in the <strong>without pay</strong> columns,
                which are recorded on the card but do not change the balance.
            </p>
            <div class="grid grid-cols-2 lg:grid-cols-7 gap-3 items-end">
                <label class="block">
                    <span class="label">Date</span>
                    <input type="date" name="date" required value="{{ now()->format('Y-m-d') }}"
                           class="input mt-1">
                </label>
                <label class="block">
                    <span class="label">VL ±</span>
                    <input type="number" step="0.01" name="vl_adjustment" value="0"
                           class="input mt-1">
                </label>
                <label class="block">
                    <span class="label">SL ±</span>
                    <input type="number" step="0.01" name="sl_adjustment" value="0"
                           class="input mt-1">
                </label>
                <label class="block">
                    <span class="label">Service ±</span>
                    <input type="number" step="0.01" name="service_adjustment" value="0"
                           class="input mt-1">
                </label>
                <label class="block">
                    <span class="label">VL w/o pay</span>
                    <input type="number" step="0.01" min="0" name="vl_used_wop" value="0"
                           class="input mt-1">
                </label>
                <label class="block">
                    <span class="label">SL w/o pay</span>
                    <input type="number" step="0.01" min="0" name="sl_used_wop" value="0"
                           class="input mt-1">
                </label>
                <button class="btn btn-md btn-primary">
                    Post
                </button>
            </div>
            <label class="block mt-3">
                <span class="label">Remarks <span class="text-red-500">*</span></span>
                <input type="text" name="remarks" required maxlength="255"
                       placeholder="e.g. Opening balance as of December 31, 2024"
                       class="input mt-1">
            </label>
        </form>
    </div>
@endif

{{-- ------------------------------------------------------------------
     The ledger card, laid out like the official form
     ------------------------------------------------------------------ --}}
<div class="card overflow-hidden mb-6">
    <div class="px-5 py-3.5 border-b border-sand-100 flex items-center justify-between">
        <div>
            <h3 class="font-semibold text-sm text-sand-700">Leave ledger card</h3>
            <p class="text-xs text-sand-400 mt-0.5">
                Days charged against vacation or sick credits. Kept to two decimal places.
            </p>
        </div>
        <span class="text-xs text-sand-400">
            {{ $ledger->reject->isOnServiceCard()->count() }} entries
        </span>
    </div>

    @if ($ledger->reject->isOnServiceCard()->isEmpty())
        <x-empty-state message="No leave has been charged to this card yet." />
    @else
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr class="bg-sand-100 text-sand-600">
                        <th colspan="3">Period</th>
                        <th colspan="4" class="text-center">Vacation Leave</th>
                        <th colspan="4" class="text-center">Sick Leave</th>
                        @if ($isHr)<th></th>@endif
                    </tr>
                    <tr class="bg-sand-50 text-sand-500 text-[10px] uppercase">
                        <th class="text-left">From</th>
                        <th class="text-left">To</th>
                        <th class="text-left">Remarks</th>
                        <th class="text-center">Earned</th>
                        <th class="text-center">W/ pay</th>
                        <th class="text-center">W/o pay</th>
                        <th class="text-center">Balance</th>
                        <th class="text-center">Earned</th>
                        <th class="text-center">W/ pay</th>
                        <th class="text-center">W/o pay</th>
                        <th class="text-center">Balance</th>
                        @if ($isHr)<th class="text-right">Correct</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @php $lastYear = null; @endphp
                    @foreach ($ledger->reject->isOnServiceCard() as $row)
                        @php $year = $row->year_label ?: $row->period_from?->format('Y'); @endphp

                        @if ($year && $year !== $lastYear)
                            <tr class="bg-sand-100">
                                <td colspan="{{ $isHr ? 12 : 11 }}">{{ $year }}</td>
                            </tr>
                            @php $lastYear = $year; @endphp
                        @endif

                        <tr x-data="{ editing: false }" class="hover:bg-sand-50/70 transition">
                            <td class="whitespace-nowrap">{{ $row->period_from?->format('M j, Y') }}</td>
                            <td class="whitespace-nowrap">
                                {{ $row->period_to && ! $row->period_to->eq($row->period_from) ? $row->period_to->format('M j, Y') : '' }}
                            </td>
                            <td class="max-w-[240px]">
                                <span class="block truncate" title="{{ $row->remarks }}">{{ $row->remarks ?: '—' }}</span>
                            </td>
                            <td class="text-center">{{ $num($row->vl_earned) }}</td>
                            <td class="text-center">{{ $num($row->vl_used) }}</td>
                            <td class="text-center">{{ $num($row->vl_used_wop) }}</td>
                            <td class="text-center">
                                {{ number_format((float) $row->vl_balance, 2) }}
                            </td>
                            <td class="text-center">{{ $num($row->sl_earned) }}</td>
                            <td class="text-center">{{ $num($row->sl_used) }}</td>
                            <td class="text-center">{{ $num($row->sl_used_wop) }}</td>
                            <td class="text-center">
                                {{ number_format((float) $row->sl_balance, 2) }}
                            </td>
                            @if ($isHr)
                                <td class="text-right whitespace-nowrap">
                                    <button type="button" @click="editing = !editing"
                                            class="text-xs font-medium text-maroon-700 hover:text-maroon-900">
                                        Correct
                                    </button>
                                </td>
                            @endif
                        </tr>

                        @if ($isHr)
                            {{-- Correcting a line replays every balance below
                                 it, so the card stays arithmetically sound. --}}
                            <tr x-show="editing" x-cloak class="bg-sand-50">
                                <td colspan="12" class="p-4">
                                    <form method="POST"
                                          action="{{ route('admin.leave.ledger.entry.update', $row) }}"
                                          class="space-y-3">
                                        @csrf @method('PUT')

                                        <div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
                                            <label class="block">
                                                <span class="label">From</span>
                                                <input type="date" name="period_from" required
                                                       value="{{ $row->period_from?->format('Y-m-d') }}" class="input mt-1">
                                            </label>
                                            <label class="block">
                                                <span class="label">To</span>
                                                <input type="date" name="period_to" required
                                                       value="{{ $row->period_to?->format('Y-m-d') }}" class="input mt-1">
                                            </label>
                                            <label class="block lg:col-span-4">
                                                <span class="label">Remarks</span>
                                                <input type="text" name="remarks" maxlength="255"
                                                       value="{{ $row->remarks }}" class="input mt-1">
                                            </label>
                                        </div>

                                        <div class="grid grid-cols-3 lg:grid-cols-8 gap-3">
                                            @foreach ([
                                                'vl_earned' => 'VL earned',
                                                'vl_used' => 'VL w/ pay',
                                                'vl_used_wop' => 'VL w/o pay',
                                                'sl_earned' => 'SL earned',
                                                'sl_used' => 'SL w/ pay',
                                                'sl_used_wop' => 'SL w/o pay',
                                                'service_earned' => 'Service earned',
                                                'service_used' => 'Service used',
                                            ] as $field => $label)
                                                <label class="block">
                                                    <span class="label">{{ $label }}</span>
                                                    <input type="number" step="0.01" min="0" name="{{ $field }}"
                                                           value="{{ rtrim(rtrim(number_format((float) $row->{$field}, 2, '.', ''), '0'), '.') ?: '0' }}"
                                                           class="input mt-1">
                                                </label>
                                            @endforeach
                                        </div>

                                        <div class="flex items-center gap-2 pt-1">
                                            <button class="btn btn-sm btn-primary">Save correction</button>
                                            <button type="button" @click="editing = false"
                                                    class="btn btn-sm btn-secondary">Cancel</button>

                                            <span class="flex-1"></span>

                                            <button type="submit" form="delete-entry-{{ $row->id }}"
                                                    class="btn btn-sm btn-danger-soft">
                                                Remove this line
                                            </button>
                                        </div>
                                    </form>

                                    <form id="delete-entry-{{ $row->id }}" method="POST"
                                          action="{{ route('admin.leave.ledger.entry.destroy', $row) }}"
                                          onsubmit="return confirm({{ Js::from('Remove this line from the card? Every balance below it will be recalculated.') }})">
                                        @csrf @method('DELETE')
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- ------------------------------------------------------------------
     Service credits — the lines that print on the second card
     ------------------------------------------------------------------ --}}
@php
    // The service credit ledger is the same card filtered to the lines that
    // move service credits, so this section edits exactly those.
    $serviceLines = $ledger->filter->touchesServiceCredits()->values();
@endphp

<div x-data="{ adding: false }" class="card overflow-hidden mb-6">
    <div class="px-5 py-3.5 border-b border-sand-100 flex items-center justify-between gap-3">
        <div>
            <h3 class="font-semibold text-sm text-sand-700">Service credits</h3>
            <p class="text-xs text-sand-400 mt-0.5">
                Credits earned outside regular accrual — election duty, enrolment,
                accreditation work — and the days charged against them. A sick or
                vacation day written on this card comes off service credits, not
                the leave balance. Kept to three decimal places.
            </p>
        </div>
        @if ($isHr)
            <button @click="adding = !adding" class="btn btn-sm btn-primary">
                <span x-text="adding ? 'Cancel' : 'Record credits'"></span>
            </button>
        @endif
    </div>

    @if ($isHr)
        <form method="POST" action="{{ route('admin.leave.earned.store', $employee) }}"
              x-show="adding" x-cloak class="p-5 bg-sand-50 border-b border-sand-100">
            @csrf
            <input type="hidden" name="ledger" value="service">

            <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 items-end">
                <label class="block">
                    <span class="label">Period from</span>
                    <input type="date" name="period_from" required
                           value="{{ now()->format('Y-m-d') }}" class="input mt-1">
                </label>
                <label class="block">
                    <span class="label">Period to</span>
                    <input type="date" name="period_to" required
                           value="{{ now()->format('Y-m-d') }}" class="input mt-1">
                </label>
                <label class="block">
                    <span class="label">Credits earned</span>
                    <input type="number" step="0.01" min="0" name="service_earned" value="0"
                           class="input mt-1">
                </label>

                {{-- The posting form writes one ledger line, so the leave
                     columns stay at zero on a service credit entry. --}}
                <input type="hidden" name="vl_earned" value="0">
                <input type="hidden" name="sl_earned" value="0">

                <label class="block lg:col-span-2">
                    <span class="label">Remarks</span>
                    <input type="text" name="remarks" maxlength="255"
                           placeholder="e.g. Service credits earned during the 2026 Barangay Elections"
                           class="input mt-1">
                </label>
            </div>

            <div class="flex justify-end mt-3">
                <button class="btn btn-md btn-primary">Record</button>
            </div>
        </form>
    @endif

    @if ($serviceLines->isEmpty())
        <x-empty-state
            title="No service credits recorded"
            message="Nothing has been earned or spent against service credits yet."
            icon="clock" />
    @else
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th class="text-left">From</th>
                        <th class="text-left">To</th>
                        <th class="text-left">Remarks</th>
                        <th class="text-center">Earned</th>
                        <th class="text-center">Used</th>
                        <th class="text-center">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($serviceLines as $line)
                        <tr class="hover:bg-sand-50/70 transition">
                            <td class="whitespace-nowrap">{{ $line->period_from?->format('M j, Y') }}</td>
                            <td class="whitespace-nowrap">
                                {{ $line->period_to && ! $line->period_to->eq($line->period_from)
                                    ? $line->period_to->format('M j, Y') : '' }}
                            </td>
                            <td class="max-w-[320px]">
                                <span class="block truncate" title="{{ $line->remarks }}">
                                    {{ $line->remarks ?: '—' }}
                                </span>
                            </td>
                            <td class="text-center">{{ $num($line->service_earned) }}</td>
                            <td class="text-center">
                                {{-- A sick or vacation day written on this card
                                     is charged to service credits. --}}
                                {{ $line->daysCharged() ?: '' }}
                            </td>
                            <td class="text-center font-medium">
                                {{ number_format((float) $line->service_balance, 3) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="px-5 py-3 border-t border-sand-100 text-xs text-sand-500">
            These lines belong to the service credit card only; they do not appear on the leave card.
        </div>
    @endif
</div>

{{-- ------------------------------------------------------------------
     This employee's leave applications
     ------------------------------------------------------------------ --}}
<div class="card overflow-hidden">
    <div class="px-5 py-3.5 border-b border-sand-100">
        <h3 class="font-semibold text-sm text-sand-700">Leave applications</h3>
    </div>

    @if ($applications->isEmpty())
        <x-empty-state message="This employee has not filed any leave yet." />
    @else
        <ul class="divide-y divide-sand-100">
            @foreach ($applications as $application)
                <li class="px-5 py-4 flex items-center justify-between gap-4 flex-wrap">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <x-badge :color="$application->leave_type === 'VL' ? 'blue' : 'purple'">
                                {{ $application->leave_type === 'VL' ? 'Vacation' : 'Sick' }}
                            </x-badge>
                            <p class="text-sm font-medium text-sand-800">
                                {{ $application->date_from?->format('M j, Y') }}
                                @if ($application->date_to && ! $application->date_to->eq($application->date_from))
                                    – {{ $application->date_to->format('M j, Y') }}
                                @endif
                            </p>
                            <span class="text-xs text-sand-400">
                                {{ rtrim(rtrim(number_format((float) $application->days, 2), '0'), '.') }} day(s)
                            </span>
                        </div>
                        @if ($application->reason)
                            <p class="text-xs text-sand-500 mt-1">{{ $application->reason }}</p>
                        @endif
                    </div>

                    <div class="flex items-center gap-3 flex-shrink-0">
                        @if ($application->awaitsLedgerPosting() && $isHr)
                            <x-badge color="yellow">Needs ledger posting</x-badge>
                        @endif
                        <x-leave.status-pill :application="$application" />
                        <a href="{{ route('admin.leave.review.show', $application) }}"
                           class="text-xs font-medium text-maroon-700 hover:text-maroon-900">Open</a>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="px-5 py-3 border-t border-sand-100">
            {{ $applications->links() }}
        </div>
    @endif
</div>

@endsection
