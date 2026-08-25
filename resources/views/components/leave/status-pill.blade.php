@props(['application'])

@php
    // Map the workflow status onto a badge tone and an icon, so the state is
    // legible at a glance without reading the label.
    [$tone, $icon] = match (true) {
        $application->status === 'completed'   => ['green', 'check-badge'],
        $application->status === 'cd_approved' => ['green', 'check-circle'],
        $application->isReturned()             => ['red', 'arrow-uturn-left'],
        $application->status === 'draft'       => ['slate', 'pencil-square'],
        default                                => ['amber', 'clock'],
    };
@endphp

<span {{ $attributes->merge(['class' => 'badge badge-' . $tone]) }}>
    <x-dynamic-component :component="'heroicon-o-' . $icon" />
    {{ $application->currentStageLabel() }}
</span>
