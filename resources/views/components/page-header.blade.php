@props(['title', 'subtitle' => null])

@push('page-title')
    <div>
        <h1 class="text-base font-semibold text-gray-800 leading-tight">{{ $title }}</h1>
        @if ($subtitle)
            <p class="text-xs text-gray-400 leading-tight mt-0.5">{{ $subtitle }}</p>
        @endif
    </div>
@endpush

@if (isset($actions))
    <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
        {{ $actions }}
    </div>
@endif