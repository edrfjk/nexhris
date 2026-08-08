{{-- resources/views/admin/pdf/partials/header.blade.php
     Shared header for every PDF export.
     Expects: $title (string), $generatedAt (Carbon), $generatedBy (string)
     Optional: $subtitle (string) — extra context after the campus name
     Optional: $filtersApplied (Collection of label => value) — rendered as chips --}}
<div class="header">
    <div class="header-left">
        <p class="title">{{ $title }}</p>
        <p class="subtitle">
            Ilocos Sur Polytechnic State College — Tagudin Campus
            @isset($subtitle)
                · {{ $subtitle }}
            @endisset
        </p>
    </div>
    <div class="header-right">
        <span class="meta-label">Generated</span><br>
        <span class="meta-value">{{ $generatedAt->format('F j, Y \a\t g:i A') }}</span><br>
        <span class="meta-label">by {{ $generatedBy }}</span>
    </div>
</div>

@isset($filtersApplied)
    @if ($filtersApplied->isNotEmpty())
        <div class="filters">
            Filters applied:
            @foreach ($filtersApplied as $label => $value)
                <span>{{ $label }}: {{ $value }}</span>
            @endforeach
        </div>
    @endif
@endisset