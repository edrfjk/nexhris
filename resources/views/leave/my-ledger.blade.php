@extends('layouts.app')
@section('title', 'My Leave Ledger')

@section('content')

<x-page-header
    title="My Leave Ledger"
    subtitle="Your official ledger card, as maintained by HR. Read-only — ask HR if a figure looks wrong.">
    <x-slot:actions>
        <a href="{{ route('leave.ledger.pdf') }}" target="_blank" class="btn btn-sm btn-secondary">
            <x-heroicon-o-arrow-top-right-on-square />Open in new tab
        </a>
        <a href="{{ route('leave.index') }}" class="btn btn-sm btn-primary">
            <x-heroicon-o-calendar-days />My leave
        </a>
    </x-slot:actions>
</x-page-header>

{{-- ------------------------------------------------------------------
     Current credits — the figures people actually come here for
     ------------------------------------------------------------------ --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
    @foreach ([
        ['Vacation leave', $balance->vl_balance ?? 0, 'sun'],
        ['Sick leave', $balance->sl_balance ?? 0, 'heart'],
        ['Service credits', $balance->service_balance ?? 0, 'clock'],
    ] as [$label, $value, $icon])
        @php $value = (float) $value; @endphp
        <x-card>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="section-label">{{ $label }}</p>
                    <p class="text-2xl font-semibold text-sand-900 tabular mt-1">
                        {{ number_format($value, 2) }}
                    </p>
                    <p class="text-[11px] text-sand-400 mt-0.5">
                        day{{ $value == 1 ? '' : 's' }} available
                    </p>
                </div>
                <div @class([
                    'w-9 h-9 rounded border flex items-center justify-center shrink-0',
                    'bg-red-50 text-red-700 border-red-200' => $value < 5,
                    'bg-forest-50 text-forest-700 border-forest-200' => $value >= 5,
                ])>
                    <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-[18px] h-[18px]" />
                </div>
            </div>
        </x-card>
    @endforeach
</div>

{{-- ------------------------------------------------------------------
     The card itself
     ------------------------------------------------------------------ --}}
<x-card title="Official ledger card">
    <x-slot:actions>
        <span class="badge badge-slate">Maintained by HR</span>
    </x-slot:actions>

    {{-- The official card, laid out from the campus template. It is drawn
         from your posted ledger entries, so it is always available — there is
         no workbook to seed first. --}}
    <iframe src="{{ route('leave.ledger.pdf') }}"
            class="w-full h-[720px] rounded-lg border border-sand-200 bg-sand-50"
            title="My leave ledger card"></iframe>

    <p class="text-[11px] text-sand-400 mt-3">
        If the card does not appear, your browser may not preview PDFs —
        <a href="{{ route('leave.ledger.pdf') }}" target="_blank"
           class="font-medium text-maroon-700 hover:text-maroon-900">open it in a new tab</a>.
    </p>
</x-card>

@endsection
