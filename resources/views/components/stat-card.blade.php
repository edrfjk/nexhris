@props(['label', 'value', 'icon' => null, 'color' => 'maroon', 'href' => null])

@php
$colors = [
    'maroon' => 'bg-maroon-50 text-maroon-800',
    'green' => 'bg-green-50 text-green-700',
    'yellow' => 'bg-yellow-50 text-yellow-700',
    'blue' => 'bg-blue-50 text-blue-700',
    'red' => 'bg-red-50 text-red-700',
    'gray' => 'bg-gray-100 text-gray-600',
];
@endphp

<{{ $href ? 'a' : 'div' }} {{ $href ? 'href='.$href : '' }}
    class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-center gap-4 {{ $href ? 'hover:shadow-md hover:-translate-y-0.5 transition' : '' }}">
    @if ($icon)
        <div class="w-11 h-11 rounded-lg flex items-center justify-center flex-shrink-0 {{ $colors[$color] }}">
            {!! $icon !!}
        </div>
    @endif
    <div>
        <p class="text-xs font-medium text-gray-500">{{ $label }}</p>
        <p class="text-2xl font-bold text-gray-800">{{ $value }}</p>
    </div>
</{{ $href ? 'a' : 'div' }}>