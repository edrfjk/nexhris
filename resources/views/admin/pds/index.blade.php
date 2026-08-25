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
            'gray' => ['bg' => 'bg-sand-50', 'text' => 'text-sand-600', 'ring' => 'ring-sand-100'],
            'blue' => ['bg' => 'bg-sky-50', 'text' => 'text-sky-600', 'ring' => 'ring-sky-100'],
            'yellow' => ['bg' => 'bg-gold-50', 'text' => 'text-gold-600', 'ring' => 'ring-gold-100'],
            'green' => ['bg' => 'bg-forest-50', 'text' => 'text-forest-600', 'ring' => 'ring-forest-100'],
            'red' => ['bg' => 'bg-red-50', 'text' => 'text-red-600', 'ring' => 'ring-red-100'],
        ];
    @endphp
    @foreach ($statMeta as $key => $meta)
        @php
            $count = $key === 'not_started' ? $totalEmployees - $counts->except('not_started')->sum() : ($counts[$key] ?? 0);
            $c = $colorMap[$meta['color']];
            $percent = $totalEmployees > 0 ? round(($count / $totalEmployees) * 100) : 0;
        @endphp
        <a href="{{ route('admin.pds.index', array_merge(request()->only(['college', 'department']), ['status' => $key, 'year' => $year])) }}"
           class="card card-interactive ring-1 {{ $c['ring'] }} p-4">
            <div class="flex items-start justify-between mb-3">
                <div class="w-9 h-9 rounded-lg {{ $c['bg'] }} {{ $c['text'] }} flex items-center justify-center">
                    <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $meta['icon'] }}"/>
                    </svg>
                </div>
                <span class="text-xs font-medium {{ $c['text'] }}">{{ $percent }}%</span>
            </div>
            <p class="text-2xl font-bold text-sand-800 leading-none">{{ $count }}</p>
            <p class="text-xs text-sand-500 mt-1.5">{{ $meta['label'] }}</p>
        </a>
    @endforeach
</div>

<!-- Active PDS form -->
<div class="card p-4 mb-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-maroon-50 text-maroon-800 flex items-center justify-center flex-shrink-0">
                <x-heroicon-o-document-text class="w-4.5 h-4.5" />
            </div>
            <div>
                @if ($activeTemplate)
                    <div class="flex items-center gap-2">
                        <p class="font-medium text-sand-800">{{ $activeTemplate->label }}</p>
                        <x-badge color="green">v{{ $activeTemplate->version }} · active</x-badge>
                    </div>
                    <p class="text-xs text-sand-400 mt-0.5">
                        {{ $activeTemplate->original_filename }} · published by {{ $activeTemplate->uploader->name ?? 'HR' }}
                        @if ($templates->count() > 1)
                            · {{ $templates->count() }} versions
                        @endif
                    </p>
                @else
                    <p class="font-medium text-sand-800">No active PDS form</p>
                    <p class="text-xs text-red-600 mt-0.5">Employees cannot download a blank PDS until one is published.</p>
                @endif
            </div>
        </div>

        {{-- Publishing lives on the Templates screen with the leave form and
             the master ledger, so all three blank forms are managed the same
             way instead of this one hiding in a panel here. --}}
        <a href="{{ route('admin.leave.templates.index', ['tab' => 'pds']) }}"
           class="btn btn-md btn-secondary flex-shrink-0">
            <x-heroicon-o-arrow-up-tray class="w-4 h-4" />
            Manage templates
        </a>
    </div>
</div>

@php
    $allDepartments = $colleges->pluck('activeDepartments')->flatten();
    $prettyStatus = fn ($v) => ucfirst(str_replace('_', ' ', $v));

    $chips = [
        ['key' => 'search', 'label' => 'Search', 'value' => request('search')],
        ['key' => 'status', 'label' => 'Status',
         'value' => request('status') ? $prettyStatus(request('status')) : null],
        ['key' => 'college', 'label' => 'College',
         'value' => $colleges->firstWhere('id', (int) request('college'))?->name],
        ['key' => 'department', 'label' => 'Department',
         'value' => $allDepartments->firstWhere('id', (int) request('department'))?->name],
    ];
@endphp

<x-filter-bar :chips="$chips" :clear="route('admin.pds.index')">

    <x-filter-field label="Search" :span="2">
        <x-filter-search placeholder="Name or employee number" />
    </x-filter-field>

    <x-filter-field label="Status">
        <select name="status" class="select">
            <option value="">All statuses</option>
            @foreach (['not_started', 'draft', 'submitted', 'approved', 'returned'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>
                    {{ $prettyStatus($status) }}
                </option>
            @endforeach
        </select>
    </x-filter-field>

    <x-filter-field label="Year">
        <select name="year" class="select">
            @foreach ($years as $y)
                <option value="{{ $y }}" @selected((int) $year === $y)>{{ $y }}</option>
            @endforeach
        </select>
    </x-filter-field>

    <x-filter-field label="College / Office">
        <select name="college" id="pds-college" class="select">
            <option value="">All colleges / offices</option>
            @foreach ($colleges as $college)
                <option value="{{ $college->id }}" @selected((string) request('college') === (string) $college->id)>
                    {{ $college->code }} — {{ $college->name }}
                </option>
            @endforeach
        </select>
    </x-filter-field>

    <x-filter-field label="Department" hint="Compliance figures follow this filter.">
        <select name="department" id="pds-department" class="select">
            <option value="">All departments</option>
        </select>
    </x-filter-field>

</x-filter-bar>

<div class="card overflow-hidden">
    <table class="table">
        <thead>
            <tr>
                <th>Employee</th>
                <th class="hidden lg:table-cell">College / Department</th>
                <th>Status ({{ $year }})</th>
                <th>Submitted On</th>
                <th class="text-right">Review</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($employees as $employee)
                @php $sub = $employee->pdsSubmissions->first(); @endphp
                <tr class="border-t border-sand-100 hover:bg-sand-50 transition">
                    <td>
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full overflow-hidden bg-maroon-50 flex items-center justify-center flex-shrink-0">
                                @if ($employee->profile_photo_path)
                                    <img src="{{ asset('storage/' . $employee->profile_photo_path) }}" class="w-full h-full object-cover">
                                @else
                                    <span class="text-sm font-semibold text-maroon-800">{{ strtoupper(substr($employee->name, 0, 1)) }}</span>
                                @endif
                            </div>
                            <div>
                                <p class="font-medium text-sand-800">{{ $employee->name }}</p>
                                <p class="text-xs text-sand-400">{{ $employee->employee_number }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="hidden lg:table-cell">
                        <p class="text-sand-700">{{ $employee->collegeName() ?: '—' }}</p>
                        @if ($employee->departmentName())
                            <p class="text-xs text-sand-400">{{ $employee->departmentName() }}</p>
                        @endif
                    </td>
                    <td>
                        <x-badge :color="match($sub->status ?? 'not_started') {
                            'approved' => 'green', 'submitted' => 'yellow', 'returned' => 'red', 'draft' => 'blue', default => 'gray',
                        }">
                            {{ ucfirst(str_replace('_', ' ', $sub->status ?? 'not_started')) }}
                        </x-badge>
                    </td>
                    <td>{{ $sub && $sub->submitted_at ? $sub->submitted_at->format('M d, Y g:i A') : '—' }}</td>
                    <td class="text-right">
                        <a href="{{ route('admin.pds.show', $employee) }}"
                           class="icon-btn"
                           title="Review PDS">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty-state message="No employees match your search or filters." /></td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $employees->links() }}</div>

{{-- The department list follows the chosen college. --}}
<script>
(function () {
    const byCollege = @json($colleges->mapWithKeys(fn ($c) => [
        $c->id => $c->activeDepartments->map(fn ($d) => ['id' => $d->id, 'label' => $d->name])->values(),
    ]));
    const collegeSelect = document.getElementById('pds-college');
    const departmentSelect = document.getElementById('pds-department');
    const preselected = @json(request('department'));

    function populate(collegeId, keepId) {
        departmentSelect.innerHTML = '<option value="">All departments</option>';

        const list = collegeId
            ? (byCollege[collegeId] || [])
            : Object.values(byCollege).flat();

        list.forEach(function (d) {
            const option = document.createElement('option');
            option.value = d.id;
            option.textContent = d.label;
            if (String(d.id) === String(keepId)) option.selected = true;
            departmentSelect.appendChild(option);
        });

        departmentSelect.disabled = list.length === 0;
    }

    populate(collegeSelect.value, preselected);
    collegeSelect.addEventListener('change', function () { populate(this.value, null); });
})();
</script>

@endsection
