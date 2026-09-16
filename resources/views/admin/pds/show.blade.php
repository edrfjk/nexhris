@extends('layouts.app')
@section('title', "PDS Review — {$employee->name}")

@section('content')
{{-- Laid out like the leave review screen: the document on the left, who it
     belongs to and the decision on the right. Reviewing a sheet you cannot
     see means leaving the page to read it and coming back to sign. --}}
<x-page-header title="PDS Review" :subtitle="$employee->name">
    <x-slot:actions>
        <a href="{{ route('admin.pds.index') }}" class="btn btn-md btn-secondary">
            <x-heroicon-o-arrow-left class="w-4 h-4" />
            Back to queue
        </a>
    </x-slot:actions>
</x-page-header>

@php
    $status = $submission->status ?? 'not_started';

    $tone = match ($status) {
        'approved' => 'green',
        'submitted' => 'amber',
        'returned' => 'red',
        'draft' => 'blue',
        default => 'gray',
    };
@endphp

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

    {{-- ============================================================
         LEFT — the sheet itself
         ============================================================ --}}
    <div class="space-y-6 lg:col-span-2">

        <x-card title="Submitted Personal Data Sheet">
            <x-slot:actions>
                <x-badge :color="$tone">{{ ucfirst(str_replace('_', ' ', $status)) }}</x-badge>
            </x-slot:actions>

            @if ($submission && $submission->file_path)
                <div class="mb-4 flex items-center justify-between gap-4 rounded-lg border border-sand-200 bg-sand-50 p-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-maroon-50 text-maroon-800">
                            <x-heroicon-o-document-text class="h-5 w-5" />
                        </div>
                        <div class="min-w-0">
                            <p class="truncate font-medium text-sand-800">
                                {{ $submission->file_original_name ?? 'PDS.xlsx' }}
                            </p>
                            <p class="text-xs text-sand-400">
                                Uploaded {{ $submission->uploaded_at?->format('M j, Y g:i A') ?: '—' }}
                                @if ($submission->template)
                                    · on {{ $submission->template->label }}
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <a href="{{ route('admin.pds.download', [$employee, \App\Support\DocumentName::personalDataSheet($employee, $submission?->applicable_year)]) }}" target="_blank"
                           class="btn btn-sm btn-primary">
                            <x-heroicon-o-arrow-top-right-on-square class="h-4 w-4" />
                            New tab
                        </a>
                        <a href="{{ route('admin.pds.workbook', $employee) }}" download
                           class="btn btn-sm btn-secondary">
                            Original
                        </a>
                    </div>
                </div>

                {{-- The workbook converted, so the whole sheet can be read
                     here rather than downloaded first. --}}
                <iframe src="{{ route('admin.pds.download', [$employee, \App\Support\DocumentName::personalDataSheet($employee, $submission?->applicable_year)]) }}"
                        class="h-[620px] w-full rounded-lg border border-sand-200 bg-sand-50"
                        title="Submitted Personal Data Sheet"></iframe>

                <p class="mt-3 text-[11px] text-sand-400">
                    If the sheet does not appear, your browser may not preview PDFs —
                    <a href="{{ route('admin.pds.download', [$employee, \App\Support\DocumentName::personalDataSheet($employee, $submission?->applicable_year)]) }}" target="_blank"
                       class="font-medium text-maroon-700 hover:text-maroon-900">open it in a new tab</a>.
                </p>
            @else
                <x-empty-state
                    title="Nothing submitted yet"
                    icon="document-text"
                    message="This employee has not uploaded a Personal Data Sheet for this year." />
            @endif
        </x-card>

        {{-- What has happened to this sheet before now. --}}
        @if ($submission && $submission->revisions->isNotEmpty())
            <x-card title="Revision history">
                <ol class="space-y-3">
                    @foreach ($submission->revisions as $revision)
                        <li class="flex gap-3">
                            <div class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-sand-100 text-sand-600">
                                <x-heroicon-o-arrow-path class="h-3.5 w-3.5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-sand-800">
                                    <span class="font-semibold">Version {{ $revision->version ?? $loop->iteration }}</span>
                                    <span class="text-sand-500">
                                        · {{ $revision->created_at?->format('M j, Y g:i A') }}
                                    </span>
                                </p>
                                @if ($revision->remarks ?? null)
                                    <p class="mt-1 rounded-md border border-sand-100 bg-sand-50 px-3 py-2 text-sm text-sand-600">
                                        {{ $revision->remarks }}
                                    </p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </x-card>
        @endif
    </div>

    {{-- ============================================================
         RIGHT — whose sheet, and the decision
         ============================================================ --}}
    <div class="space-y-6">

        <x-card title="Employee">
            <div class="flex items-center gap-4">
                <div class="h-16 w-16 shrink-0 overflow-hidden rounded-full bg-sand-100 ring-4 ring-white shadow-soft">
                    @if ($employee->profile_photo_path)
                        <img src="{{ asset('storage/' . $employee->profile_photo_path) }}"
                             alt="{{ $employee->name }}" class="h-full w-full object-cover">
                    @else
                        <div class="flex h-full w-full items-center justify-center text-xl font-bold text-sand-400">
                            {{ strtoupper(mb_substr($employee->name, 0, 1)) }}
                        </div>
                    @endif
                </div>
                <div class="min-w-0">
                    <p class="font-semibold text-sand-900">{{ $employee->name }}</p>
                    <p class="text-xs text-sand-400">{{ $employee->employee_number ?: '—' }}</p>
                </div>
            </div>

            <dl class="mt-5 space-y-2.5 border-t border-sand-100 pt-5 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-xs text-sand-400">Position</dt>
                    <dd class="text-right text-sand-700">{{ $employee->position ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-xs text-sand-400">College / Office</dt>
                    <dd class="text-right text-sand-700">{{ $employee->collegeName() ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-xs text-sand-400">Department</dt>
                    <dd class="text-right text-sand-700">{{ $employee->departmentName() ?: '—' }}</dd>
                </div>
            </dl>

            <div class="mt-5 flex flex-col gap-2 border-t border-sand-100 pt-5">
                <a href="{{ route('admin.employees.show', $employee) }}" class="btn btn-sm btn-secondary">
                    <x-heroicon-o-user-circle class="h-4 w-4" />
                    Employee record
                </a>
                <a href="{{ route('admin.leave.ledger', $employee) }}" class="btn btn-sm btn-secondary">
                    <x-heroicon-o-book-open class="h-4 w-4" />
                    Leave ledger card
                </a>
            </div>
        </x-card>

        {{-- ---------- Decision ---------- --}}
        @if ($submission && $submission->status === 'submitted')
            <div x-data="{ returning: false }" class="card overflow-hidden">
                <div class="border-b border-sand-100 px-5 py-4">
                    <h3 class="text-sm font-semibold text-sand-700">Your decision</h3>
                    <p class="mt-0.5 text-xs text-sand-400">As HR Administrator</p>
                </div>

                <div class="space-y-3 p-5">
                    <p class="text-xs leading-relaxed text-sand-500">
                        Approve the sheet, or return it with remarks so the employee
                        can correct and re-upload it.
                    </p>

                    <form method="POST" action="{{ route('admin.pds.approve', $employee) }}"
                          onsubmit="return confirm({{ Js::from('Approve this PDS?') }})">
                        @csrf
                        <button class="btn btn-md btn-success w-full">
                            <x-heroicon-o-check class="h-4 w-4" />
                            Approve PDS
                        </button>
                    </form>

                    <button type="button" @click="returning = !returning"
                            class="btn btn-md btn-danger-soft w-full">
                        Return for revision
                    </button>

                    <form method="POST" action="{{ route('admin.pds.return', $employee) }}"
                          x-show="returning" x-cloak class="space-y-3 border-t border-sand-100 pt-3">
                        @csrf
                        <label class="block">
                            <span class="label label-required">What needs correcting?</span>
                            <textarea name="return_remarks" rows="3" required maxlength="500"
                                      class="textarea mt-1"
                                      placeholder="e.g. Page C2 is missing the eligibility dates."></textarea>
                        </label>
                        <div class="flex gap-2">
                            <button class="btn btn-md btn-danger flex-1">Confirm return</button>
                            <button type="button" @click="returning = false"
                                    class="btn btn-md btn-secondary">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        @elseif ($submission && $submission->status === 'returned')
            <div class="alert alert-error">
                <x-heroicon-o-arrow-uturn-left />
                <div>
                    <p class="font-semibold">
                        Returned on {{ $submission->reviewed_at?->format('M j, Y') }}
                    </p>
                    <p class="mt-0.5 text-[13px] leading-relaxed">{{ $submission->return_remarks }}</p>
                </div>
            </div>
        @elseif ($submission && $submission->status === 'approved')
            <div class="alert alert-success">
                <x-heroicon-o-check-circle />
                <div>
                    <p class="font-semibold">Approved</p>
                    <p class="mt-0.5 text-[13px]">
                        {{ $submission->reviewed_at?->format('M j, Y') }}
                        @if ($submission->reviewer)
                            by {{ $submission->reviewer->name }}
                        @endif
                    </p>
                </div>
            </div>
        @else
            <div class="rounded-xl border border-sand-200 bg-sand-50 p-5 text-sm text-sand-600">
                <p class="mb-1 font-medium text-sand-700">Nothing to review</p>
                <p class="text-xs leading-relaxed">
                    A decision can be made once the employee submits their sheet.
                </p>
            </div>
        @endif
    </div>
</div>
@endsection
