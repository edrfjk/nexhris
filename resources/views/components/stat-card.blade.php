@props([
    'label',
    'value',
    'icon' => null,
    'color' => 'maroon',
    'href' => null,
    'hint' => null,
    'emphasis' => false,
])

@php
    // Flat tint per tone — the icon chip is the only coloured element, so a row
    // of tiles stays readable rather than competing for attention.
    $tones = [
        'maroon' => 'bg-maroon-50 text-maroon-700 border-maroon-200',
        'green'  => 'bg-forest-50 text-forest-700 border-forest-200',
        'yellow' => 'bg-gold-50 text-gold-700 border-gold-200',
        'amber'  => 'bg-gold-50 text-gold-700 border-gold-200',
        'blue'   => 'bg-sky-50 text-sky-700 border-sky-200',
        'red'    => 'bg-red-50 text-red-700 border-red-200',
        'gray'   => 'bg-sand-100 text-sand-600 border-sand-200',
        'slate'  => 'bg-sand-100 text-sand-600 border-sand-200',
    ];

    $chip = $tones[$color] ?? $tones['maroon'];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->merge([
        'class' => 'card px-4 py-3 flex items-center gap-3 ' . ($href ? 'card-interactive' : ''),
    ]) }}>

    @if ($icon)
        <div class="w-9 h-9 rounded border flex items-center justify-center shrink-0 {{ $chip }}">
            @if (is_string($icon) && str_starts_with($icon, '<'))
                {!! $icon !!}
            @else
                <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-[18px] h-[18px]" />
            @endif
        </div>
    @endif

    <div class="min-w-0 flex-1">
        <p class="text-[11px] font-medium text-sand-500 truncate">{{ $label }}</p>
        <p class="text-xl font-semibold text-sand-900 leading-tight tabular">{{ $value }}</p>
        @if ($hint)
            <p class="text-[11px] text-sand-400 truncate">{{ $hint }}</p>
        @endif
    </div>

    @if ($href)
        <x-heroicon-o-chevron-right class="w-4 h-4 text-sand-300 shrink-0" />
    @endif
</{{ $tag }}>
