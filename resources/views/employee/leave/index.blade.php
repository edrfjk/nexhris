@extends('layouts.app')
@section('title', 'My Leave')

@section('content')
<x-page-header title="My Leave" subtitle="File leave requests and track your balance." />

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
    <x-stat-card label="Vacation Leave Balance" :value="number_format($balance->vl_balance ?? 0, 3)" color="green" />
    <x-stat-card label="Sick Leave Balance" :value="number_format($balance->sl_balance ?? 0, 3)" color="blue" />
</div>

<x-card title="File a Leave Application" class="mb-6">
    <form method="POST" action="{{ route('leave.store') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end text-sm">
        @csrf
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Leave Type</label>
            <select name="leave_type" class="border border-gray-300 rounded-lg px-2.5 py-2 w-full focus:ring-2 focus:ring-maroon-700 focus:border-transparent" required>
                <option value="VL">Vacation Leave</option>
                <option value="SL">Sick Leave</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Date From</label>
            <input type="date" name="date_from" class="border border-gray-300 rounded-lg px-2.5 py-2 w-full focus:ring-2 focus:ring-maroon-700 focus:border-transparent" required>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Date To</label>
            <input type="date" name="date_to" class="border border-gray-300 rounded-lg px-2.5 py-2 w-full focus:ring-2 focus:ring-maroon-700 focus:border-transparent" required>
        </div>
        <button class="bg-maroon-800 text-white rounded-lg px-3 py-2 hover:bg-maroon-900 transition">File Leave</button>
        <div class="sm:col-span-4">
            <label class="block text-xs font-medium text-gray-500 mb-1">Reason</label>
            <textarea name="reason" rows="2" class="border border-gray-300 rounded-lg px-2.5 py-2 w-full focus:ring-2 focus:ring-maroon-700 focus:border-transparent"></textarea>
        </div>
    </form>

    @if ($errors->any())
        <div class="mt-3 text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
            {{ $errors->first() }}
        </div>
    @endif
</x-card>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-left text-gray-500">
            <tr>
                <th class="px-4 py-3 font-medium">Type</th>
                <th class="px-4 py-3 font-medium">Dates</th>
                <th class="px-4 py-3 font-medium">Days</th>
                <th class="px-4 py-3 font-medium">Status</th>
                <th class="px-4 py-3 font-medium">Remarks</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($applications as $application)
                <tr class="border-t border-gray-100 hover:bg-gray-50 transition">
                    <td class="px-4 py-3"><x-badge color="blue">{{ $application->leave_type }}</x-badge></td>
                    <td class="px-4 py-3 text-gray-600">{{ $application->date_from->format('M d') }} – {{ $application->date_to->format('M d, Y') }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $application->days }}</td>
                    <td class="px-4 py-3">
                        <x-badge :color="match($application->status) { 'approved' => 'green', 'declined' => 'red', default => 'yellow' }">
                            {{ ucfirst($application->status) }}
                        </x-badge>
                    </td>
                    <td class="px-4 py-3 text-gray-500">{{ $application->remarks ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty-state message="No leave applications yet." /></td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $applications->links() }}</div>
@endsection