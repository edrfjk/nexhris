@props(['message' => 'Nothing here yet.', 'icon' => null])

<div class="flex flex-col items-center justify-center py-12 text-gray-400">
    @if ($icon)
        <div class="w-12 h-12 mb-3 text-gray-300">{!! $icon !!}</div>
    @endif
    <p class="text-sm">{{ $message }}</p>
</div>