@props([
    'label',
    'value' => 0,
    'detail' => 'days available',
    'icon' => 'calendar-days',
])

{{-- Shared wherever an employee sees their available leave. Keeping the
     figure, label, icon and card surface here prevents the leave page and
     ledger page from quietly developing different visual languages. --}}
<x-card>
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="section-label">{{ $label }}</p>
            <p class="text-2xl font-semibold text-sand-900 tabular-nums mt-1">
                {{ number_format((float) $value, 2) }}
            </p>
            <p class="text-[11px] text-sand-400 mt-0.5">{{ $detail }}</p>
        </div>
        <div class="w-9 h-9 rounded-lg border border-maroon-200 bg-maroon-50 text-maroon-700 flex items-center justify-center shrink-0">
            <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-[18px] h-[18px]" />
        </div>
    </div>
</x-card>
