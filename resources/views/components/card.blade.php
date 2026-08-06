@props(['title' => null])

<div {{ $attributes->merge(['class' => 'bg-white rounded-xl shadow-sm border border-gray-100']) }}>
    @if ($title)
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-sm text-gray-700">{{ $title }}</h3>
        </div>
    @endif
    <div class="p-5">
        {{ $slot }}
    </div>
</div>