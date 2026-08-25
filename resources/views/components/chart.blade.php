@props(['id', 'type' => 'bar', 'labels' => [], 'datasets' => [], 'height' => 'h-64'])

{{-- Data travels in a JSON script tag so no markup inlines a JS literal. --}}
<div class="relative {{ $height }}">
    <canvas data-chart="{{ $id }}-data" data-type="{{ $type }}"></canvas>
</div>

@push('scripts')
    <script type="application/json" id="{{ $id }}-data">
        @json(['labels' => $labels, 'datasets' => $datasets])
    </script>
@endpush
