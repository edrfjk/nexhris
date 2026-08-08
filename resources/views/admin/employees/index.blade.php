@extends('layouts.app')
@section('title', 'Employee Accounts')

@section('content')
<x-page-header title="Employee Accounts" subtitle="Manage HR-created employee accounts.">
    <x-slot:actions>
        <a href="{{ route('admin.employees.create') }}"
           class="inline-flex items-center gap-1.5 bg-maroon-800 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-maroon-900 transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15"/></svg>
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
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 ring-1 ring-gray-100 p-4">
        <div class="w-9 h-9 rounded-lg bg-gray-50 text-gray-600 flex items-center justify-center mb-3">
            <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
        </div>
        <p class="text-2xl font-bold text-gray-800 leading-none">{{ $employees->total() }}</p>
        <p class="text-xs text-gray-500 mt-1.5">Total Employees</p>
    </div>

    <a href="{{ request()->fullUrlWithQuery(['status' => 'active']) }}"
       class="bg-white rounded-xl shadow-sm border border-gray-100 ring-1 ring-green-100 p-4 hover:shadow-md hover:-translate-y-0.5 transition">
        <div class="w-9 h-9 rounded-lg bg-green-50 text-green-600 flex items-center justify-center mb-3">
            <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <p class="text-2xl font-bold text-gray-800 leading-none">{{ $activeCountVal }}</p>
        <p class="text-xs text-gray-500 mt-1.5">Active {{ $isGlobal ? '' : '(page)' }}</p>
    </a>

    <a href="{{ request()->fullUrlWithQuery(['status' => 'inactive']) }}"
       class="bg-white rounded-xl shadow-sm border border-gray-100 ring-1 ring-gray-100 p-4 hover:shadow-md hover:-translate-y-0.5 transition">
        <div class="w-9 h-9 rounded-lg bg-gray-50 text-gray-500 flex items-center justify-center mb-3">
            <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
        </div>
        <p class="text-2xl font-bold text-gray-800 leading-none">{{ $inactiveCountVal }}</p>
        <p class="text-xs text-gray-500 mt-1.5">Inactive {{ $isGlobal ? '' : '(page)' }}</p>
    </a>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 ring-1 ring-blue-100 p-4">
        <div class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center mb-3">
            <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        </div>
        <p class="text-2xl font-bold text-gray-800 leading-none">{{ $newCountVal }}</p>
        <p class="text-xs text-gray-500 mt-1.5">New This Month {{ $isGlobal ? '' : '(page)' }}</p>
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
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name, employee no., or email"
                       class="border border-gray-300 rounded-lg pl-9 pr-3 py-2 text-sm w-full focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
            </div>
        </div>

        <div class="w-full sm:w-64">
            <label class="block text-xs font-medium text-gray-500 mb-1.5">College / Office</label>
            <select name="college"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-full focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
                <option value="">All Colleges/Offices</option>
                @foreach ($colleges as $code => $college)
                    <option value="{{ $code }}" @selected(request('college') === $code)>{{ $code }} — {{ $college['name'] }}</option>
                @endforeach
            </select>
        </div>

        <div class="w-full sm:w-40">
            <label class="block text-xs font-medium text-gray-500 mb-1.5">Status</label>
            <select name="status"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-full focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
                <option value="">All</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
        </div>

        <div class="w-full sm:w-48">
            <label class="block text-xs font-medium text-gray-500 mb-1.5">Sort By</label>
            <select name="sort"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-full focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
                <option value="" @selected(!request('sort'))>Name (A-Z)</option>
                <option value="newest" @selected(request('sort') === 'newest')>Newest First</option>
                <option value="oldest" @selected(request('sort') === 'oldest')>Oldest First</option>
                <option value="employee_number" @selected(request('sort') === 'employee_number')>Employee No.</option>
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit"
                    class="inline-flex items-center gap-1.5 bg-gray-800 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-900 transition whitespace-nowrap">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75"/></svg>
                Apply Filters
            </button>

            @if (request()->hasAny(['search', 'college', 'status', 'sort']))
                <a href="{{ route('admin.employees.index') }}"
                   class="inline-flex items-center gap-1.5 text-gray-500 border border-gray-300 px-3 py-2 rounded-lg text-sm hover:bg-gray-50 transition whitespace-nowrap">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Clear
                </a>
            @endif
        </div>
    </form>

    <!-- Active filter chips -->
    @if (request()->hasAny(['search', 'college', 'status']))
        <div class="flex flex-wrap gap-2 mt-3 pt-3 border-t border-gray-100">
            @if (request('search'))
                <span class="inline-flex items-center gap-1.5 bg-maroon-50 text-maroon-800 text-xs font-medium px-2.5 py-1 rounded-full">
                    Search: "{{ request('search') }}"
                    <a href="{{ request()->fullUrlWithQuery(['search' => null]) }}" class="hover:text-maroon-950" aria-label="Remove search filter">&times;</a>
                </span>
            @endif
            @if (request('college'))
                <span class="inline-flex items-center gap-1.5 bg-maroon-50 text-maroon-800 text-xs font-medium px-2.5 py-1 rounded-full">
                    College: {{ request('college') }}
                    <a href="{{ request()->fullUrlWithQuery(['college' => null]) }}" class="hover:text-maroon-950" aria-label="Remove college filter">&times;</a>
                </span>
            @endif
            @if (request('status'))
                <span class="inline-flex items-center gap-1.5 bg-maroon-50 text-maroon-800 text-xs font-medium px-2.5 py-1 rounded-full">
                    Status: {{ ucfirst(request('status')) }}
                    <a href="{{ request()->fullUrlWithQuery(['status' => null]) }}" class="hover:text-maroon-950" aria-label="Remove status filter">&times;</a>
                </span>
            @endif
        </div>
    @endif
</div>

<!-- Table -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

    <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 text-sm text-gray-500">
        <span>
            @if ($employees->total() > 0)
                Showing <span class="font-medium text-gray-700">{{ $employees->firstItem() }}–{{ $employees->lastItem() }}</span>
                of <span class="font-medium text-gray-700">{{ $employees->total() }}</span> employees
            @else
                No employees found
            @endif
        </span>
    </div>

    {{-- Desktop table --}}
    <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="px-5 py-3 font-medium">Employee</th>
                    <th class="px-5 py-3 font-medium">Employee No.</th>
                    <th class="px-5 py-3 font-medium">College / Program</th>
                    <th class="px-5 py-3 font-medium">Status</th>
                    <th class="px-5 py-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($employees as $employee)
                    <tr class="border-t border-gray-100 hover:bg-gray-50 transition">
                        <td class="px-5 py-3">
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
                                    <p class="font-medium text-gray-800">{{ $employee->name }}</p>
                                    <p class="text-xs text-gray-400">{{ $employee->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-gray-600">{{ $employee->employee_number }}</td>
                        <td class="px-5 py-3 text-gray-600">
                            @if ($employee->department)
                                <p class="font-medium text-gray-700">{{ $employee->department }}</p>
                                <p class="text-xs text-gray-400">{{ $employee->program ?: '—' }}</p>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            <x-badge :color="$employee->status === 'active' ? 'green' : 'gray'">
                                {{ ucfirst($employee->status) }}
                            </x-badge>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.employees.show', $employee) }}"
                                   class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-gray-500 hover:text-maroon-800 hover:bg-maroon-50 transition"
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
                    <tr><td colspan="5"><x-empty-state message="No employees match your search or filters." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile card list --}}
    <div class="md:hidden divide-y divide-gray-100">
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
                        <p class="font-medium text-gray-800 truncate">{{ $employee->name }}</p>
                        <p class="text-xs text-gray-400 truncate">{{ $employee->employee_number }} · {{ $employee->department ?: 'No department' }}</p>
                    </div>
                </a>
                <x-badge :color="$employee->status === 'active' ? 'green' : 'gray'">
                    {{ ucfirst($employee->status) }}
                </x-badge>
                <a href="{{ route('admin.employees.edit', $employee) }}" class="text-gray-400 hover:text-maroon-800 p-1" title="Edit">
                    <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                </a>
            </div>
        @empty
            <x-empty-state message="No employees match your search or filters." />
        @endforelse
    </div>
</div>

<div class="mt-4">{{ $employees->links() }}</div>
@endsection