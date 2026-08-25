@props(['href', 'active' => false, 'icon' => null, 'badge' => 0])

{{-- Icons come from the Heroicons package rather than hand-copied SVG paths,
     so weight and sizing stay identical everywhere. --}}
<a href="{{ $href }}" class="nav-link {{ $active ? 'nav-link-active' : '' }}">

    @if ($icon)
        <x-dynamic-component :component="'heroicon-o-' . $icon" />
    @endif

    <span class="flex-1 min-w-0 truncate">{{ $slot }}</span>

    @if ((int) $badge > 0)
        <span class="shrink-0 min-w-[1.25rem] px-1.5 rounded-md bg-ispscgold
                     text-maroon-900 text-[10px] font-bold text-center leading-[1.1rem] tabular">
            {{ $badge > 99 ? '99+' : $badge }}
        </span>
    @endif
</a>
