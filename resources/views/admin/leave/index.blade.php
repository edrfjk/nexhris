@extends('layouts.app')
@section('title', 'Leave Management')

@section('content')
<x-page-header title="Leave Management" subtitle="View employee leave balances and post earned credits.">
    <x-slot:actions>
        <a href="{{ route('admin.leave.pending') }}"
           class="relative inline-flex items-center gap-1.5 bg-maroon-800 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-maroon-900 transition">
          
            Pending Applications
            @if ($pendingCount > 0)
                <span class="absolute -top-2 -right-2 bg-red-600 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                    {{ $pendingCount }}
                </span>
            @endif
        </a>
    </x-slot:actions>
</x-page-header>

@if (session('success'))
    <div class="mb-4 flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.5 12.75l6 6 9-13.5"/></svg>
        {{ session('success') }}
    </div>
@endif

<!--
    Stat cards, mirroring the PDS index. Only $employees and $pendingCount are guaranteed
    available from the original controller, so these two are wired live. If you want the
    same visual weight as PDS's 5-card row, pass extra aggregates from the controller, e.g.:
      $lowVlCount   = number of active employees with vl_balance below a threshold
      $totalVlUsed  = sum of vl_used for the current year
    and drop them in as additional cards below using the same markup pattern.
-->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 ring-1 ring-gray-100 p-4">
        <div class="flex items-start justify-between mb-3">
            <div class="w-9 h-9 rounded-lg bg-gray-50 text-gray-600 flex items-center justify-center">
                <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                </svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-800 leading-none">{{ $employees->total() }}</p>
        <p class="text-xs text-gray-500 mt-1.5">Total Employees</p>
    </div>

    <a href="{{ route('admin.leave.pending') }}"
       class="bg-white rounded-xl shadow-sm border border-gray-100 ring-1 ring-yellow-100 p-4 hover:shadow-md hover:-translate-y-0.5 transition">
        <div class="flex items-start justify-between mb-3">
            <div class="w-9 h-9 rounded-lg bg-yellow-50 text-yellow-600 flex items-center justify-center">
                <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9.75L12 3.75m0 6l3-3m-3 3l-3-3M3.75 15.75v3a2.25 2.25 0 002.25 2.25h12a2.25 2.25 0 002.25-2.25v-3"/>
                </svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-800 leading-none">{{ $pendingCount }}</p>
        <p class="text-xs text-gray-500 mt-1.5">Pending Applications</p>
    </a>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 ring-1 ring-blue-100 p-4">
        <div class="flex items-start justify-between mb-3">
            <div class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-800 leading-none">{{ number_format($employees->getCollection()->avg(fn($e) => $e->leaveBalance->vl_balance ?? 0), 1) }}</p>
        <p class="text-xs text-gray-500 mt-1.5">Avg. VL Balance (page)</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 ring-1 ring-green-100 p-4">
        <div class="flex items-start justify-between mb-3">
            <div class="w-9 h-9 rounded-lg bg-green-50 text-green-600 flex items-center justify-center">
                <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-800 leading-none">{{ number_format($employees->getCollection()->avg(fn($e) => $e->leaveBalance->sl_balance ?? 0), 1) }}</p>
        <p class="text-xs text-gray-500 mt-1.5">Avg. SL Balance (page)</p>
    </div>
</div>

<!-- Filter toolbar, same shape as PDS: search + apply/clear + active-filter chips -->
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

        <div class="flex gap-2">
            <button type="submit"
                    class="inline-flex items-center gap-1.5 bg-gray-800 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-900 transition whitespace-nowrap">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75"/></svg>
                Search
            </button>
            @if (request()->hasAny(['search']))
                <a href="{{ route('admin.leave.index') }}"
                   class="inline-flex items-center gap-1.5 text-gray-500 border border-gray-300 px-3 py-2 rounded-lg text-sm hover:bg-gray-50 transition whitespace-nowrap">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Clear
                </a>
            @endif
        </div>
    </form>

    @if (request('search'))
        <div class="flex flex-wrap gap-2 mt-3 pt-3 border-t border-gray-100">
            <span class="inline-flex items-center gap-1 bg-maroon-50 text-maroon-800 text-xs font-medium px-2.5 py-1 rounded-full">
                Search: "{{ request('search') }}"
            </span>
        </div>
    @endif
</div>

<!-- Bulk post earned credits -->
<x-card title="Post Earned Credits to All Active Employees" class="mb-6">
    <form method="POST" action="{{ route('admin.leave.bulk-earned.store') }}"
          class="grid grid-cols-1 sm:grid-cols-5 gap-3 items-end text-sm"
          onsubmit="return confirm('Post these earned credits to every active employee? This cannot be undone in bulk.')">
        @csrf
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Period From</label>
            <input type="date" name="period_from" class="border border-gray-300 rounded-lg px-2.5 py-2 w-full focus:ring-2 focus:ring-maroon-700 focus:border-transparent" required>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Period To</label>
            <input type="date" name="period_to" class="border border-gray-300 rounded-lg px-2.5 py-2 w-full focus:ring-2 focus:ring-maroon-700 focus:border-transparent" required>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">VL Earned</label>
            <input type="number" step="0.001" name="vl_earned" class="border border-gray-300 rounded-lg px-2.5 py-2 w-full focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">SL Earned</label>
            <input type="number" step="0.001" name="sl_earned" class="border border-gray-300 rounded-lg px-2.5 py-2 w-full focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
        </div>
        <button class="inline-flex items-center justify-center gap-1.5 bg-gray-700 text-white rounded-lg px-4 py-2 hover:bg-gray-800 transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            Post to All
        </button>
    </form>
</x-card>

<!-- Table, with avatar cell matching PDS index -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-left text-gray-500">
            <tr>
                <th class="px-5 py-3 font-medium">Employee</th>
                <th class="px-5 py-3 font-medium">VL Balance</th>
                <th class="px-5 py-3 font-medium">SL Balance</th>
                <th class="px-5 py-3 font-medium text-right">Ledger</th>
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
                        <span class="font-semibold text-gray-700">{{ number_format($employee->leaveBalance->vl_balance ?? 0, 3) }}</span>
                    </td>
                    <td class="px-5 py-3">
                        <span class="font-semibold text-gray-700">{{ number_format($employee->leaveBalance->sl_balance ?? 0, 3) }}</span>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <a href="{{ route('admin.leave.ledger', $employee) }}"
                           class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-gray-500 hover:text-maroon-800 hover:bg-maroon-50 transition"
                           title="View Ledger">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4"><x-empty-state message="No employees found." /></td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $employees->links() }}</div>
@endsection