@extends('layouts.app')
@section('title', "Leave Ledger — {$employee->name}")

@section('content')
<x-page-header title="Leave Ledger">
    <x-slot:actions>
        <a href="{{ route('admin.leave.ledger.pdf', $employee) }}" target="_blank"
           class="inline-flex items-center gap-1.5 bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-800 transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9.75L12 3.75m0 6l3-3m-3 3l-3-3M3.75 15.75v3a2.25 2.25 0 002.25 2.25h12a2.25 2.25 0 002.25-2.25v-3"/></svg>
            Export PDF
        </a>
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

<x-card title="Manual Adjustment" class="mb-6">
    <p class="text-xs text-gray-400 mb-3">Use this only for corrections (e.g. encoding errors). Positive values add credits, negative values deduct.</p>
    <form method="POST" action="{{ route('admin.leave.adjust.store', $employee) }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end text-sm">
        @csrf
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Date</label>
            <input type="date" name="date" class="border border-gray-300 rounded-lg px-2.5 py-2 w-full focus:ring-2 focus:ring-maroon-700 focus:border-transparent" required>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">VL Adjustment</label>
            <input type="number" step="0.001" name="vl_adjustment" placeholder="e.g. -1.5" class="border border-gray-300 rounded-lg px-2.5 py-2 w-full focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">SL Adjustment</label>
            <input type="number" step="0.001" name="sl_adjustment" placeholder="e.g. -1.5" class="border border-gray-300 rounded-lg px-2.5 py-2 w-full focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
        </div>
        <button class="inline-flex items-center justify-center gap-1.5 bg-gray-700 text-white rounded-lg px-4 py-2 hover:bg-gray-800 transition">
            Post Adjustment
        </button>
        <div class="sm:col-span-4">
            <label class="block text-xs font-medium text-gray-500 mb-1">Remarks (required)</label>
            <input type="text" name="remarks" placeholder="Reason for this adjustment" class="border border-gray-300 rounded-lg px-2.5 py-2 w-full focus:ring-2 focus:ring-maroon-700 focus:border-transparent" required>
        </div>
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

{{-- Leave Applications History --}}
<div class="mt-8 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-800">Leave Applications</h3>
        <p class="text-sm text-gray-500">History of leave requests submitted by this employee.</p>
    </div>

    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-left text-gray-500">
            <tr>
                <th class="px-5 py-3 font-medium">Type</th>
                <th class="px-5 py-3 font-medium">Period</th>
                <th class="px-5 py-3 font-medium">Days</th>
                <th class="px-5 py-3 font-medium">Status</th>
                <th class="px-5 py-3 font-medium text-center">Reason</th>
            </tr>
        </thead>

        <tbody>
        @forelse ($applications as $app)
            <tr class="border-t border-gray-100 hover:bg-gray-50">
                <td class="px-5 py-3">
                    <x-badge :color="$app->leave_type === 'VL' ? 'blue' : 'green'">
                        {{ $app->leave_type }}
                    </x-badge>
                </td>

                <td class="px-5 py-3">
                    {{ $app->date_from->format('M d, Y') }}
                    —
                    {{ $app->date_to->format('M d, Y') }}
                </td>

                <td class="px-5 py-3">
                    {{ $app->days }}
                </td>

                <td class="px-5 py-3">
                    <x-badge :color="match($app->status){
                        'approved' => 'green',
                        'declined' => 'red',
                        default => 'yellow'
                    }">
                        {{ ucfirst($app->status) }}
                    </x-badge>
                </td>

<td class="px-5 py-3 text-center">
    @if($app->reason)
        <div x-data="{ open: false }">
        <button
            @click="open = true"
            class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-gray-500 hover:text-maroon-800 hover:bg-maroon-50 transition"
            title="View Reason">

            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.5"
                    d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.5"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>

        </button>

            <template x-teleport="body">
                <div
                    x-show="open"
                    x-cloak
                    class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4"
                    @click.self="open = false">

                    <div
                        class="bg-white rounded-xl shadow-lg max-w-md w-full p-5"
                        @click.stop>

                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <p class="font-semibold text-gray-800">
                                    {{ $app->leave_type }}
                                    —
                                    {{ $app->date_from->format('M d') }}
                                    to
                                    {{ $app->date_to->format('M d, Y') }}
                                </p>

                                <x-badge :color="match($app->status){
                                    'approved'=>'green',
                                    'declined'=>'red',
                                    default=>'yellow'
                                }" class="inline-flex mt-2">
                                    {{ ucfirst($app->status) }}
                                </x-badge>
                            </div>

                            <button
                                @click="open = false"
                                class="text-gray-400 hover:text-gray-600">

                                <svg class="w-5 h-5"
                                     fill="none"
                                     viewBox="0 0 24 24"
                                     stroke="currentColor">
                                    <path stroke-linecap="round"
                                          stroke-linejoin="round"
                                          stroke-width="2"
                                          d="M6 18L18 6M6 6l12 12"/>
                                </svg>

                            </button>
                        </div>

                        <p class="text-xs text-gray-400 mb-1">Reason</p>
                        <p class="text-sm text-gray-700">{{ $app->reason }}</p>

                        @if($app->remarks)
                            <p class="text-xs text-gray-400 mt-4 mb-1">HR Remarks</p>
                            <p class="text-sm text-gray-700">{{ $app->remarks }}</p>
                        @endif

                    </div>
                </div>
            </template>
        </div>
    @else
        <span class="text-gray-400">—</span>
    @endif
</td>
            </tr>

        @empty

            <tr>
                <td colspan="5">
                    <x-empty-state message="No leave applications on record." />
                </td>
            </tr>

        @endforelse

        </tbody>
    </table>

    <div class="px-5 py-3 border-t border-gray-100">
        {{ $applications->links() }}
    </div>
</div>
@endsection