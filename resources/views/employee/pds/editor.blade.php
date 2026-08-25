@extends('layouts.app')
@section('title', 'Personal Data Sheet')

@section('content')
<x-page-header title="Personal Data Sheet" subtitle="Download the official template, fill it out, then upload it back here." />

@if ($errors->any())
    <div class="mb-4 flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ $errors->first() }}
    </div>
@endif

@if (!$template)
    <div class="bg-gold-50 border border-gold-200 text-gold-800 text-sm rounded-lg px-4 py-3">
        HR has not activated a PDS template yet. Please check back later or contact HR.
    </div>
@else

    @if ($submission->status === 'returned')
        <div class="mb-6 bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg px-4 py-3">
            <span class="font-medium">HR returned your PDS for revision:</span> {{ $submission->return_remarks }}
        </div>
    @elseif ($submission->status === 'approved')
        <div class="mb-6 bg-forest-50 border border-forest-200 text-forest-800 text-sm rounded-lg px-4 py-3">
            Your PDS for {{ now()->year }} has been reviewed and approved by HR.
        </div>
    @endif

    <!-- My Submission summary -->
    @if ($submission->file_path)
        <div class="card p-5 mb-6">
            <div class="flex items-start justify-between flex-wrap gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-lg bg-forest-50 text-forest-600 flex items-center justify-center flex-shrink-0">
                        <x-heroicon-o-check-circle class="w-5 h-5" />
                    </div>
                    <div>
                        <p class="font-medium text-sand-800">{{ $submission->file_original_name ?? 'My PDS.xlsx' }}</p>
                        <p class="text-xs text-sand-400">
                            Uploaded {{ optional($submission->uploaded_at)->format('M d, Y g:i A') ?? '—' }}
                        </p>
                    </div>
                </div>
                <x-badge :color="match($submission->status) {
                    'approved' => 'green', 'submitted' => 'yellow', 'returned' => 'red', 'draft' => 'blue', default => 'gray',
                }">
                    {{ ucfirst(str_replace('_', ' ', $submission->status)) }}
                </x-badge>
            </div>

            <div class="flex items-center gap-2 flex-wrap mt-4 pt-4 border-t border-sand-100">
                <a href="{{ route('pds.export') }}" target="_blank"
                   class="inline-flex items-center gap-1.5 bg-sand-700 text-white px-3 py-2 rounded-lg text-xs font-medium hover:bg-sand-800 transition">
                    View as PDF
                </a>
                <a href="{{ asset('storage/' . $submission->file_path) }}" download
                   class="btn btn-sm btn-secondary">
                    Download My Excel File
                </a>
                <button type="button" onclick="document.getElementById('replace-panel').classList.toggle('hidden')"
                        class="btn btn-sm btn-secondary">
                    Upload a Different Version
                </button>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <x-card title="Step 1 — Download the Official Template">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-11 h-11 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                </div>
                <div>
                    <p class="font-medium text-sand-800">{{ $template->label }}</p>
                    <p class="text-xs text-sand-400">{{ $template->original_filename }}</p>
                </div>
            </div>
            <p class="text-sm text-sand-500 mb-4">
                Download this file and open it in Excel, LibreOffice Calc, or Google Sheets. Fill in your details directly in the cells — do not rename sheets or change the layout.
            </p>
            <a href="{{ asset('storage/' . $template->file_path) }}" download
               class="inline-flex items-center gap-1.5 bg-sky-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-sky-700 transition">
                Download Template
            </a>
        </x-card>

        <x-card title="Step 2 — Upload Your Completed PDS" id="upload-card">
            @if ($submission->file_path)
                <div id="replace-panel" class="hidden">
                    <p class="text-xs text-sand-400 mb-3">Uploading a new file will replace your current submission.</p>
                    <form method="POST" action="{{ route('pds.upload') }}" enctype="multipart/form-data" class="space-y-3">
                        @csrf
                        <input type="file" name="file" accept=".xlsx" required class="file-input">
                        <button class="btn btn-md btn-primary">
                            Replace File
                        </button>
                    </form>
                </div>
                <p class="text-sm text-sand-400">Your PDS is already uploaded — see the summary above. Click "Upload a Different Version" if you need to make changes.</p>
            @else
                <form method="POST" action="{{ route('pds.upload') }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <input type="file" name="file" accept=".xlsx" required class="file-input">
                    <p class="text-xs text-sand-400">Only .xlsx files based on the official template are accepted. Max 10MB.</p>
                    <button class="btn btn-md btn-primary">
                        Upload Completed PDS
                    </button>
                </form>
            @endif
        </x-card>
    </div>

    @if ($submission->file_path && $submission->status !== 'approved' && $submission->status !== 'submitted')
        <div class="mt-6">
            <form method="POST" action="{{ route('pds.submit') }}"
                  onsubmit="return confirm('Submit your PDS to HR for review? Make sure you have filled it out completely and checked the PDF preview.')">
                @csrf
                <button class="bg-forest-700 text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-forest-800 transition">
                    Submit PDS to HR for Review
                </button>
            </form>
        </div>
    @elseif ($submission->status === 'submitted')
        <div class="mt-6 bg-gold-50 border border-gold-200 text-gold-800 text-sm rounded-lg px-4 py-3">
            Your PDS is awaiting HR review.
        </div>
    @endif

@endif
@endsection