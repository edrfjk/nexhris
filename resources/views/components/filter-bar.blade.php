@props([
    // Where Clear goes back to — the bare list with nothing applied.
    'clear' => null,
    // Applied filters, as ['key' => ..., 'label' => ..., 'value' => ...].
    // Each becomes a chip that can be dropped on its own.
    'chips' => [],
    'action' => null,
])

@php
    $chips = collect($chips)->filter(fn ($chip) => filled($chip['value'] ?? null))->values();
    $clear = $clear ?? url()->current();
@endphp

<form method="GET" @if ($action) action="{{ $action }}" @endif class="filter-bar mb-5">

    {{-- Fields sit on a grid rather than free-wrapping, so the boxes line up
         in columns instead of forming a ragged row at every window width. --}}
    <div class="filter-grid">
        {{ $slot }}
    </div>

    <div class="filter-actions">
        <div class="flex flex-wrap items-center gap-2">
            <button class="btn btn-sm btn-primary">
                <x-heroicon-o-funnel />
                Apply filters
            </button>

            @if ($chips->isNotEmpty())
                <a href="{{ $clear }}" class="btn btn-sm btn-ghost">
                    <x-heroicon-o-x-mark />
                    Clear all
                </a>
            @endif
        </div>

        @if ($chips->isNotEmpty())
            {{-- Each chip names the filter it came from, because a value on
                 its own ("CAS", "Vacation") does not say which box set it. --}}
            <div class="flex flex-wrap items-center gap-1.5">
                <span class="text-xs text-sand-500 mr-0.5">Showing:</span>

                @foreach ($chips as $chip)
                    <a href="{{ request()->fullUrlWithQuery([$chip['key'] => null]) }}"
                       class="filter-chip"
                       title="Remove this filter">
                        <span class="text-sand-500">{{ $chip['label'] }}:</span>
                        <span class="font-medium">{{ $chip['value'] }}</span>
                        <x-heroicon-o-x-mark class="w-3 h-3 opacity-60" />
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</form>
