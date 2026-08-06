@extends('layouts.app')
@section('title', 'PDS Requests')

@section('content')
<x-page-header title="PDS Requests" subtitle="Monitor employee Personal Data Sheet submissions." />

<!-- Stat cards -->
<div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6">
    @php
        $statMeta = [
            'not_started' => ['label' => 'Not Started', 'color' => 'gray', 'icon' => 'M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z'],
            'draft' => ['label' => 'Draft', 'color' => 'blue', 'icon' => 'M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z'],
            'submitted' => ['label' => 'Submitted', 'color' => 'yellow', 'icon' => 'M12 9.75L12 3.75m0 6l3-3m-3 3l-3-3M3.75 15.75v3a2.25 2.25 0 002.25 2.25h12a2.25 2.25 0 002.25-2.25v-3'],
            'approved' => ['label' => 'Approved', 'color' => 'green', 'icon' => 'M4.5 12.75l6 6 9-13.5'],
            'returned' => ['label' => 'Returned', 'color' => 'red', 'icon' => 'M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3'],
        ];
        $colorMap = [
            'gray' => ['bg' => 'bg-gray-50', 'text' => 'text-gray-600', 'ring' => 'ring-gray-100'],
            'blue' => ['bg' => 'bg-blue-50', 'text' => 'text-blue-600', 'ring' => 'ring-blue-100'],
            'yellow' => ['bg' => 'bg-yellow-50', 'text' => 'text-yellow-600', 'ring' => 'ring-yellow-100'],
            'green' => ['bg' => 'bg-green-50', 'text' => 'text-green-600', 'ring' => 'ring-green-100'],
            'red' => ['bg' => 'bg-red-50', 'text' => 'text-red-600', 'ring' => 'ring-red-100'],
        ];
    @endphp
    @foreach ($statMeta as $key => $meta)
        @php
            $count = $key === 'not_started' ? $totalEmployees - $counts->except('not_started')->sum() : ($counts[$key] ?? 0);
            $c = $colorMap[$meta['color']];
            $percent = $totalEmployees > 0 ? round(($count / $totalEmployees) * 100) : 0;
        @endphp
        <a href="{{ route('admin.pds.index', ['status' => $key, 'year' => $year]) }}"
           class="bg-white rounded-xl shadow-sm border border-gray-100 ring-1 {{ $c['ring'] }} p-4 hover:shadow-md hover:-translate-y-0.5 transition">
            <div class="flex items-start justify-between mb-3">
                <div class="w-9 h-9 rounded-lg {{ $c['bg'] }} {{ $c['text'] }} flex items-center justify-center">
                    <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $meta['icon'] }}"/>
                    </svg>
                </div>
                <span class="text-xs font-medium {{ $c['text'] }}">{{ $percent }}%</span>
            </div>
            <p class="text-2xl font-bold text-gray-800 leading-none">{{ $count }}</p>
            <p class="text-xs text-gray-500 mt-1.5">{{ $meta['label'] }}</p>
        </a>
    @endforeach
</div>

<!-- Filter toolbar -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
    <form method="GET" class="flex flex-col sm:flex-row sm:items-end gap-3">
        <div class="flex-1 min-w-[220px]">
            <label class="block text-xs font-medium text-gray-500 mb-1.5">Search</label>
            <div class="relative">
                <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                </svg>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name or employee no."
                       class="border border-gray-300 rounded-lg pl-9 pr-3 py-2 text-sm w-full focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
            </div>
        </div>

        <div class="w-full sm:w-48">
            <label class="block text-xs font-medium text-gray-500 mb-1.5">Status</label>
            <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-full focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
                <option value="">All Statuses</option>
                @foreach (['not_started', 'draft', 'submitted', 'approved', 'returned'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                @endforeach
            </select>
        </div>

        <div class="w-full sm:w-32">
            <label class="block text-xs font-medium text-gray-500 mb-1.5">Year</label>
            <select name="year" class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-full focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
                @foreach ($years as $y)
                    <option value="{{ $y }}" @selected((int) $year === $y)>{{ $y }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit"
                    class="inline-flex items-center gap-1.5 bg-gray-800 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-900 transition whitespace-nowrap">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75"/></svg>
                Apply Filters
            </button>
            @if (request()->hasAny(['search', 'status']))
                <a href="{{ route('admin.pds.index') }}"
                   class="inline-flex items-center gap-1.5 text-gray-500 border border-gray-300 px-3 py-2 rounded-lg text-sm hover:bg-gray-50 transition whitespace-nowrap">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Clear
                </a>
            @endif
        </div>
    </form>

    @if (request()->hasAny(['search', 'status']))
        <div class="flex flex-wrap gap-2 mt-3 pt-3 border-t border-gray-100">
            @if (request('search'))
                <span class="inline-flex items-center gap-1 bg-maroon-50 text-maroon-800 text-xs font-medium px-2.5 py-1 rounded-full">
                    Search: "{{ request('search') }}"
                </span>
            @endif
            @if (request('status'))
                <span class="inline-flex items-center gap-1 bg-maroon-50 text-maroon-800 text-xs font-medium px-2.5 py-1 rounded-full">
                    Status: {{ ucfirst(str_replace('_', ' ', request('status'))) }}
                </span>
            @endif
        </div>
    @endif
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-left text-gray-500">
            <tr>
                <th class="px-5 py-3 font-medium">Employee</th>
                <th class="px-5 py-3 font-medium">Status ({{ $year }})</th>
                <th class="px-5 py-3 font-medium">Submitted On</th>
                <th class="px-5 py-3 font-medium text-right">Review</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($employees as $employee)
                @php $sub = $employee->pdsSubmissions->first(); @endphp
                <tr class="border-t border-gray-100 hover:bg-gray-50 transition">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full overflow-hidden bg-maroon-50 flex items-center justify-center flex-shrink-0">
                                @if ($employee->profile_photo_path)
                                    <img src="{{ asset('storage/' . $employee->profile_photo_path) }}" class="w-full h-full object-cover">
                                @else
                                    <span class="text-sm font-semibold text-maroon-800">{{ strtoupper(substr($employee->name, 0, 1)) }}</span>
                                @endif
                            </div>
                            <div>
                                <p class="font-medium text-gray-800">{{ $employee->name }}</p>
                                <p class="text-xs text-gray-400">{{ $employee->employee_number }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3">
                        <x-badge :color="match($sub->status ?? 'not_started') {
                            'approved' => 'green', 'submitted' => 'yellow', 'returned' => 'red', 'draft' => 'blue', default => 'gray',
                        }">
                            {{ ucfirst(str_replace('_', ' ', $sub->status ?? 'not_started')) }}
                        </x-badge>
                    </td>
                    <td class="px-5 py-3 text-gray-500">{{ $sub && $sub->submitted_at ? $sub->submitted_at->format('M d, Y g:i A') : '—' }}</td>
                    <td class="px-5 py-3 text-right">
                        <a href="{{ route('admin.pds.show', $employee) }}"
                           class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-gray-500 hover:text-maroon-800 hover:bg-maroon-50 transition"
                           title="Review PDS">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4"><x-empty-state message="No employees match your search or filters." /></td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $employees->links() }}</div>
@endsection