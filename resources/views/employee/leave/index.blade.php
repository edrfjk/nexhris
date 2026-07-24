@extends('layouts.app')
@section('title', 'My Leave')

@section('content')
<div class="grid grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded shadow p-4">
        <p class="text-sm text-gray-500">Vacation Leave Balance</p>
        <p class="text-2xl font-bold text-maroon-800">{{ number_format($balance->vl_balance ?? 0, 3) }}</p>
    </div>
    <div class="bg-white rounded shadow p-4">
        <p class="text-sm text-gray-500">Sick Leave Balance</p>
        <p class="text-2xl font-bold text-maroon-800">{{ number_format($balance->sl_balance ?? 0, 3) }}</p>
    </div>
</div>

<form method="POST" action="{{ route('leave.store') }}" class="bg-white rounded shadow p-4 mb-6 grid grid-cols-4 gap-3 items-end text-sm">
    @csrf
    <div>
        <label class="block mb-1">Leave Type</label>
        <select name="leave_type" class="border rounded px-2 py-1.5 w-full" required>
            <option value="VL">Vacation Leave</option>
            <option value="SL">Sick Leave</option>
        </select>
    </div>
    <div>
        <label class="block mb-1">Date From</label>
        <input type="date" name="date_from" class="border rounded px-2 py-1.5 w-full" required>
    </div>
    <div>
        <label class="block mb-1">Date To</label>
        <input type="date" name="date_to" class="border rounded px-2 py-1.5 w-full" required>
    </div>
    <button class="bg-maroon-800 text-white rounded px-3 py-2">File Leave</button>
    <div class="col-span-4">
        <label class="block mb-1">Reason</label>
        <textarea name="reason" rows="2" class="border rounded px-2 py-1.5 w-full"></textarea>
    </div>
</form>

@if ($errors->any())
    <div class="mb-4 text-sm text-red-600 bg-red-50 border border-red-200 rounded px-3 py-2">
        {{ $errors->first() }}
    </div>
@endif

<div class="bg-white rounded shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-100 text-left">
            <tr>
                <th class="px-4 py-3">Type</th>
                <th class="px-4 py-3">Dates</th>
                <th class="px-4 py-3">Days</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Remarks</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($applications as $application)
                <tr class="border-t">
                    <td class="px-4 py-3">{{ $application->leave_type }}</td>
                    <td class="px-4 py-3">{{ $application->date_from->format('M d') }} – {{ $application->date_to->format('M d, Y') }}</td>
                    <td class="px-4 py-3">{{ $application->days }}</td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 rounded text-xs
                            {{ match($application->status) {
                                'approved' => 'bg-green-100 text-green-700',
                                'declined' => 'bg-red-100 text-red-700',
                                default => 'bg-yellow-100 text-yellow-700',
                            } }}">
                            {{ ucfirst($application->status) }}
                        </span>
                    </td>
                    <td class="px-4 py-3">{{ $application->remarks ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">No leave applications yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $applications->links() }}</div>
@endsection