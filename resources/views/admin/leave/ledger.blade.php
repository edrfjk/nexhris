@extends('layouts.app')
@section('title', "Leave Ledger — {$employee->name}")

@section('content')
<x-page-header title="Leave Ledger">
    <x-slot:actions>
        <a href="{{ route('admin.leave.index') }}"
           class="inline-flex items-center gap-1.5 text-sm text-gray-600 border border-gray-300 rounded-lg px-3 py-2 hover:bg-gray-50 transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
            </svg>
            Back to Leave Management
        </a>
    </x-slot:actions>
</x-page-header>

@if (session('success'))
    <div class="mb-4 flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.5 12.75l6 6 9-13.5"/></svg>
        {{ session('success') }}
    </div>
@endif

    <!-- Profile header banner -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-6">
        <div class="h-24 bg-gradient-to-r from-maroon-900 via-maroon-800 to-maroon-900"></div>
        <div class="px-6 pb-6">
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 -mt-12">
                <div class="flex items-end gap-4">
                    <div class="w-24 h-24 rounded-full border-4 border-white bg-gray-100 overflow-hidden shadow-md flex-shrink-0">
                        @if ($employee->profile_photo_path)
                            <img src="{{ asset('storage/' . $employee->profile_photo_path) }}" class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-2xl font-bold text-gray-400">
                                {{ strtoupper(substr($employee->name, 0, 1)) }}
                            </div>
                        @endif
                    </div>
                    <div class="pb-1">
                        <h2 class="text-lg font-bold text-gray-800">{{ $employee->name }}</h2>
                        <p class="text-sm text-gray-500">{{ $employee->position ?: 'Employee' }} · {{ $employee->department ?: 'No department' }}</p>
                    </div>
                </div>

            <div class="flex items-center gap-2 pb-1">
                <a href="{{ route('admin.employees.show', $employee) }}"
                   class="text-sm text-gray-600 border border-gray-300 rounded-lg px-3 py-2 hover:bg-gray-50 transition">Employee Details</a>
                <a href="{{ route('admin.pds.show', $employee) }}"
                   class="text-sm text-gray-600 border border-gray-300 rounded-lg px-3 py-2 hover:bg-gray-50 transition">PDS Review</a>
            </div>
        </div>
    </div>
</div>

<!-- Balance cards, styled like the PDS stat cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 ring-1 ring-blue-100 p-4">
        <div class="flex items-start justify-between mb-3">
            <div class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-800 leading-none">{{ number_format($balance->vl_balance ?? 0, 3) }}</p>
        <p class="text-xs text-gray-500 mt-1.5">Vacation Leave Balance</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 ring-1 ring-green-100 p-4">
        <div class="flex items-start justify-between mb-3">
            <div class="w-9 h-9 rounded-lg bg-green-50 text-green-600 flex items-center justify-center">
                <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-800 leading-none">{{ number_format($balance->sl_balance ?? 0, 3) }}</p>
        <p class="text-xs text-gray-500 mt-1.5">Sick Leave Balance</p>
    </div>
</div>

<x-card title="Post Earned Credits" class="mb-6">
    <form method="POST" action="{{ route('admin.leave.earned.store', $employee) }}"
          class="grid grid-cols-1 sm:grid-cols-5 gap-3 items-end text-sm">
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
        <button class="inline-flex items-center justify-center gap-1.5 bg-maroon-800 text-white rounded-lg px-3 py-2 hover:bg-maroon-900 transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            Post Earned Credits
        </button>
    </form>
</x-card>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-5 py-3 border-b border-gray-100">
        <h3 class="text-sm font-semibold text-gray-700">Ledger History</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-xs text-center">
            <thead class="bg-gray-50">
                <tr>
                    <th rowspan="2" class="border-b border-gray-100 px-2 py-2 align-middle text-left">Period</th>
                    <th rowspan="2" class="border-b border-gray-100 px-2 py-2 align-middle text-left">Remarks</th>
                    <th colspan="3" class="border-b border-gray-100 px-2 py-2">Vacation Leave</th>
                    <th colspan="3" class="border-b border-gray-100 px-2 py-2">Sick Leave</th>
                </tr>
                <tr class="text-gray-500">
                    <th class="border-b border-gray-100 px-2 py-1">Earned</th>
                    <th class="border-b border-gray-100 px-2 py-1">Used</th>
                    <th class="border-b border-gray-100 px-2 py-1">Balance</th>
                    <th class="border-b border-gray-100 px-2 py-1">Earned</th>
                    <th class="border-b border-gray-100 px-2 py-1">Used</th>
                    <th class="border-b border-gray-100 px-2 py-1">Balance</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($ledger as $row)
                    <tr class="border-t border-gray-100 hover:bg-gray-50 transition">
                        <td class="px-2 py-2 text-left">{{ $row->period_from->format('M d, Y') }} – {{ $row->period_to->format('M d, Y') }}</td>
                        <td class="px-2 py-2 text-left text-gray-600">{{ $row->remarks }}</td>
                        <td class="px-2 py-2">{{ $row->vl_earned ?: '' }}</td>
                        <td class="px-2 py-2">{{ $row->vl_used ?: '' }}</td>
                        <td class="px-2 py-2 font-semibold text-gray-700">{{ number_format($row->vl_balance, 3) }}</td>
                        <td class="px-2 py-2">{{ $row->sl_earned ?: '' }}</td>
                        <td class="px-2 py-2">{{ $row->sl_used ?: '' }}</td>
                        <td class="px-2 py-2 font-semibold text-gray-700">{{ number_format($row->sl_balance, 3) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-empty-state message="No ledger entries yet." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection