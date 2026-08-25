@props(['application', 'compact' => false])

@php
    // The chain is derived from the applicant's role, so a Dean's own form
    // shows the Dean stage as N/A rather than pretending it was approved.
    $steps = $application->timeline();
@endphp

<div {{ $attributes->merge(['class' => 'flex items-start ' . ($compact ? 'gap-1' : 'gap-2')]) }}>
    @foreach ($steps as $index => $step)
        @php
            [$mark, $text, $connector] = match ($step['state']) {
                'approved' => ['bg-forest-700 text-white border-forest-700', 'text-forest-800', 'bg-forest-500'],
                'current'  => ['bg-gold-400 text-maroon-900 border-gold-500', 'text-gold-800', 'bg-sand-300'],
                'returned' => ['bg-red-600 text-white border-red-600', 'text-red-700', 'bg-sand-300'],
                'skipped'  => ['bg-sand-100 text-sand-400 border-sand-200 border-dashed', 'text-sand-400', 'bg-sand-200'],
                default    => ['bg-white text-sand-400 border-sand-300', 'text-sand-400', 'bg-sand-200'],
            };
            $size = $compact ? 'w-4 h-4 text-[9px]' : 'w-6 h-6 text-[11px]';
            $glyph = $compact ? 'w-2.5 h-2.5' : 'w-3.5 h-3.5';
        @endphp

        <div class="flex-1 min-w-0">
            <div class="flex items-center">
                <div class="shrink-0 rounded-full border {{ $mark }} {{ $size }}
                            flex items-center justify-center font-semibold">
                    @if ($step['state'] === 'approved')
                        <x-heroicon-o-check class="{{ $glyph }}" stroke-width="3" />
                    @elseif ($step['state'] === 'returned')
                        <x-heroicon-o-x-mark class="{{ $glyph }}" stroke-width="3" />
                    @elseif ($step['state'] === 'skipped')
                        <x-heroicon-o-minus class="{{ $glyph }}" stroke-width="3" />
                    @else
                        {{ $index + 1 }}
                    @endif
                </div>

                @if (! $loop->last)
                    <div class="flex-1 h-px mx-1.5 {{ $connector }}"></div>
                @endif
            </div>

            <p class="mt-1.5 font-medium truncate {{ $text }} {{ $compact ? 'text-[9px]' : 'text-[11px]' }}">
                {{ $step['label'] }}
            </p>

            @unless ($compact)
                <p class="text-[10px] text-sand-400 truncate">
                    @if ($step['state'] === 'skipped')
                        {{-- The applicant holds this role themselves. --}}
                        N/A — own stage
                    @elseif ($step['at'])
                        {{ $step['who'] ?: '—' }} · {{ $step['at']->format('M j') }}
                    @elseif ($step['state'] === 'current')
                        Waiting
                    @else
                        —
                    @endif
                </p>
            @endunless
        </div>
    @endforeach
</div>
