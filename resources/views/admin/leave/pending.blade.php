@extends('layouts.app')
@section('title', 'Pending Leave Applications')

@section('content')
<div class="bg-white rounded shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-100 text-left">
            <tr>
                <th class="px-4 py-3">Employee</th>
                <th class="px-4 py-3">Type</th>
                <th class="px-4 py-3">Dates</th>
                <th class="px-4 py-3">Days</th>
                <th class="px-4 py-3">Reason</th>
                <th class="px-4 py-3 text-right">Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($applications as $application)
                <tr class="border-t align-top">
                    <td class="px-4 py-3">
                        <a href="{{ route('admin.leave.ledger', $application->user) }}" class="text-blue-600 hover:underline">
                            {{ $application->user->name }}
                        </a>
                    </td>
                    <td class="px-4 py-3">{{ $application->leave_type }}</td>
                    <td class="px-4 py-3">
                        {{ $application->date_from->format('M d, Y') }} – {{ $application->date_to->format('M d, Y') }}
                    </td>
                    <td class="px-4 py-3">{{ $application->days }}</td>
                    <td class="px-4 py-3">{{ $application->reason ?: '—' }}</td>
                    <td class="px-4 py-3 text-right space-y-2">
                        <form action="{{ route('admin.leave.approve', $application) }}" method="POST" class="inline">
                            @csrf
                            <button class="bg-green-600 text-white text-xs px-3 py-1.5 rounded hover:bg-green-700">
                                Approve
                            </button>
                        </form>

                        <button type="button"
                                onclick="document.getElementById('decline-{{ $application->id }}').classList.toggle('hidden')"
                                class="bg-red-600 text-white text-xs px-3 py-1.5 rounded hover:bg-red-700">
                            Decline
                        </button>

                        <form id="decline-{{ $application->id }}" action="{{ route('admin.leave.decline', $application) }}"
                              method="POST" class="hidden mt-2 text-left">
                            @csrf
                            <textarea name="remarks" rows="2" placeholder="Reason for declining"
                                      class="w-full border rounded px-2 py-1 text-xs" required></textarea>
                            <button class="mt-1 bg-gray-700 text-white text-xs px-3 py-1 rounded">Confirm Decline</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">No pending leave applications.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $applications->links() }}</div>
@endsection