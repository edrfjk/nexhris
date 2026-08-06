@extends('layouts.app')
@section('title', 'Pending Leave Applications')

@section('content')
<x-page-header title="Pending Leave Applications" subtitle="Review and act on leave requests awaiting HR decision.">
    <x-slot:actions>
        <a href="{{ route('admin.leave.index') }}"
           class="inline-flex items-center gap-1.5 text-sm text-gray-600 border border-gray-300 rounded-lg px-3 py-2 hover:bg-gray-50 transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
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

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
    <form method="GET" class="flex flex-wrap items-end gap-3">

        <div class="w-40">
            <label class="block text-xs font-medium text-gray-500 mb-1.5">
                Leave Type
            </label>

            <select
                name="type"
                onchange="this.form.submit()"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                       focus:ring-2 focus:ring-maroon-700 focus:border-transparent">

                <option value="">All Types</option>

                <option value="VL" @selected(request('type') === 'VL')>
                    Vacation Leave
                </option>

                <option value="SL" @selected(request('type') === 'SL')>
                    Sick Leave
                </option>

            </select>
        </div>

    </form>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-left text-gray-500">
            <tr>
                <th class="px-5 py-3 font-medium">Employee</th>
                <th class="px-5 py-3 font-medium">Type</th>
                <th class="px-5 py-3 font-medium">Dates</th>
                <th class="px-5 py-3 font-medium">Days</th>
                <th class="px-5 py-3 font-medium">Reason</th>
                <th class="px-5 py-3 font-medium text-right">Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($applications as $application)
                @php
                    // Matches the VL/SL codes used in LeaveLedgerController::approve()
                    $typeColor = match ($application->leave_type) {
                        'VL' => 'blue',
                        'SL' => 'green',
                        default => 'gray',
                    };
                @endphp
                <tr class="border-t border-gray-100 hover:bg-gray-50 transition align-top">
                    <td class="px-5 py-3">
                        <a href="{{ route('admin.leave.ledger', $application->user) }}" class="flex items-center gap-3 group">
                            <div class="w-9 h-9 rounded-full overflow-hidden bg-maroon-50 flex items-center justify-center flex-shrink-0">
                                @if ($application->user->profile_photo_path)
                                    <img src="{{ asset('storage/' . $application->user->profile_photo_path) }}" class="w-full h-full object-cover">
                                @else
                                    <span class="text-sm font-semibold text-maroon-800">{{ strtoupper(substr($application->user->name, 0, 1)) }}</span>
                                @endif
                            </div>
                            <div>
                                <p class="font-medium text-gray-800 group-hover:text-maroon-800 transition">{{ $application->user->name }}</p>
                                <p class="text-xs text-gray-400">{{ $application->user->employee_number }}</p>
                            </div>
                        </a>
                    </td>
                    <td class="px-5 py-3">
                        <x-badge :color="$typeColor">{{ $application->leave_type }}</x-badge>
                    </td>
                    <td class="px-5 py-3 text-gray-600">
                    {{ $application->date_from->format('M d, Y') }}
                    –
                    {{ $application->date_to->format('M d, Y') }}

                    <p class="text-xs text-gray-400 mt-0.5">
                        Filed {{ $application->created_at->diffForHumans() }}
                    </p>
                    </td>
                    <td class="px-5 py-3 text-gray-600">{{ $application->days }}</td>
                    <td class="px-5 py-3 text-gray-500 max-w-[220px] truncate" title="{{ $application->reason }}">{{ $application->reason ?: '—' }}</td>
                    <td class="px-5 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <form action="{{ route('admin.leave.approve', $application) }}" method="POST"
                                  onsubmit="return confirm('Approve this leave application?')">
                                @csrf
                                <button class="inline-flex items-center gap-1 bg-green-600 text-white text-xs px-3 py-1.5 rounded-lg hover:bg-green-700 transition">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                    Approve
                                </button>
                            </form>

                            <button type="button"
                                    onclick="document.getElementById('decline-{{ $application->id }}').classList.toggle('hidden')"
                                    class="inline-flex items-center gap-1 bg-red-600 text-white text-xs px-3 py-1.5 rounded-lg hover:bg-red-700 transition">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
                                Decline
                            </button>
                        </div>

                        <form id="decline-{{ $application->id }}" action="{{ route('admin.leave.decline', $application) }}"
                              method="POST" class="hidden mt-3 text-left bg-gray-50 border border-gray-200 rounded-lg p-3">
                            @csrf
                            <label class="block text-xs font-medium text-gray-500 mb-1">Reason for declining (required)</label>
                            <textarea name="remarks" rows="2" placeholder="Explain why this application is being declined"
                                      class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs focus:ring-2 focus:ring-maroon-700 focus:border-transparent" required></textarea>
                            <button class="mt-2 bg-gray-700 text-white text-xs px-3 py-1.5 rounded-lg hover:bg-gray-800 transition">Confirm Decline</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty-state message="No pending leave applications." /></td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $applications->links() }}</div>
@endsection