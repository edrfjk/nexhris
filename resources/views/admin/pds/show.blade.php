@extends('layouts.app')
@section('title', "PDS Review — {$employee->name}")

@section('content')
<x-page-header title="PDS Review" />

<div x-data>

    <!-- Profile header banner -->
    <div class="card overflow-hidden mb-6">
        <div class="h-24 bg-maroon-800"></div>
        <div class="px-6 pb-6">
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 -mt-12">
                <div class="flex items-end gap-4">
                    <div class="w-24 h-24 rounded-full border-4 border-white bg-sand-100 overflow-hidden shadow-soft flex-shrink-0">
                        @if ($employee->profile_photo_path)
                            <img src="{{ asset('storage/' . $employee->profile_photo_path) }}" class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-2xl font-bold text-sand-400">
                                {{ strtoupper(substr($employee->name, 0, 1)) }}
                            </div>
                        @endif
                    </div>
                    <div class="pb-1">
                        <h2 class="text-lg font-bold text-sand-800">{{ $employee->name }}</h2>
                        <p class="text-sm text-sand-500">{{ $employee->position ?: 'Employee' }} · {{ $employee->orgLine() }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-2 pb-1">
                    <a href="{{ route('admin.employees.show', $employee) }}"
                       class="btn btn-md btn-secondary">Employee Details</a>
                    <a href="{{ route('admin.leave.ledger', $employee) }}"
                       class="btn btn-md btn-secondary">Leave Ledger</a>

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
        <div class="bg-gold-50 border border-gold-200 rounded p-5 mb-6">
            <p class="text-sm text-sand-700 mb-3">This PDS is awaiting your review. Approve it, or return it with remarks for the employee to revise.</p>
            <div class="flex gap-3">
                <form method="POST" action="{{ route('admin.pds.approve', $employee) }}" onsubmit="return confirm('Approve this PDS?')">
                    @csrf
                    <button class="btn btn-md btn-success">
                        Approve PDS
                    </button>
                </form>
                <button type="button" onclick="document.getElementById('return-form').classList.toggle('hidden')"
                        class="btn btn-md btn-danger">
                    Return for Revision
                </button>
            </div>
            <form id="return-form" method="POST" action="{{ route('admin.pds.return', $employee) }}" class="hidden mt-4 pt-4 border-t border-gold-200">
                @csrf
                <label class="label">Remarks for the employee (required)</label>
                <textarea name="return_remarks" rows="3" class="textarea" required></textarea>
                <button class="btn btn-md btn-danger mt-2">Confirm Return</button>
            </form>
        </div>
    @elseif ($submission && $submission->status === 'returned')
        <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg px-4 py-3 mb-6">
            <span class="font-medium">Returned on {{ $submission->reviewed_at->format('M d, Y') }}:</span> {{ $submission->return_remarks }}
        </div>
    @elseif ($submission && $submission->status === 'approved')
        <div class="bg-forest-50 border border-forest-200 text-forest-800 text-sm rounded-lg px-4 py-3 mb-6">
            Approved on {{ $submission->reviewed_at->format('M d, Y') }}.
        </div>
    @endif

    <!-- Submission file -->
    <div class="card p-5">
        <h3 class="font-semibold text-sand-800 text-sm mb-4">Submitted PDS File</h3>

        @if ($submission && $submission->file_path)
            <div class="flex items-center gap-3 mb-4">
                <div class="w-11 h-11 rounded-lg bg-forest-50 text-forest-600 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                </div>
                <div>
                    <p class="font-medium text-sand-800">{{ $submission->file_original_name ?? 'PDS.xlsx' }}</p>
                    <p class="text-xs text-sand-400">Uploaded {{ $submission->uploaded_at?->format('M d, Y g:i A') }}</p>
                </div>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('admin.pds.download', $employee) }}" target="_blank"
                   class="btn btn-md btn-secondary">
                    View as PDF
                </a>
                <a href="{{ asset('storage/' . $submission->file_path) }}" download
                   class="btn btn-md btn-secondary">
                    Download Excel File
                </a>
            </div>
        @else
            <p class="text-sm text-sand-400">This employee has not uploaded a PDS yet.</p>
        @endif
    </div>
</div>
@endsection