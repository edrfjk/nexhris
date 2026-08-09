@extends('layouts.app')
@section('title', 'My Leave')

@section('content')
<x-page-header title="My Leave" subtitle="File leave requests and track your balance.">
    <x-slot:actions>
        <a href="{{ route('leave.ledger.pdf') }}" target="_blank"
           class="inline-flex items-center gap-1.5 bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-800 transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9.75L12 3.75m0 6l3-3m-3 3l-3-3M3.75 15.75v3a2.25 2.25 0 002.25 2.25h12a2.25 2.25 0 002.25-2.25v-3"/></svg>
            Export My Ledger (PDF)
        </a>
    </x-slot:actions>
</x-page-header>

@if (session('success'))
    <div class="mb-4 flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.5 12.75l6 6 9-13.5"/></svg>
        {{ session('success') }}
    </div>
@endif

<!-- Stat cards -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 ring-1 ring-blue-100 p-4">
        <div class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center mb-3">
            <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <p class="text-2xl font-bold text-gray-800 leading-none">{{ number_format($balance->vl_balance ?? 0, 3) }}</p>
        <p class="text-xs text-gray-500 mt-1.5">Vacation Leave Balance</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 ring-1 ring-green-100 p-4">
        <div class="w-9 h-9 rounded-lg bg-green-50 text-green-600 flex items-center justify-center mb-3">
            <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <p class="text-2xl font-bold text-gray-800 leading-none">{{ number_format($balance->sl_balance ?? 0, 3) }}</p>
        <p class="text-xs text-gray-500 mt-1.5">Sick Leave Balance</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 ring-1 ring-yellow-100 p-4">
        <div class="w-9 h-9 rounded-lg bg-yellow-50 text-yellow-600 flex items-center justify-center mb-3">
            <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9.75L12 3.75m0 6l3-3m-3 3l-3-3M3.75 15.75v3a2.25 2.25 0 002.25 2.25h12a2.25 2.25 0 002.25-2.25v-3"/></svg>
        </div>
        <p class="text-2xl font-bold text-gray-800 leading-none">{{ $pendingCount }}</p>
        <p class="text-xs text-gray-500 mt-1.5">Pending Requests</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 ring-1 ring-gray-100 p-4">
        <div class="w-9 h-9 rounded-lg bg-gray-50 text-gray-600 flex items-center justify-center mb-3">
            <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
        </div>
        <p class="text-2xl font-bold text-gray-800 leading-none">{{ $approvedThisYear }}</p>
        <p class="text-xs text-gray-500 mt-1.5">Days Used ({{ now()->year }})</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- File a request -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 sticky top-20">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-800 text-sm">File a Leave Application</h3>
            </div>
            <form method="POST" action="{{ route('leave.store') }}" class="p-5 space-y-4" x-data="{
                leaveType: '{{ old('leave_type', 'VL') }}',
                dateFrom: '{{ old('date_from') }}',
                dateTo: '{{ old('date_to') }}',
                vlBalance: {{ $balance->vl_balance ?? 0 }},
                slBalance: {{ $balance->sl_balance ?? 0 }},
                get available() { return this.leaveType === 'VL' ? this.vlBalance : this.slBalance },
                get estimatedDays() {
                    if (!this.dateFrom || !this.dateTo) return 0;
                    const from = new Date(this.dateFrom), to = new Date(this.dateTo);
                    if (to < from) return 0;
                    let count = 0;
                    for (let d = new Date(from); d <= to; d.setDate(d.getDate() + 1)) {
                        const day = d.getDay();
                        if (day !== 0 && day !== 6) count++;
                    }
                    return count;
                },
                get exceedsBalance() { return this.estimatedDays > this.available }
            }">
                @csrf

                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">Leave Type</label>
                    <select name="leave_type" x-model="leaveType" class="border border-gray-300 rounded-lg px-3 py-2 w-full text-sm focus:ring-2 focus:ring-maroon-700 focus:border-transparent" required>
                        <option value="VL">Vacation Leave</option>
                        <option value="SL">Sick Leave</option>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Available: <span x-text="available" class="font-medium"></span> day(s)</p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">Date From</label>
                    <input type="date" name="date_from" x-model="dateFrom" class="border border-gray-300 rounded-lg px-3 py-2 w-full text-sm focus:ring-2 focus:ring-maroon-700 focus:border-transparent" required>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">Date To</label>
                    <input type="date" name="date_to" x-model="dateTo" class="border border-gray-300 rounded-lg px-3 py-2 w-full text-sm focus:ring-2 focus:ring-maroon-700 focus:border-transparent" required>
                </div>

                <div x-show="estimatedDays > 0" x-cloak
                     :class="exceedsBalance ? 'bg-red-50 border-red-200 text-red-700' : 'bg-blue-50 border-blue-200 text-blue-700'"
                     class="border rounded-lg px-3 py-2 text-xs">
                    <span x-text="estimatedDays"></span> weekday(s) requested.
                    <template x-if="exceedsBalance">
                        <span class="block font-medium mt-1">⚠ This exceeds your available balance.</span>
                    </template>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">Reason (optional)</label>
                    <textarea name="reason" rows="3" class="border border-gray-300 rounded-lg px-3 py-2 w-full text-sm focus:ring-2 focus:ring-maroon-700 focus:border-transparent">{{ old('reason') }}</textarea>
                </div>

                @if ($errors->any())
                    <div class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
                        {{ $errors->first() }}
                    </div>
                @endif

                <button type="submit" :disabled="exceedsBalance"
                        :class="exceedsBalance ? 'bg-gray-300 cursor-not-allowed' : 'bg-maroon-800 hover:bg-maroon-900'"
                        class="w-full text-white py-2.5 rounded-lg text-sm font-medium transition">
                    Submit Application
                </button>
            </form>
        </div>
    </div>

    <!-- Applications list + recent ledger -->
    <div class="lg:col-span-2 space-y-6">

        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between flex-wrap gap-2">
                <h3 class="font-semibold text-gray-800 text-sm">My Applications</h3>
                <form method="GET" class="flex gap-2">
                    <select name="status" onchange="this.form.submit()" class="border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
                        <option value="">All Statuses</option>
                        <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                        <option value="approved" @selected(request('status') === 'approved')>Approved</option>
                        <option value="declined" @selected(request('status') === 'declined')>Declined</option>
                    </select>
                </form>
            </div>

            <div class="divide-y divide-gray-100">
                @forelse ($applications as $application)
                    <div class="px-5 py-4" x-data="{ open: false }">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-start gap-3">
                                <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0
                                    {{ match($application->status) { 'approved' => 'bg-green-50 text-green-600', 'declined' => 'bg-red-50 text-red-600', default => 'bg-yellow-50 text-yellow-600' } }}">
                                    @if ($application->status === 'approved')
                                        <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                    @elseif ($application->status === 'declined')
                                        <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    @else
                                        <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    @endif
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-800">
                                        {{ $application->leave_type === 'VL' ? 'Vacation Leave' : 'Sick Leave' }}
                                        <span class="text-gray-400 font-normal">· {{ $application->days }} day(s)</span>
                                    </p>
                                    <p class="text-xs text-gray-400">{{ $application->date_from->format('M d') }} – {{ $application->date_to->format('M d, Y') }}</p>
                                    @if ($application->reason)
                                        <button @click="open = !open" class="text-xs text-blue-600 hover:underline mt-1">
                                            <span x-show="!open">View reason</span><span x-show="open" x-cloak>Hide reason</span>
                                        </button>
                                        <p x-show="open" x-cloak class="text-xs text-gray-500 mt-1 bg-gray-50 rounded px-2 py-1.5">{{ $application->reason }}</p>
                                    @endif
                                    @if ($application->status === 'declined' && $application->remarks)
                                        <p class="text-xs text-red-600 mt-1 bg-red-50 rounded px-2 py-1.5">HR remarks: {{ $application->remarks }}</p>
                                    @endif
                                </div>
                            </div>
                            <x-badge :color="match($application->status) { 'approved' => 'green', 'declined' => 'red', default => 'yellow' }">
                                {{ ucfirst($application->status) }}
                            </x-badge>
                        </div>
                    </div>
                @empty
                    <x-empty-state message="No leave applications yet." />
                @endforelse
            </div>

            <div class="px-5 py-3">{{ $applications->links() }}</div>
        </div>

        <!-- Recent ledger snapshot -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-800 text-sm">Recent Ledger Activity</h3>
            </div>
            <table class="w-full text-xs">
                <thead class="bg-gray-50 text-left text-gray-500">
                    <tr>
                        <th class="px-5 py-2 font-medium">Period</th>
                        <th class="px-5 py-2 font-medium">Remarks</th>
                        <th class="px-5 py-2 font-medium text-right">VL Balance</th>
                        <th class="px-5 py-2 font-medium text-right">SL Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ledger as $row)
                        <tr class="border-t border-gray-100">
                            <td class="px-5 py-2.5 text-gray-600">{{ $row->period_from->format('M d, Y') }}</td>
                            <td class="px-5 py-2.5 text-gray-500">{{ $row->remarks ?: '—' }}</td>
                            <td class="px-5 py-2.5 text-right font-medium text-gray-700">{{ number_format($row->vl_balance, 3) }}</td>
                            <td class="px-5 py-2.5 text-right font-medium text-gray-700">{{ number_format($row->sl_balance, 3) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><x-empty-state message="No ledger entries yet." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection