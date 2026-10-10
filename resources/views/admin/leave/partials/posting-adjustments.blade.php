{{-- LEDGER-03: how the posting departed from the approved form, if it did.
     Read-only; the approved figures on the form itself are never changed. --}}
@php
    $adjustments = $application->postingAdjustments()->with('adjuster')->get();
@endphp

@if ($adjustments->isNotEmpty())
    <div class="mt-3 space-y-2">
        @foreach ($adjustments as $adjustment)
            <div class="rounded-lg border border-sand-200 bg-sand-50 p-3 text-xs text-sand-700">
                <p class="mb-1.5"><x-badge color="amber">Posted with adjustments</x-badge></p>
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach ($adjustment->differences as $difference)
                        <li>
                            {{ ucfirst(\App\Services\LeavePostingReconciler::label($difference['field'])) }}:
                            approved <strong>{{ $difference['approved'] ?? '—' }}</strong>,
                            posted <strong>{{ $difference['posted'] ?? '—' }}</strong>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-1.5"><span class="font-medium">Reason:</span> {{ $adjustment->reason }}</p>
                <p class="mt-0.5 text-sand-500">
                    {{ $adjustment->adjuster?->name ?? 'HR' }} · {{ $adjustment->created_at?->format('M j, Y g:i A') }}
                </p>
            </div>
        @endforeach
    </div>
@elseif ($application->ledger_posted && ! $application->posting_checked_at)
    <p class="mt-2 text-[11px] text-sand-500">
        Posted before adjustment tracking began — any difference from the approved figures was not recorded then.
    </p>
@endif
