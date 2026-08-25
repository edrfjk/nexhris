@props([
    'title' => null,
    'subtitle' => null,
    'padded' => true,
])

{{-- $actions fills the right side of the header; $footer renders a bottom bar. --}}
<div {{ $attributes->merge(['class' => 'card']) }}>
    @if ($title || isset($actions))
        <div class="card-header">
            <div class="min-w-0">
                @if ($title)
                    <h3 class="card-title">{{ $title }}</h3>
                @endif
                @if ($subtitle)
                    <p class="text-xs text-sand-400 mt-0.5">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex items-center gap-2 flex-shrink-0">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    {{-- An unpadded card lets the slot run edge to edge (lists, tables).
         Emitting no class attribute at all keeps the markup clean. --}}
    @if ($padded)
        <div class="card-body">{{ $slot }}</div>
    @else
        {{ $slot }}
    @endif

    @isset($footer)
        <div class="card-footer">{{ $footer }}</div>
    @endisset
</div>
