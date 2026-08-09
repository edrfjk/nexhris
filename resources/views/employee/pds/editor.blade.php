@extends('layouts.app')
@section('title', 'Personal Data Sheet')

@section('content')
<x-page-header title="Personal Data Sheet" subtitle="Download the official template, fill it out, then upload it back here." />

@if (session('success'))
    <div class="mb-4 flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
        {{ session('success') }}
    </div>
@endif
@if (session('error'))
    <div class="mb-4 flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ session('error') }}
    </div>
@endif
@if ($errors->any())
    <div class="mb-4 flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ $errors->first() }}
    </div>
@endif

@if (!$template)
    <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 text-sm rounded-lg px-4 py-3">
        HR has not activated a PDS template yet. Please check back later or contact HR.
    </div>
@else

    @if ($submission->status)
        <div class="mb-6">
            <x-badge :color="match($submission->status) {
                'approved' => 'green', 'submitted' => 'yellow', 'returned' => 'red', 'draft' => 'blue', default => 'gray',
            }">
                {{ ucfirst(str_replace('_', ' ', $submission->status)) }}
            </x-badge>
        </div>
    @endif

    @if ($submission->status === 'returned')
        <div class="mb-6 bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg px-4 py-3">
            HR returned your PDS for revision: {{ $submission->return_remarks }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <x-card title="Step 1 — Download the Official Template">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-11 h-11 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                </div>
                <div>
                    <p class="font-medium text-gray-800">{{ $template->label }}</p>
                    <p class="text-xs text-gray-400">{{ $template->original_filename }}</p>
                </div>
            </div>
            <p class="text-sm text-gray-500 mb-4">
                Download this file and open it in Excel, LibreOffice Calc, or Google Sheets. Fill in your details directly in the cells — do not rename sheets or change the layout.
            </p>
            <a href="{{ asset('storage/' . $template->file_path) }}" download
               class="inline-flex items-center gap-1.5 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition">
                Download Template
            </a>
        </x-card>

        <x-card title="Step 2 — Upload Your Completed PDS">
            <form method="POST" action="{{ route('pds.upload') }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <input type="file" name="file" accept=".xlsx" required class="text-sm w-full">
                <p class="text-xs text-gray-400">Only .xlsx files based on the official template are accepted. Max 10MB.</p>
                <button class="bg-maroon-800 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-maroon-900 transition">
                    Upload Completed PDS
                </button>
            </form>

            @if ($submission->file_path)
                <div class="mt-5 pt-4 border-t border-gray-100">
                    <p class="text-xs text-gray-400 mb-2">Your uploaded file</p>
                    <div class="flex items-center gap-2 flex-wrap">
                        <a href="{{ route('pds.export') }}" target="_blank"
                           class="inline-flex items-center gap-1.5 bg-gray-700 text-white px-3 py-2 rounded-lg text-xs font-medium hover:bg-gray-800 transition">
                            View as PDF
                        </a>
                        <a href="{{ asset('storage/' . $submission->file_path) }}" download
                           class="inline-flex items-center gap-1.5 border border-gray-300 text-gray-600 px-3 py-2 rounded-lg text-xs font-medium hover:bg-gray-50 transition">
                            Download My Excel File
                        </a>
                    </div>
                </div>
            @endif
        </x-card>
    </div>

    @if ($submission->file_path && $submission->status !== 'approved')
        <div class="mt-6">
            <form method="POST" action="{{ route('pds.submit') }}"
                  onsubmit="return confirm('Submit your PDS to HR for review? Make sure you have filled it out completely and checked the PDF preview.')">
                @csrf
                <button class="bg-green-700 text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-green-800 transition">
                    Submit PDS to HR for Review
                </button>
            </form>
        </div>
    @elseif ($submission->status === 'approved')
        <div class="mt-6 bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3">
            Your PDS for {{ now()->year }} has been reviewed and approved by HR.
        </div>
    @endif

@endif
@endsection

{{-- i tried to upload my own pds but when i click the view as pdf, it cant turn it into pdf also dont iclude the success action part wherein it has a pop-up because i already put that in the app layout so no need to put in the pds blade.  also since i can see the design for now, it has a lot of missing parts because after i uploaded the pds file, i cant see or theres nothing i can see that i has already uploaded something so itll be bad ui and ux. the employee should see their uploaded file there listed together with the remarks if it was passed to the admin or etc just improve it more. and i have another concern, since we remove the old steps wizards in pds part, then what will be update in the admin side about the pds part because if you can remember when the admin view the employee it shows there the Personal InfoEducationWork Experience so i think the pds view part for admin will be change but dont remove the header type that displays the picture, name, and quicklinks for employee details and admin ledger --}}