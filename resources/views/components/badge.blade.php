@props(['color' => 'gray'])

@php
$colors = [
    'green' => 'bg-green-100 text-green-700',
    'yellow' => 'bg-yellow-100 text-yellow-700',
    'red' => 'bg-red-100 text-red-700',
    'blue' => 'bg-blue-100 text-blue-700',
    'purple' => 'bg-purple-100 text-purple-700',
    'gray' => 'bg-gray-200 text-gray-600',
];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ' . $colors[$color]]) }}>
    {{ $slot }}
</span>