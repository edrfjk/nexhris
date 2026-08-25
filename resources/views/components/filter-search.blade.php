@props([
    'name' => 'search',
    'placeholder' => 'Search…',
])

<div class="relative">
    <x-heroicon-o-magnifying-glass
        class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-sand-400 pointer-events-none" />

    <input type="text"
           name="{{ $name }}"
           value="{{ request($name) }}"
           placeholder="{{ $placeholder }}"
           {{ $attributes->merge(['class' => 'input pl-9']) }}>
</div>
