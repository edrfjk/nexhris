@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'icon' => null,
])

@php
    $classes = trim(implode(' ', [
        'btn',
        'btn-' . $size,
        'btn-' . $variant,
    ]));

    $tag = $href ? 'a' : 'button';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @else type="{{ $attributes->get('type', 'submit') }}" @endif
    {{ $attributes->except('type')->merge(['class' => $classes]) }}>
    @isset($icon)
        {{ $icon }}
    @endisset
    {{ $slot }}
</{{ $tag }}>
