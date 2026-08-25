@props([
    'label',
    'hint' => null,
    // Search boxes need more room than a two-option select.
    'span' => 1,
])

@php
    $spanClass = match ((int) $span) {
        3 => 'sm:col-span-2 lg:col-span-3',
        2 => 'sm:col-span-2',
        default => null,
    };
@endphp

{{-- @class would still emit class="" once everything is excluded, so the
     attribute is written only when there is a span to write. --}}
<div @if ($spanClass) class="{{ $spanClass }}" @endif>
    {{-- Every control is labelled. A placeholder disappears the moment a
         value is chosen, which is why the old bars became unreadable once
         filters were applied. --}}
    <label class="label">{{ $label }}</label>
    {{ $slot }}
    @if ($hint)
        <span class="hint">{{ $hint }}</span>
    @endif
</div>
