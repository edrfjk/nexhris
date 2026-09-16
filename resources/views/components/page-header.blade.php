@props(['title', 'subtitle' => null, 'back' => null, 'crumbs' => [], 'section' => null])

{{--
    The page heading lives in the application bar, once.

    It used to sit in the content with the module name in the bar above it,
    which read as two stacked headers. The bar now carries the title itself,
    so a screen announces itself in one place and the content begins with the
    content.
--}}
@push('page-title')
    <h1 class="page-title truncate">{{ $title }}</h1>

    @if ($subtitle)
        <p class="mt-0.5 truncate text-[12px] leading-tight text-sand-500">{{ $subtitle }}</p>
    @endif
@endpush

@php
    // Nothing is drawn in the content unless the page offers a trail or
    // something to do; an empty bar of whitespace is worse than no bar.
    $hasToolbar = $crumbs || $back || isset($actions);
@endphp

@if ($hasToolbar)
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        {{-- Breadcrumbs sit in the content, where a trail belongs. --}}
        @if ($crumbs)
            <nav class="crumbs min-w-0" aria-label="Breadcrumb">
                @foreach ($crumbs as $label => $url)
                    @if ($url)
                        <a href="{{ $url }}">{{ $label }}</a>
                    @else
                        <span>{{ $label }}</span>
                    @endif
                    <x-heroicon-o-chevron-right />
                @endforeach
                <span class="truncate font-medium text-sand-700">{{ $title }}</span>
            </nav>
        @else
            <span></span>
        @endif

        @if ($back || isset($actions))
            <div class="flex max-w-full flex-wrap items-center gap-2">
                @if ($back)
                    <a href="{{ $back }}" class="btn btn-sm btn-secondary">
                        <x-heroicon-o-arrow-left />
                        Back
                    </a>
                @endif
                {{ $actions ?? '' }}
            </div>
        @endif
    </div>
@endif
