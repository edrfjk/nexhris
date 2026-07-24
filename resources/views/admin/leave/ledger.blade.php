@extends('layouts.app')
@section('title', "Leave Ledger — {$employee->name}")

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

<form method="POST" action="{{ route('admin.leave.earned.store', $employee) }}"
      class="bg-white rounded shadow p-4 mb-6 grid grid-cols-5 gap-3 items-end text-sm">
    @csrf
    <div><label class="block mb-1">Period From</label><input type="date" name="period_from" class="border rounded px-2 py-1 w-full" required></div>
    <div><label class="block mb-1">Period To</label><input type="date" name="period_to" class="border rounded px-2 py-1 w-full" required></div>
    <div><label class="block mb-1">VL Earned</label><input type="number" step="0.001" name="vl_earned" class="border rounded px-2 py-1 w-full"></div>
    <div><label class="block mb-1">SL Earned</label><input type="number" step="0.001" name="sl_earned" class="border rounded px-2 py-1 w-full"></div>
    <button class="bg-maroon-800 text-white rounded px-3 py-2">Post Earned Credits</button>
</form>

<div class="bg-white rounded shadow overflow-x-auto">
    <table class="w-full text-xs text-center">
        <thead class="bg-gray-100">
            <tr>
                <th rowspan="2" class="border px-2 py-2 align-middle">Period</th>
                <th rowspan="2" class="border px-2 py-2 align-middle">Remarks</th>
                <th colspan="3" class="border px-2 py-2">Vacation Leave</th>
                <th colspan="3" class="border px-2 py-2">Sick Leave</th>
            </tr>
            <tr>
                <th class="border px-2 py-1">Earned</th>
                <th class="border px-2 py-1">Used</th>
                <th class="border px-2 py-1">Balance</th>
                <th class="border px-2 py-1">Earned</th>
                <th class="border px-2 py-1">Used</th>
                <th class="border px-2 py-1">Balance</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($ledger as $row)
                <tr class="border-t">
                    <td class="border px-2 py-1">{{ $row->period_from->format('M d, Y') }} – {{ $row->period_to->format('M d, Y') }}</td>
                    <td class="border px-2 py-1 text-left">{{ $row->remarks }}</td>
                    <td class="border px-2 py-1">{{ $row->vl_earned ?: '' }}</td>
                    <td class="border px-2 py-1">{{ $row->vl_used ?: '' }}</td>
                    <td class="border px-2 py-1 font-semibold">{{ number_format($row->vl_balance, 3) }}</td>
                    <td class="border px-2 py-1">{{ $row->sl_earned ?: '' }}</td>
                    <td class="border px-2 py-1">{{ $row->sl_used ?: '' }}</td>
                    <td class="border px-2 py-1 font-semibold">{{ number_format($row->sl_balance, 3) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="py-6 text-gray-400">No ledger entries yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection