@extends('layouts.app')
@section('title', 'Holidays')

@section('content')

@php
    $categoryColors = [
        'regular' => 'red',
        'special_non_working' => 'amber',
        'special_local' => 'blue',
        'special_working' => 'gray',
        'religious' => 'green',
    ];
@endphp

<x-page-header
    title="Holiday Calendar"
    subtitle="Holidays marked “not charged” are left out of vacation and sick leave days. Enter each date from its official proclamation.">
    <x-slot:actions>
        <button type="button" class="btn btn-md btn-primary"
                onclick="document.getElementById('add-holiday').showModal()">
            <x-heroicon-o-plus />
            Add Holiday
        </button>
    </x-slot:actions>
</x-page-header>

<div class="alert alert-info mb-4">
    <x-heroicon-o-information-circle />
    <span class="text-[13px]">
        Changes apply to leave filed or corrected from now on. Forms already filed, approved or posted to a ledger keep
        the days they were filed with. Maternity leave and other calendar-day leave are not affected.
    </span>
</div>

<form method="GET" action="{{ route('admin.holidays.index') }}" class="flex flex-wrap items-end gap-3 mb-4">
    <div>
        <label for="year" class="label">Year</label>
        <select id="year" name="year" class="select" onchange="this.form.submit()">
            @foreach ($years as $y)
                <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
            @endforeach
        </select>
    </div>
    <noscript><button class="btn btn-md btn-secondary">Show</button></noscript>
</form>

<x-card>
    @if ($holidays->isEmpty())
        <x-empty-state title="No holidays for {{ $year }}"
                       message="Add the regular holidays and special non-working days proclaimed for this year, and any local holidays that apply to the campus."
                       icon="sun" />
    @else
        {{-- Phones: one card per holiday, so the date, treatment and the Edit /
             Remove actions are all on screen without sideways scrolling. --}}
        <ul class="sm:hidden divide-y divide-sand-200" aria-label="Holidays in {{ $year }}">
            @foreach ($holidays as $holiday)
                <li class="py-3 space-y-1.5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-sand-800">{{ $holiday->name }}</p>
                            <p class="text-xs text-sand-500 tabular">{{ $holiday->date->format('D, M j, Y') }}</p>
                        </div>
                        @if ($holiday->excludes_from_leave)
                            <x-badge color="green">Not charged</x-badge>
                        @else
                            <x-badge color="gray">Charged</x-badge>
                        @endif
                    </div>
                    <p class="text-xs text-sand-600">
                        {{ $holiday->categoryLabel() }} · {{ $holiday->scope === 'local' ? $holiday->locality : 'Nationwide' }}
                        @if ($holiday->source)<span class="block text-sand-500">{{ $holiday->source }}</span>@endif
                    </p>
                    <div class="flex gap-2 pt-1">
                        <button type="button" class="btn btn-sm btn-secondary"
                                onclick="document.getElementById('edit-holiday-{{ $holiday->id }}').showModal()">
                            Edit<span class="sr-only"> {{ $holiday->name }}</span>
                        </button>
                        <form method="POST" action="{{ route('admin.holidays.destroy', $holiday) }}"
                              onsubmit="return confirm({{ Js::from('Remove ' . $holiday->name . ' from the calendar? Leave already filed is not changed.') }})">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger-soft">Remove<span class="sr-only"> {{ $holiday->name }}</span></button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="hidden sm:block relative overflow-x-auto">
            <table class="w-full text-[13px]">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wide text-sand-500 border-b border-sand-200">
                        <th class="text-left font-semibold px-5 py-2">Date</th>
                        <th class="text-left font-semibold px-4 py-2">Holiday</th>
                        <th class="text-left font-semibold px-4 py-2">Category</th>
                        <th class="text-left font-semibold px-4 py-2 hidden md:table-cell">Applies to</th>
                        <th class="text-left font-semibold px-4 py-2">Leave</th>
                        <th class="px-5 py-2"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sand-200">
                    @foreach ($holidays as $holiday)
                        <tr>
                            <td class="px-5 py-2.5 whitespace-nowrap tabular">
                                {{ $holiday->date->format('M j') }}
                                <span class="text-sand-400">· {{ $holiday->date->format('D') }}</span>
                            </td>
                            <td class="px-4 py-2.5">
                                <p class="font-medium text-sand-800">{{ $holiday->name }}</p>
                                @if ($holiday->source)
                                    <p class="text-[11px] text-sand-500">{{ $holiday->source }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                <x-badge :color="$categoryColors[$holiday->category] ?? 'gray'">{{ $holiday->categoryLabel() }}</x-badge>
                            </td>
                            <td class="px-4 py-2.5 hidden md:table-cell text-sand-600">
                                {{ $holiday->scope === 'local' ? $holiday->locality : 'Nationwide' }}
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                @if ($holiday->excludes_from_leave)
                                    <x-badge color="green">Not charged</x-badge>
                                @else
                                    <x-badge color="gray">Charged</x-badge>
                                @endif
                            </td>
                            <td class="px-5 py-2.5">
                                <div class="flex justify-end gap-1.5">
                                    <button type="button" class="btn btn-xs btn-secondary"
                                            onclick="document.getElementById('edit-holiday-{{ $holiday->id }}').showModal()">
                                        Edit<span class="sr-only"> {{ $holiday->name }}</span>
                                    </button>
                                    <form method="POST" action="{{ route('admin.holidays.destroy', $holiday) }}"
                                          onsubmit="return confirm({{ Js::from('Remove ' . $holiday->name . ' from the calendar? Leave already filed is not changed.') }})">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-xs btn-danger-soft">Remove<span class="sr-only"> {{ $holiday->name }}</span></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-card>

@include('admin.holidays.partials.dialog', ['holiday' => null, 'dialogId' => 'add-holiday'])

@foreach ($holidays as $holiday)
    @include('admin.holidays.partials.dialog', ['holiday' => $holiday, 'dialogId' => 'edit-holiday-' . $holiday->id])
@endforeach

@endsection
