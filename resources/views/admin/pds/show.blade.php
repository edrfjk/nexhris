@extends('layouts.app')
@section('title', "PDS Review — {$employee->name}")

@section('content')
<x-page-header title="PDS Review" />

<div x-data>

    <!-- Profile banner (unchanged) -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-6">
        <div class="h-20 bg-gradient-to-r from-maroon-900 via-maroon-800 to-maroon-900"></div>
        <div class="px-6 pb-5">
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 -mt-10">
                <div class="flex items-end gap-4">
                    <div class="w-20 h-20 rounded-full border-4 border-white bg-gray-100 overflow-hidden shadow-md flex-shrink-0">
                        @if ($employee->profile_photo_path)
                            <img src="{{ asset('storage/' . $employee->profile_photo_path) }}" class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-xl font-bold text-gray-400">
                                {{ strtoupper(substr($employee->name, 0, 1)) }}
                            </div>
                        @endif
                    </div>
                    <div class="pb-1">
                        <h2 class="text-lg font-bold text-gray-800">{{ $employee->name }}</h2>
                        <p class="text-sm text-gray-500">{{ $employee->employee_number }} · {{ $employee->department ?: 'No department set' }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-2 pb-1">
                    <a href="{{ route('admin.employees.show', $employee) }}"
                       class="text-sm text-gray-600 border border-gray-300 rounded-lg px-3 py-2 hover:bg-gray-50 transition">Employee Details</a>
                    <a href="{{ route('admin.leave.ledger', $employee) }}"
                       class="text-sm text-gray-600 border border-gray-300 rounded-lg px-3 py-2 hover:bg-gray-50 transition">Leave Ledger</a>

                    @if ($submission)
                        <x-badge :color="match($submission->status) {
                            'approved' => 'green', 'submitted' => 'yellow', 'returned' => 'red', 'draft' => 'blue', default => 'gray',
                        }" class="text-sm">
                            {{ ucfirst(str_replace('_', ' ', $submission->status)) }}
                        </x-badge>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Pending review actions -->
    @if ($submission && $submission->status === 'submitted')
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-5 mb-6">
            <p class="text-sm text-gray-700 mb-3">This PDS is awaiting your review. Approve it, or return it with remarks for the employee to revise.</p>
            <div class="flex gap-3">
                <form method="POST" action="{{ route('admin.pds.approve', $employee) }}" onsubmit="return confirm('Approve this PDS?')">
                    @csrf
                    <button class="inline-flex items-center gap-1.5 bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-800 transition">
                        Approve PDS
                    </button>
                </form>
                <button type="button" onclick="document.getElementById('return-form').classList.toggle('hidden')"
                        class="inline-flex items-center gap-1.5 bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-700 transition">
                    Return for Revision
                </button>
            </div>
            <form id="return-form" method="POST" action="{{ route('admin.pds.return', $employee) }}" class="hidden mt-4 pt-4 border-t border-yellow-200">
                @csrf
                <label class="block text-sm font-medium text-gray-700 mb-1">Remarks for the employee (required)</label>
                <textarea name="return_remarks" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-700 focus:border-transparent" required></textarea>
                <button class="mt-2 bg-red-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-red-700 transition">Confirm Return</button>
            </form>
        </div>
    @elseif ($submission && $submission->status === 'returned')
        <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg px-4 py-3 mb-6">
            <span class="font-medium">Returned on {{ $submission->reviewed_at->format('M d, Y') }}:</span> {{ $submission->return_remarks }}
        </div>
    @elseif ($submission && $submission->status === 'approved')
        <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3 mb-6">
            Approved on {{ $submission->reviewed_at->format('M d, Y') }}.
        </div>
    @endif

    <!-- Submission file -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-800 text-sm mb-4">Submitted PDS File</h3>

        @if ($submission && $submission->file_path)
            <div class="flex items-center gap-3 mb-4">
                <div class="w-11 h-11 rounded-lg bg-green-50 text-green-600 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                </div>
                <div>
                    <p class="font-medium text-gray-800">{{ $submission->file_original_name ?? 'PDS.xlsx' }}</p>
                    <p class="text-xs text-gray-400">Uploaded {{ $submission->uploaded_at?->format('M d, Y g:i A') }}</p>
                </div>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('admin.pds.download', $employee) }}" target="_blank"
                   class="inline-flex items-center gap-1.5 bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-800 transition">
                    View as PDF
                </a>
                <a href="{{ asset('storage/' . $submission->file_path) }}" download
                   class="inline-flex items-center gap-1.5 border border-gray-300 text-gray-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
                    Download Excel File
                </a>
            </div>
        @else
            <p class="text-sm text-gray-400">This employee has not uploaded a PDS yet.</p>
        @endif
    </div>
</div>
@endsection