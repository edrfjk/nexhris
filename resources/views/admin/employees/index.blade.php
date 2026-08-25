@extends('layouts.app')
@section('title', 'Employee Accounts')

@section('content')
<x-page-header title="Employee Accounts" subtitle="Manage HR-created employee accounts.">
    <x-slot:actions>
        <a href="{{ route('admin.employees.export.pdf', request()->query()) }}" target="_blank"
           class="btn btn-md btn-secondary">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
            Export PDF
        </a>
        <a href="{{ route('admin.employees.create') }}"
           class="btn btn-md btn-primary">
            <x-heroicon-o-plus class="w-4 h-4" />
            Add Employee
        </a>
    </x-slot:actions>
</x-page-header>

@php
    // Falls back to page-level counts if the controller hasn't been updated yet
    // with the global $activeCount / $inactiveCount / $newThisMonthCount vars.
    $activeCountVal   = $activeCount ?? $employees->getCollection()->where('status', 'active')->count();
    $inactiveCountVal = $inactiveCount ?? $employees->getCollection()->where('status', 'inactive')->count();
    $newCountVal      = $newThisMonthCount ?? $employees->getCollection()->where('created_at', '>=', now()->startOfMonth())->count();
    $isGlobal         = isset($activeCount);
@endphp

<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="card ring-1 ring-sand-100 p-4">
        <div class="w-9 h-9 rounded-lg bg-sand-50 text-sand-600 flex items-center justify-center mb-3">
            <x-heroicon-o-users class="w-4.5 h-4.5" />
        </div>
        <p class="text-2xl font-bold text-sand-800 leading-none">{{ $employees->total() }}</p>
        <p class="text-xs text-sand-500 mt-1.5">Total Employees</p>
    </div>

    <a href="{{ request()->fullUrlWithQuery(['status' => 'active']) }}"
       class="card ring-1 ring-forest-100 p-4 hover:shadow-soft transition">
        <div class="w-9 h-9 rounded-lg bg-forest-50 text-forest-600 flex items-center justify-center mb-3">
            <x-heroicon-o-check-circle class="w-4.5 h-4.5" />
        </div>
        <p class="text-2xl font-bold text-sand-800 leading-none">{{ $activeCountVal }}</p>
        <p class="text-xs text-sand-500 mt-1.5">Active {{ $isGlobal ? '' : '(page)' }}</p>
    </a>

    <a href="{{ request()->fullUrlWithQuery(['status' => 'inactive']) }}"
       class="card ring-1 ring-sand-100 p-4 hover:shadow-soft transition">
        <div class="w-9 h-9 rounded-lg bg-sand-50 text-sand-500 flex items-center justify-center mb-3">
            <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
        </div>
        <p class="text-2xl font-bold text-sand-800 leading-none">{{ $inactiveCountVal }}</p>
        <p class="text-xs text-sand-500 mt-1.5">Inactive {{ $isGlobal ? '' : '(page)' }}</p>
    </a>

    <div class="card ring-1 ring-sky-100 p-4">
        <div class="w-9 h-9 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center mb-3">
            <x-heroicon-o-plus class="w-4.5 h-4.5" />
        </div>
        <p class="text-2xl font-bold text-sand-800 leading-none">{{ $newCountVal }}</p>
        <p class="text-xs text-sand-500 mt-1.5">New This Month {{ $isGlobal ? '' : '(page)' }}</p>
    </div>
</div>

@php
    $allDepartments = $colleges->pluck('activeDepartments')->flatten();

    $sortLabels = [
        'newest' => 'Newest first',
        'oldest' => 'Oldest first',
        'employee_number' => 'Employee no.',
    ];

    $chips = [
        ['key' => 'search', 'label' => 'Search', 'value' => request('search')],
        // This printed the raw college id before — "College: 3".
        ['key' => 'college', 'label' => 'College',
         'value' => $colleges->firstWhere('id', (int) request('college'))?->name],
        ['key' => 'department', 'label' => 'Department',
         'value' => $allDepartments->firstWhere('id', (int) request('department'))?->name],
        ['key' => 'status', 'label' => 'Status',
         'value' => request('status') ? ucfirst(request('status')) : null],
        ['key' => 'sort', 'label' => 'Sorted', 'value' => $sortLabels[request('sort')] ?? null],
    ];
@endphp

<x-filter-bar :chips="$chips" :clear="route('admin.employees.index')">

    <x-filter-field label="Search" :span="2">
        <x-filter-search placeholder="Name, employee no. or email" />
    </x-filter-field>

    <x-filter-field label="College / Office">
        <select name="college" id="filter-college" class="select">
            <option value="">All colleges / offices</option>
            @foreach ($colleges as $college)
                <option value="{{ $college->id }}" @selected((string) request('college') === (string) $college->id)>
                    {{ $college->code }} — {{ $college->name }}
                </option>
            @endforeach
        </select>
    </x-filter-field>

    <x-filter-field label="Department">
        <select name="department" id="filter-department" class="select">
            <option value="">All departments</option>
            @foreach ($colleges as $college)
                <optgroup label="{{ $college->code }}" data-college="{{ $college->id }}">
                    @foreach ($college->activeDepartments as $department)
                        <option value="{{ $department->id }}"
                                data-college="{{ $college->id }}"
                                @selected((string) request('department') === (string) $department->id)>
                            {{ $department->code }} — {{ $department->name }}
                        </option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
    </x-filter-field>

    <x-filter-field label="Status">
        <select name="status" class="select">
            <option value="">All statuses</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
    </x-filter-field>

    <x-filter-field label="Order">
        <select name="sort" class="select">
            <option value="" @selected(! request('sort'))>Name (A–Z)</option>
            @foreach ($sortLabels as $value => $label)
                <option value="{{ $value }}" @selected(request('sort') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </x-filter-field>

</x-filter-bar>

<!-- Table -->
<div class="card overflow-hidden">

    <div class="flex items-center justify-between px-5 py-3 border-b border-sand-100 text-sm text-sand-500">
        <span>
            @if ($employees->total() > 0)
                Showing <span class="font-medium text-sand-700">{{ $employees->firstItem() }}–{{ $employees->lastItem() }}</span>
                of <span class="font-medium text-sand-700">{{ $employees->total() }}</span> employees
            @else
                No employees found
            @endif
        </span>
    </div>

    {{-- Desktop table --}}
    <div class="hidden md:block overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Employee No.</th>
                    <th>College / Program</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($employees as $employee)
                    <tr class="border-t border-sand-100 hover:bg-sand-50 transition">
                        <td>
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full overflow-hidden bg-maroon-50 flex items-center justify-center flex-shrink-0">
                                    @if ($employee->profile_photo_path)
                                        <img src="{{ asset('storage/' . $employee->profile_photo_path) }}" class="w-full h-full object-cover">
                                    @else
                                        <span class="text-sm font-semibold text-maroon-800">
                                            {{ collect(explode(' ', $employee->name))->map(fn($p) => mb_substr($p, 0, 1))->take(2)->join('') }}
                                        </span>
                                    @endif
                                </div>
                                <div>
                                    <p class="font-medium text-sand-800">{{ $employee->name }}</p>
                                    <p class="text-xs text-sand-400">{{ $employee->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td>{{ $employee->employee_number }}</td>
                        <td>
                            @if ($employee->college)
                                <p class="font-medium text-sand-700">{{ $employee->college->code }}</p>
                                <p class="text-xs text-sand-400">{{ $employee->departmentName() ?? 'No department' }}</p>
                            @else
                                <span class="badge badge-amber">
                                    <x-heroicon-o-exclamation-triangle />
                                    Unassigned
                                </span>
                            @endif
                        </td>
                        <td>
                            @if ($employee->isEmployee())
                                <span class="text-sand-500">—</span>
                            @else
                                <x-badge :color="$employee->isDean() ? 'violet' : 'amber'">
                                    {{ $employee->roleLabel() }}
                                </x-badge>
                            @endif
                        </td>
                        <td>
                            <x-badge :color="$employee->status === 'active' ? 'green' : 'gray'">
                                {{ ucfirst($employee->status) }}
                            </x-badge>
                        </td>
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.employees.show', $employee) }}"
                                   class="icon-btn"
                                   title="View employee">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty-state message="No employees match your search or filters." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile card list --}}
    <div class="md:hidden divide-y divide-sand-100">
        @forelse ($employees as $employee)
            <div class="flex items-center gap-3 p-4">
                <a href="{{ route('admin.employees.show', $employee) }}" class="flex items-center gap-3 flex-1 min-w-0">
                    <div class="w-10 h-10 rounded-full overflow-hidden bg-maroon-50 flex items-center justify-center flex-shrink-0">
                        @if ($employee->profile_photo_path)
                            <img src="{{ asset('storage/' . $employee->profile_photo_path) }}" class="w-full h-full object-cover">
                        @else
                            <span class="text-sm font-semibold text-maroon-800">
                                {{ collect(explode(' ', $employee->name))->map(fn($p) => mb_substr($p, 0, 1))->take(2)->join('') }}
                            </span>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="font-medium text-sand-800 truncate">{{ $employee->name }}</p>
                        <p class="text-xs text-sand-400 truncate">{{ $employee->employee_number }} · {{ $employee->college->code ?? 'Unassigned' }}</p>
                    </div>
                </a>
                <x-badge :color="$employee->status === 'active' ? 'green' : 'gray'">
                    {{ ucfirst($employee->status) }}
                </x-badge>
                <a href="{{ route('admin.employees.edit', $employee) }}" class="text-sand-400 hover:text-maroon-800 p-1" title="Edit">
                    <x-heroicon-o-pencil-square class="w-4.5 h-4.5" />
                </a>
            </div>
        @empty
            <x-empty-state message="No employees match your search or filters." />
        @endforelse
    </div>
</div>

<div class="mt-4">{{ $employees->links() }}</div>
@endsection

@push('scripts')
<script>
(function () {
    // Narrow the department filter to whichever college is selected, so HR is
    // not scrolling past every programme on campus.
    const college = document.getElementById('filter-college');
    const department = document.getElementById('filter-department');

    if (!college || !department) return;

    function sync() {
        const chosen = college.value;

        Array.from(department.querySelectorAll('optgroup')).forEach(function (group) {
            group.hidden = chosen !== '' && group.dataset.college !== chosen;
        });

        // A department from a college that is no longer selected must not stay
        // applied, or the filters would contradict each other.
        const selected = department.options[department.selectedIndex];
        if (selected && selected.dataset.college && chosen !== '' && selected.dataset.college !== chosen) {
            department.value = '';
        }
    }

    sync();
    college.addEventListener('change', sync);
})();
</script>
@endpush
