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

<!-- PDS form template -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-maroon-50 text-maroon-800 flex items-center justify-center flex-shrink-0">
                <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <div>
                @if ($activeTemplate)
                    <div class="flex items-center gap-2">
                        <p class="font-medium text-gray-800">{{ $activeTemplate->label }}</p>
                        <x-badge color="green">Active</x-badge>
                    </div>
                    <p class="text-xs text-gray-400 mt-0.5">
                        {{ $activeTemplate->original_filename }} · uploaded by {{ $activeTemplate->uploader->name ?? '—' }}
                    </p>
                @else
                    <p class="font-medium text-gray-800">No active PDS template</p>
                    <p class="text-xs text-red-500 mt-0.5">Employees can't open the PDS editor until one is uploaded and activated.</p>
                @endif
            </div>
        </div>

        <button type="button" onclick="document.getElementById('template-panel').classList.toggle('hidden')"
                class="inline-flex items-center gap-1.5 text-sm text-gray-600 border border-gray-300 rounded-lg px-3 py-2 hover:bg-gray-50 transition whitespace-nowrap flex-shrink-0">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
            Manage Templates
        </button>
    </div>

    <div id="template-panel" class="hidden mt-4 pt-4 border-t border-gray-100">

        <form method="POST" action="{{ route('admin.pds.templates.store') }}" enctype="multipart/form-data"
              class="flex flex-col sm:flex-row sm:items-end gap-3 mb-4">
            @csrf
            <div class="flex-1">
                <label class="block text-xs font-medium text-gray-500 mb-1">Label</label>
                <input type="text" name="label" placeholder="e.g. CS Form 212 (Revised 2027)"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-700 focus:border-transparent" required>
            </div>
            <div class="flex-1">
                <label class="block text-xs font-medium text-gray-500 mb-1">File (.xlsx)</label>
                <input type="file" name="file" accept=".xlsx" class="text-sm w-full" required>
            </div>
            <button class="bg-maroon-800 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-maroon-900 transition whitespace-nowrap">
                Upload
            </button>
        </form>

        <div class="space-y-2">
            @forelse ($templates as $template)
                <div class="flex items-center justify-between text-sm py-2.5 px-2 rounded-lg hover:bg-gray-50 transition {{ !$loop->last ? 'border-b border-gray-100' : '' }}">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <x-badge :color="$template->is_active ? 'green' : 'gray'">
                            {{ $template->is_active ? 'Active' : 'Inactive' }}
                        </x-badge>
                        <div class="min-w-0">
                            <p class="font-medium text-gray-700 truncate">{{ $template->label }}</p>
                            <p class="text-xs text-gray-400 truncate">
                                {{ $template->original_filename }} · {{ $template->created_at->format('M d, Y') }} · by {{ $template->uploader->name ?? '—' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 flex-shrink-0 ml-3">
                        <a href="{{ asset('storage/' . $template->file_path) }}" target="_blank"
                           class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition"
                           title="Download this file">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                        </a>

                        @if ($template->is_active)
                            <span class="inline-flex items-center gap-1 text-xs font-medium text-green-700 bg-green-50 px-2.5 py-1.5 rounded-lg">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                In Use
                            </span>
                        @else
                            <form action="{{ route('admin.pds.templates.activate', $template) }}" method="POST">
                                @csrf
                                <button class="inline-flex items-center gap-1 text-xs font-medium text-blue-700 border border-blue-200 bg-blue-50 px-2.5 py-1.5 rounded-lg hover:bg-blue-100 transition">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                    Activate
                                </button>
                            </form>
                        @endif

                        <form action="{{ route('admin.pds.templates.destroy', $template) }}" method="POST"
                              onsubmit="return confirm({{ $template->is_active
                                    ? Js::from('This is the ACTIVE template. Deleting it will stop employees from opening the PDS editor until you activate another one. Delete anyway?')
                                    : Js::from('Delete this template? This cannot be undone.') }})">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-red-700 hover:bg-red-50 transition"
                                    title="Delete this template">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <x-empty-state message="No templates uploaded yet." />
            @endforelse
        </div>
    </div>
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