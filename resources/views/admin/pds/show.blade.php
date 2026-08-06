@extends('layouts.app')
@section('title', "PDS Review — {$employee->name}")

@section('content')
<x-page-header title="PDS Review">
    <x-slot:actions>
        <a href="{{ route('admin.pds.download', $employee) }}" target="_blank"
           class="inline-flex items-center gap-1.5 bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-800 transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9.75L12 3.75m0 6l3-3m-3 3l-3-3M3.75 15.75v3a2.25 2.25 0 002.25 2.25h12a2.25 2.25 0 002.25-2.25v-3"/></svg>
            View as PDF
        </a>
        <a href="{{ route('admin.pds.index') }}"
           class="inline-flex items-center gap-1.5 text-sm text-gray-600 border border-gray-300 rounded-lg px-3 py-2 hover:bg-gray-50 transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            Back
        </a>
    </x-slot:actions>
</x-page-header>

<div x-data="{ tab: 'personal' }">

    <!-- Profile banner -->
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

    <!-- Action card for pending review -->
    @if ($submission && $submission->status === 'submitted')
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-5 mb-6">
            <p class="text-sm text-gray-700 mb-3">This PDS is awaiting your review. Approve it, or return it with remarks for the employee to revise.</p>
            <div class="flex gap-3">
                <form method="POST" action="{{ route('admin.pds.approve', $employee) }}" onsubmit="return confirm('Approve this PDS?')">
                    @csrf
                    <button class="inline-flex items-center gap-1.5 bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-800 transition">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.5 12.75l6 6 9-13.5"/></svg>
                        Approve PDS
                    </button>
                </form>
                <button type="button" onclick="document.getElementById('return-form').classList.toggle('hidden')"
                        class="inline-flex items-center gap-1.5 bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-700 transition">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
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
    @elseif (!$submission || $submission->status === 'not_started')
        <div class="bg-gray-100 text-gray-600 text-sm rounded-lg px-4 py-3 mb-6">
            This employee has not started their PDS for {{ now()->year }} yet.
        </div>
    @endif

    <!-- Tabs -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="flex border-b border-gray-100 px-2 overflow-x-auto">
            <button @click="tab = 'personal'" :class="tab === 'personal' ? 'border-maroon-800 text-maroon-800' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    class="px-4 py-3 text-sm font-medium border-b-2 transition whitespace-nowrap">Personal Info</button>
            <button @click="tab = 'education'" :class="tab === 'education' ? 'border-maroon-800 text-maroon-800' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    class="px-4 py-3 text-sm font-medium border-b-2 transition whitespace-nowrap">Education</button>
            <button @click="tab = 'work'" :class="tab === 'work' ? 'border-maroon-800 text-maroon-800' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    class="px-4 py-3 text-sm font-medium border-b-2 transition whitespace-nowrap">Work Experience</button>
        </div>

        @php $p = $employee->pdsPersonalInformation; @endphp

        <div x-show="tab === 'personal'" x-cloak class="p-6">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-4 text-sm">
                <div><p class="text-xs text-gray-400 mb-0.5">Name</p><p class="text-gray-800 font-medium">{{ $p->surname ?? '—' }}, {{ $p->first_name ?? '' }} {{ $p->middle_name ?? '' }}</p></div>
                <div><p class="text-xs text-gray-400 mb-0.5">Date of Birth</p><p class="text-gray-800 font-medium">{{ optional($p->date_of_birth ?? null)->format('M d, Y') ?? '—' }}</p></div>
                <div><p class="text-xs text-gray-400 mb-0.5">Sex / Civil Status</p><p class="text-gray-800 font-medium">{{ $p->sex ?? '—' }} / {{ $p->civil_status ?? '—' }}</p></div>
                <div><p class="text-xs text-gray-400 mb-0.5">Citizenship</p><p class="text-gray-800 font-medium">{{ $p->citizenship ?? '—' }}</p></div>
                <div><p class="text-xs text-gray-400 mb-0.5">Mobile</p><p class="text-gray-800 font-medium">{{ $p->mobile_no ?? '—' }}</p></div>
                <div><p class="text-xs text-gray-400 mb-0.5">Email</p><p class="text-gray-800 font-medium">{{ $p->email_address ?? '—' }}</p></div>
            </div>
        </div>

        <div x-show="tab === 'education'" x-cloak>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-500">
                    <tr><th class="px-6 py-2.5 font-medium">Level</th><th class="px-6 py-2.5 font-medium">School</th><th class="px-6 py-2.5 font-medium">Course</th><th class="px-6 py-2.5 font-medium">Year Graduated</th></tr>
                </thead>
                <tbody>
                    @forelse ($employee->pdsEducationalBackgrounds as $edu)
                        <tr class="border-t border-gray-100">
                            <td class="px-6 py-2.5 text-gray-700">{{ $edu->level }}</td>
                            <td class="px-6 py-2.5 text-gray-700">{{ $edu->school_name }}</td>
                            <td class="px-6 py-2.5 text-gray-700">{{ $edu->degree_course }}</td>
                            <td class="px-6 py-2.5 text-gray-700">{{ $edu->year_graduated }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><x-empty-state message="No education records." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div x-show="tab === 'work'" x-cloak>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-500">
                    <tr><th class="px-6 py-2.5 font-medium">Position</th><th class="px-6 py-2.5 font-medium">Agency/Company</th><th class="px-6 py-2.5 font-medium">From</th><th class="px-6 py-2.5 font-medium">To</th></tr>
                </thead>
                <tbody>
                    @forelse ($employee->pdsWorkExperiences as $work)
                        <tr class="border-t border-gray-100">
                            <td class="px-6 py-2.5 text-gray-700">{{ $work->position_title }}</td>
                            <td class="px-6 py-2.5 text-gray-700">{{ $work->department_agency_office_company }}</td>
                            <td class="px-6 py-2.5 text-gray-700">{{ $work->date_from->format('M Y') }}</td>
                            <td class="px-6 py-2.5 text-gray-700">{{ $work->date_to ? $work->date_to->format('M Y') : 'Present' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><x-empty-state message="No work experience records." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection