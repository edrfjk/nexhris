@extends('layouts.app')
@section('title', 'Personal Data Sheet')

{{--
    My PDS, for every role — employees, Deans, the Campus Director and HR all
    file one. There are two ways to fill it in, and both end in the same
    official CS Form 212 reaching HR:

      1. on screen, section by section, printed into the form by the system;
      2. the classic way: download the blank, fill it in Excel, upload it.
--}}

@php
    use App\Support\DocumentName;
    use App\Support\Pds\PdsFormSchema;

    $me = auth()->user();
    $editable = $submission->isEditable();
    $sections = PdsFormSchema::sections();
    $savedSections = count(array_intersect_key($submission->form_data['_saved'] ?? [], $sections));
    $startedOnScreen = $submission->form_data !== null && $submission->form_data !== [];

    // A PDS filed from the on-screen form is stored under the system's own
    // name for it; an uploaded one keeps the name of the file the person chose.
    $filedOnScreen = $submission->file_path
        && $submission->file_original_name === DocumentName::personalDataSheet($me, $submission->applicable_year, 'xlsx');
@endphp

@section('content')
<x-page-header title="Personal Data Sheet"
               subtitle="Fill in your PDS on screen, or in Excel and upload it — whichever suits you." />

@if ($errors->any())
    <div class="alert alert-error mb-5">
        <x-heroicon-o-exclamation-circle />
        <span>{{ $errors->first() }}</span>
    </div>
@endif

@if (! $template)
    <div class="alert alert-warning">
        <x-heroicon-o-exclamation-triangle />
        <span>HR has not published a PDS template yet, so a PDS cannot be filled in either way. Please check back later or contact the HR Office.</span>
    </div>
@else

    {{-- Where this year's PDS stands --}}
    @if ($submission->isReturned())
        <div class="alert alert-error mb-5">
            <x-heroicon-o-arrow-uturn-left />
            <span>
                <span class="font-medium">HR returned your PDS for correction:</span> {{ $submission->return_remarks }}
                <span class="block mt-1">Correct it using either option below, then submit it again.</span>
            </span>
        </div>
    @elseif ($submission->isApproved())
        <div class="alert alert-success mb-5">
            <x-heroicon-o-check-circle />
            <span>Your {{ $submission->applicable_year }} PDS has been reviewed and approved by HR.</span>
        </div>
    @elseif ($submission->isSubmitted())
        <div class="alert alert-info mb-5">
            <x-heroicon-o-clock />
            <span>Your PDS is with HR for review. It cannot be changed until HR approves it or returns it to you.</span>
        </div>
    @endif

    @if ($submission->file_path)
        <div class="card p-5 mb-6">
            <div class="flex items-start justify-between flex-wrap gap-4">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="w-11 h-11 rounded-lg bg-forest-50 text-forest-700 flex items-center justify-center flex-shrink-0">
                        <x-heroicon-o-document-check class="w-5 h-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="font-medium text-sand-800">Your {{ $submission->applicable_year }} PDS</p>
                        <p class="text-xs text-sand-500">
                            {{ $filedOnScreen ? 'Filled in on screen' : 'Uploaded from Excel' }}
                            · {{ optional($submission->uploaded_at)->format('M d, Y g:i A') ?? '—' }}
                            @unless ($filedOnScreen)
                                · <span class="break-all">{{ $submission->file_original_name }}</span>
                            @endunless
                        </p>
                    </div>
                </div>
                <x-badge :color="$submission->statusTone()">{{ $submission->statusLabel() }}</x-badge>
            </div>

            <div class="flex items-center gap-2 flex-wrap mt-4 pt-4 border-t border-sand-100">
                <a href="{{ route('pds.export', [DocumentName::personalDataSheet($me, $submission->applicable_year)]) }}"
                   target="_blank" class="btn btn-sm btn-primary">
                    View as PDF
                </a>
                <a href="{{ route('pds.workbook') }}" download class="btn btn-sm btn-secondary">
                    Download as Excel
                </a>
            </div>

            {{-- An upload is filed as a draft first, so it can be checked in
                 the PDF before it goes. The on-screen form submits directly. --}}
            @if ($submission->status === 'draft')
                <form method="POST" action="{{ route('pds.submit') }}" class="mt-4 pt-4 border-t border-sand-100 flex flex-wrap items-center justify-between gap-3"
                      onsubmit="return confirm('Submit this PDS to HR for review? Check the PDF first — you cannot change it while HR is reviewing.')">
                    @csrf
                    <p class="text-sm text-sand-600">This PDS has not been sent to HR yet.</p>
                    <button type="submit" class="btn btn-md btn-primary">Submit PDS to HR</button>
                </form>
            @endif
        </div>
    @endif

    @if ($editable)
        <div class="mb-3">
            <h2 class="text-base font-semibold text-sand-900">
                {{ $submission->file_path ? 'Need to make changes? Choose how' : 'Choose how to fill in your PDS' }}
            </h2>
            <p class="text-sm text-sand-500">
                Both options give HR the same official CS Form 212. Use whichever suits you —
                the most recent one you file is the one HR reviews.
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

            {{-- Option 1 — on screen --}}
            <x-card class="border-maroon-200 ring-1 ring-maroon-100">
                <div class="flex items-start gap-3 mb-4">
                    <div class="w-11 h-11 rounded-lg bg-maroon-50 text-maroon-700 flex items-center justify-center flex-shrink-0">
                        <x-heroicon-o-pencil-square class="w-5 h-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-maroon-700">Option 1 · Recommended</p>
                        <h3 class="font-semibold text-sand-900">Fill in on screen</h3>
                    </div>
                </div>

                <ul class="space-y-2 text-sm text-sand-600 mb-4">
                    <li class="flex gap-2"><x-heroicon-o-check class="w-4 h-4 mt-0.5 shrink-0 text-forest-700" />No download or Excel needed — works on any device with a browser.</li>
                    <li class="flex gap-2"><x-heroicon-o-check class="w-4 h-4 mt-0.5 shrink-0 text-forest-700" />Answer section by section; your answers are saved as you go.</li>
                    <li class="flex gap-2"><x-heroicon-o-check class="w-4 h-4 mt-0.5 shrink-0 text-forest-700" />The system prints it in the official format, tick boxes included, and you can preview it any time.</li>
                    <li class="flex gap-2"><x-heroicon-o-check class="w-4 h-4 mt-0.5 shrink-0 text-forest-700" />Next year, your answers are already there to update.</li>
                </ul>

                @if ($startedOnScreen)
                    <div class="mb-4">
                        <div class="flex items-center justify-between text-xs text-sand-500 mb-1.5">
                            <span>{{ $savedSections }} of {{ count($sections) }} sections saved</span>
                            @if ($submission->form_updated_at)
                                <span>Last saved {{ $submission->form_updated_at->format('M d, Y g:i A') }}</span>
                            @endif
                        </div>
                        <div class="h-1.5 rounded-full bg-sand-100 overflow-hidden">
                            <div class="h-full rounded-full bg-forest-600"
                                 style="width: {{ round($savedSections / max(1, count($sections)) * 100) }}%"></div>
                        </div>
                    </div>
                @endif

                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('pds.form') }}" class="btn btn-md btn-primary">
                        {{ $startedOnScreen ? 'Continue filling in' : 'Start filling in' }}
                    </a>
                    @if ($startedOnScreen)
                        <a href="{{ route('pds.form.preview', [DocumentName::personalDataSheet($me, $submission->applicable_year)]) }}"
                           target="_blank" class="btn btn-md btn-secondary">
                            <x-heroicon-o-document-magnifying-glass class="w-4 h-4" />
                            Preview PDF
                        </a>
                        <a href="{{ route('pds.form.workbook') }}" class="btn btn-md btn-secondary">
                            <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                            Download as Excel
                        </a>
                    @endif
                </div>
            </x-card>

            {{-- Option 2 — Excel --}}
            <x-card>
                <div class="flex items-start gap-3 mb-4">
                    <div class="w-11 h-11 rounded-lg bg-sky-50 text-sky-700 flex items-center justify-center flex-shrink-0">
                        <x-heroicon-o-table-cells class="w-5 h-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-sand-500">Option 2</p>
                        <h3 class="font-semibold text-sand-900">Download, fill in Excel, upload</h3>
                    </div>
                </div>

                <ol class="space-y-4 text-sm text-sand-600">
                    <li>
                        <p class="font-medium text-sand-800">1. Download the official blank</p>
                        <p class="text-xs text-sand-500 mb-2 break-all">{{ $template->label }} · {{ $template->original_filename }}</p>
                        <a href="{{ route('pds.template.download') }}" download class="btn btn-sm btn-secondary">
                            <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                            Download template
                        </a>
                    </li>

                    <li>
                        <p class="font-medium text-sand-800">2. Fill it in on a computer</p>
                        {{-- This used to recommend Google Sheets. Google Sheets,
                             Excel in a browser, WPS and phone spreadsheet apps
                             drop the form's tick boxes when they save, and the
                             uploaded sheet then prints with every checkbox
                             missing. Desktop Excel and LibreOffice Calc keep
                             them; LibreOffice was checked by saving the template
                             through it. --}}
                        <p class="mb-2">
                            Use <strong class="font-medium text-sand-700">Microsoft Excel</strong> or
                            <strong class="font-medium text-sand-700">LibreOffice Calc</strong>. Type in the cells;
                            do not rename sheets or change the layout.
                        </p>
                        <div class="alert alert-warning text-[13px]">
                            <x-heroicon-o-exclamation-triangle />
                            <span>
                                Not Google Sheets, Excel in a web browser, WPS or a phone app — they remove the
                                form's tick boxes when they save.
                            </span>
                        </div>
                    </li>

                    <li>
                        <p class="font-medium text-sand-800">3. Upload the finished file</p>
                        <form method="POST" action="{{ route('pds.upload') }}" enctype="multipart/form-data" class="space-y-2 mt-1">
                            @csrf
                            <input type="file" name="file" accept=".xlsx" required class="file-input">
                            <p class="text-xs text-sand-400">
                                The .xlsx based on the official template, up to 10 MB.
                                @if ($submission->file_path)
                                    Uploading replaces the PDS above; the earlier version is kept in your history.
                                @endif
                            </p>
                            <button type="submit" class="btn btn-md btn-primary">Upload PDS</button>
                        </form>
                        <p class="text-xs text-sand-500 mt-2">After uploading, check the PDF, then press <em>Submit PDS to HR</em>.</p>
                    </li>
                </ol>
            </x-card>
        </div>
    @endif
@endif
@endsection
