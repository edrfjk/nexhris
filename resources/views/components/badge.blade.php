@props(['color' => 'gray', 'dot' => false])

@php
    // Legacy colour names are kept as aliases so existing call sites keep
    // working while everything settles on the design-system palette.
    $map = [
        'gray' => 'slate', 'slate' => 'slate',
        'maroon' => 'maroon',
        'green' => 'green', 'emerald' => 'green',
        'yellow' => 'amber', 'amber' => 'amber',
        'red' => 'red',
        'blue' => 'blue', 'sky' => 'blue',
        'purple' => 'violet', 'violet' => 'violet',
    ];

    $tone = $map[$color] ?? 'slate';
@endphp

<span {{ $attributes->merge(['class' => 'badge badge-' . $tone]) }}>
    @if ($dot)
        <span class="w-1.5 h-1.5 rounded-full bg-current opacity-70"></span>
    @endif
    {{ $slot }}
</span>
