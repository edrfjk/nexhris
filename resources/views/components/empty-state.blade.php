@props([
    'message' => 'Nothing here yet.',
    'title' => null,
    'icon' => 'inbox',
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-10 text-center']) }}>
    <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-8 h-8 text-sand-300 mb-2.5" />

    @if ($title)
        <p class="text-[13px] font-medium text-sand-700">{{ $title }}</p>
        <p class="text-xs text-sand-500 mt-1 max-w-sm">{{ $message }}</p>
    @else
        <p class="text-xs text-sand-500 max-w-sm">{{ $message }}</p>
    @endif

    @isset($action)
        <div class="mt-3.5">{{ $action }}</div>
    @endisset
</div>
