@extends('layouts.app')
@section('title', 'Fill In My PDS')

@php
    use App\Support\Pds\PdsFormSchema;

    $locked = ! $submission->isEditable();
    $current = $sections[$section];
    $keys = array_keys($sections);
    $position = array_search($section, $keys, true);
    $isLast = $position === count($keys) - 1;
    $values = $data[$section] ?? [];
@endphp

@section('content')
<x-page-header title="Fill In My PDS"
               subtitle="CS Form 212 (Revised 2026) — answer on screen, then print it in the official format.">
    <x-slot:actions>
        <a href="{{ route('pds.form.preview', [\App\Support\DocumentName::personalDataSheet(auth()->user(), $submission->applicable_year)]) }}"
           target="_blank" class="btn btn-md btn-secondary">
            <x-heroicon-o-document-magnifying-glass class="w-4 h-4" />
            Preview PDF
        </a>
        <a href="{{ route('pds.form.workbook') }}" class="btn btn-md btn-secondary">
            <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
            Download as Excel
        </a>
    </x-slot:actions>
</x-page-header>

@if ($locked)
    <div class="alert alert-info mb-5">
        <x-heroicon-o-lock-closed />
        <span>
            @if ($submission->isApproved())
                Your {{ $submission->applicable_year }} PDS has been approved, so these answers can no longer be changed.
            @else
                Your PDS is with HR for review. Ask HR to return it if you need to change an answer.
            @endif
        </span>
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-[15rem_1fr] gap-6 items-start">

    <div class="space-y-4">
    {{-- Steps --}}
    <nav class="card p-2" aria-label="PDS sections">
        @foreach ($sections as $key => $meta)
            @php $done = isset($data['_saved'][$key]); @endphp
            <a href="{{ route('pds.form', $key) }}"
               @if ($key === $section) aria-current="step" @endif
               class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition
                      {{ $key === $section ? 'bg-maroon-50 text-maroon-800 font-medium' : 'text-sand-600 hover:bg-sand-50' }}">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold
                             {{ $done ? 'bg-forest-100 text-forest-700' : 'bg-sand-100 text-sand-500' }}">
                    @if ($done)
                        <x-heroicon-s-check class="w-3.5 h-3.5" />
                    @else
                        {{ $meta['number'] }}
                    @endif
                </span>
                <span class="min-w-0 truncate">{{ $meta['title'] }}</span>
            </a>
        @endforeach
    </nav>

    @unless ($locked)
        {{-- Finishing: the same filing an uploaded workbook gets. --}}
        <div class="card p-4 space-y-3">
            <p class="text-sm font-medium text-sand-800">Finished?</p>
            <p class="text-xs text-sand-500 leading-relaxed">
                Preview it first. Submitting prints your answers into the official CS Form 212 and sends it to HR.
            </p>
            <form method="POST" action="{{ route('pds.form.submit') }}"
                  onsubmit="return confirm('Submit your PDS to HR for review? Check the preview first — you cannot change it while HR is reviewing.')">
                @csrf
                <button type="submit" class="btn btn-md btn-primary w-full">Submit to HR</button>
            </form>
        </div>
    @endunless
    </div>

    {{-- The section --}}
    <form method="POST" action="{{ route('pds.form.update', $section) }}" class="card" novalidate>
        @csrf
        @method('PUT')

        <div class="card-header">
            <div class="min-w-0">
                <h3 class="card-title">{{ $current['number'] }}. {{ $current['title'] }}</h3>
                <p class="text-xs text-sand-400 mt-0.5">{{ $current['intro'] }} If something does not apply to you, type N/A or leave it blank — it prints as N/A.</p>
            </div>
        </div>

        <div class="p-5 space-y-8">
            @include('employee.pds.sections.' . $section, ['values' => $values, 'locked' => $locked])
        </div>

        @unless ($locked)
            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-sand-100 px-5 py-4">
                @if ($isLast)
                    <button type="submit" name="then" value="stay" class="btn btn-md btn-primary">Save</button>
                @else
                    <button type="submit" name="then" value="stay" class="btn btn-md btn-secondary">Save</button>
                    <button type="submit" name="then" value="next" class="btn btn-md btn-primary">Save and continue</button>
                @endif
            </div>
        @endunless
    </form>
</div>
@endsection
