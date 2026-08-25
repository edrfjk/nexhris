@props(['label'])

<div class="mb-1">
    <p class="px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wider text-sand-400/80">
        {{ $label }}
    </p>
    {{ $slot }}
</div>
