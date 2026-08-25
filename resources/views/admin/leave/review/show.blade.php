@extends('layouts.app')
@section('title', 'Review Leave Form')

@section('content')

@php
    $employee = $application->user;
    $balance = $employee->leaveBalance;
    $isHr = auth()->user()->isAdmin();
@endphp

<x-page-header
    :title="'Leave Form · ' . $employee->name"
    :subtitle="'Reference LV-' . str_pad($application->id, 5, '0', STR_PAD_LEFT)">
    <x-slot:actions>
        <a href="{{ route('admin.leave.review.index') }}"
           class="btn btn-md btn-secondary">
            ← Back to queue
        </a>
    </x-slot:actions>
</x-page-header>

{{-- ------------------------------------------------------------------
     Chain progress
     ------------------------------------------------------------------ --}}
<div class="card mb-6 p-5">
    <div class="flex items-start justify-between gap-4 mb-5">
        <div>
            <p class="section-label">Status</p>
            <p class="mt-1 text-lg font-semibold text-sand-800">{{ $application->currentStageLabel() }}</p>
        </div>
        <x-leave.status-pill :application="$application" />
    </div>

    <x-leave.stepper :application="$application" />
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- ============================================================
         LEFT — the form and its details
         ============================================================ --}}
    <div class="lg:col-span-2 space-y-6">

        <x-card title="Leave details">
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                <div>
                    <dt class="text-xs text-sand-400">Type of leave</dt>
                    <dd class="mt-0.5 font-medium text-sand-800">
                        {{ $application->leave_type === 'VL' ? 'Vacation Leave' : 'Sick Leave' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-sand-400">Working days</dt>
                    <dd class="mt-0.5 font-medium text-sand-800">
                        {{ number_format((float) $application->days, 2) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-sand-400">Inclusive dates</dt>
                    <dd class="mt-0.5 font-medium text-sand-800">
                        {{ $application->date_from?->format('F j, Y') }}
                        @if ($application->date_to && ! $application->date_to->eq($application->date_from))
                            – {{ $application->date_to->format('F j, Y') }}
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-sand-400">Submitted</dt>
                    <dd class="mt-0.5 font-medium text-sand-800">
                        {{ $application->uploaded_at?->format('F j, Y g:i A') ?: '—' }}
                    </dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs text-sand-400">Reason given</dt>
                    <dd class="mt-0.5 text-sand-700">{{ $application->reason ?: '—' }}</dd>
                </div>
            </dl>
        </x-card>

        {{-- The uploaded form itself --}}
        <x-card title="Uploaded leave form">
            @if ($application->file_path)
                @php $ext = $application->formExtension(); @endphp

                <div class="flex items-center justify-between gap-4 mb-4 p-3 rounded-lg bg-sand-50 border border-sand-200">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-lg bg-maroon-50 text-maroon-800 flex items-center justify-center flex-shrink-0">
                            <x-heroicon-o-document-text class="w-4 h-4" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-sand-800 truncate">
                                {{ $application->file_original_name ?: 'Leave form' }}
                            </p>
                            <p class="text-xs text-sand-400 uppercase">{{ $ext }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <a href="{{ route('admin.leave.review.form.pdf', $application) }}" target="_blank"
                           class="btn btn-sm btn-primary">
                            <x-heroicon-o-document-text class="w-4 h-4" />
                            View as PDF
                        </a>
                        <a href="{{ route('admin.leave.review.form', $application) }}"
                           class="btn btn-sm btn-secondary">
                            Original
                        </a>
                    </div>
                </div>

                {{-- Employees fill the campus form in Excel, and no browser
                     shows an .xlsx. It is converted so every reviewer in the
                     chain can read it here rather than downloading it first. --}}
                <iframe src="{{ $ext === 'pdf'
                        ? route('admin.leave.review.form', $application)
                        : route('admin.leave.review.form.pdf', $application) }}"
                        class="w-full h-[560px] rounded-lg border border-sand-200 bg-sand-50"
                        title="Uploaded leave form"></iframe>

                @if ($ext !== 'pdf')
                    <p class="text-[11px] text-sand-400 mt-2">
                        Converted from the uploaded
                        <span class="uppercase">{{ $ext }}</span> for reading.
                        Use <span class="font-medium">Original</span> for the file as it was filed.
                    </p>
                @endif
            @else
                <x-empty-state message="No file was attached to this application." />
            @endif
        </x-card>

        {{-- Full audit trail --}}
        @if ($application->approvals->isNotEmpty())
            <x-card title="Review history">
                <ol class="space-y-3">
                    @foreach ($application->approvals as $approval)
                        <li class="flex gap-3">
                            <div @class([
                                'w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5',
                                'bg-forest-100 text-forest-700' => $approval->action === 'approved',
                                'bg-red-100 text-red-700' => $approval->action === 'returned',
                            ])>
                                @if ($approval->action === 'approved')
                                    <x-heroicon-o-check class="w-3.5 h-3.5" />
                                @else
                                    <x-heroicon-o-x-mark class="w-3.5 h-3.5" />
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-sand-800">
                                    <span class="font-semibold">{{ $approval->approver->name ?? 'Unknown' }}</span>
                                    <span class="text-sand-500">
                                        ({{ $approval->stageLabel() }})
                                        {{ $approval->action === 'approved' ? 'approved' : 'returned' }} this form
                                    </span>
                                </p>
                                <p class="text-xs text-sand-400">{{ $approval->created_at->format('F j, Y g:i A') }}</p>
                                @if ($approval->remarks)
                                    <p class="mt-1 text-sm text-sand-600 bg-sand-50 rounded-md px-3 py-2 border border-sand-100">
                                        {{ $approval->remarks }}
                                    </p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </x-card>
        @endif
    </div>

    {{-- ============================================================
         RIGHT — employee context and the decision
         ============================================================ --}}
    <div class="space-y-6">

        <x-card title="Employee">
            <p class="font-semibold text-sand-800">{{ $employee->name }}</p>
            <p class="text-xs text-sand-400 mb-4">{{ $employee->employee_number ?: '—' }}</p>

            <dl class="space-y-2.5 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-sand-400 text-xs">Position</dt>
                    <dd class="text-sand-700 text-right">{{ $employee->position ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-sand-400 text-xs">College / Office</dt>
                    <dd class="text-sand-700 text-right">{{ $employee->collegeName() ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-sand-400 text-xs">Department</dt>
                    <dd class="text-sand-700 text-right">{{ $employee->departmentName() ?: '—' }}</dd>
                </div>
            </dl>

            <div class="grid grid-cols-3 gap-2 mt-4 pt-4 border-t border-sand-100 text-center">
                <div>
                    <p class="section-label">VL</p>
                    <p class="text-lg font-bold text-sand-800">{{ number_format((float) ($balance->vl_balance ?? 0), 2) }}</p>
                </div>
                <div>
                    <p class="section-label">SL</p>
                    <p class="text-lg font-bold text-sand-800">{{ number_format((float) ($balance->sl_balance ?? 0), 2) }}</p>
                </div>
                <div>
                    <p class="section-label">Service</p>
                    <p class="text-lg font-bold text-sand-800">{{ number_format((float) ($balance->service_balance ?? 0), 2) }}</p>
                </div>
            </div>

            {{-- Whoever signs this should not have to open the ledger and
                 subtract by hand to see whether the credits cover it. --}}
            @php
                $typeLabel = $application->leave_type === 'SL' ? 'sick leave' : 'vacation leave';
                $shortfall = $application->creditShortfall();
            @endphp

            <div @class([
                'mt-4 rounded-lg px-3 py-2.5 text-xs leading-relaxed border',
                'bg-gold-50 border-gold-200 text-gold-900' => $shortfall > 0,
                'bg-forest-50 border-forest-200 text-forest-800' => $shortfall <= 0,
            ])>
                @if ($shortfall > 0)
                    @php
                        // Built in one piece: split across lines in the
                        // template it renders as "Short by 5\n days".
                        $shortText = rtrim(rtrim(number_format($shortfall, 2), '0'), '.')
                            . ' day' . ($shortfall == 1 ? '' : 's');
                    @endphp
                    <span class="font-semibold">Short by {{ $shortText }}.</span>
                    Asks for {{ number_format((float) $application->days, 2) }}
                    against {{ number_format($application->availableCredits(), 2) }}
                    {{ $typeLabel }} credits. Approving is still allowed — HR records
                    the excess as leave without pay.
                @else
                    <span class="font-semibold">Credits cover this.</span>
                    {{ number_format((float) $application->days, 2) }} of
                    {{ number_format($application->availableCredits(), 2) }}
                    {{ $typeLabel }} credits.
                @endif
            </div>

            <a href="{{ route('admin.leave.ledger', $employee) }}"
               class="mt-4 block text-center text-xs font-medium text-maroon-700 hover:text-maroon-900">
                View full ledger card →
            </a>
        </x-card>

        {{-- ---------- Decision ---------- --}}
        @if ($canReview)
            <div x-data="{ mode: null }" class="card overflow-hidden">
                <div class="px-5 py-4 border-b border-sand-100">
                    <h3 class="font-semibold text-sm text-sand-700">Your decision</h3>
                    <p class="text-xs text-sand-400 mt-0.5">
                        As {{ auth()->user()->roleLabel() }}
                    </p>
                </div>

                <div class="p-5 space-y-3">
                    <div class="grid grid-cols-2 gap-2" x-show="mode === null">
                        <button @click="mode = 'approve'"
                                class="btn btn-lg btn-success">
                            Approve
                        </button>
                        <button @click="mode = 'return'"
                                class="btn btn-lg btn-danger-soft">
                            Return
                        </button>
                    </div>

                    {{-- Approve --}}
                    <form method="POST" action="{{ route('admin.leave.review.approve', $application) }}"
                          x-show="mode === 'approve'" x-cloak class="space-y-3">
                        @csrf
                        <label class="block">
                            <span class="label">Remarks <span class="text-sand-400">(optional)</span></span>
                            <textarea name="remarks" rows="3" maxlength="500"
                                      class="textarea mt-1"
                                      placeholder="Anything the next reviewer should know…"></textarea>
                        </label>
                        <div class="flex gap-2">
                            <button class="btn btn-md btn-success flex-1">
                                Confirm approval
                            </button>
                            <button type="button" @click="mode = null"
                                    class="btn btn-md btn-secondary">
                                Cancel
                            </button>
                        </div>
                    </form>

                    {{-- Return --}}
                    <form method="POST" action="{{ route('admin.leave.review.return', $application) }}"
                          x-show="mode === 'return'" x-cloak class="space-y-3">
                        @csrf
                        <label class="block">
                            <span class="label">
                                What needs correcting? <span class="text-red-500">*</span>
                            </span>
                            <textarea name="remarks" rows="3" required maxlength="500"
                                      class="textarea mt-1"
                                      placeholder="e.g. The inclusive dates do not match the attached medical certificate."></textarea>
                        </label>
                        @php
                            // A Dean's own form has no Dean stage, so naming
                            // the Dean here would be wrong for them.
                            $firstStage = app(\App\Services\LeaveChain::class)->stagesFor($employee)[0] ?? null;
                        @endphp
                        <p class="text-[11px] text-sand-500">
                            {{ $employee->name }} re-uploads a corrected form, and the chain restarts
                            @if ($firstStage)
                                from the {{ \App\Services\LeaveChain::LABELS[$firstStage] }}.
                            @else
                                from the beginning.
                            @endif
                        </p>
                        <div class="flex gap-2">
                            <button class="btn btn-md btn-danger flex-1">
                                Return to employee
                            </button>
                            <button type="button" @click="mode = null"
                                    class="btn btn-md btn-secondary">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @else
            <div class="rounded border border-sand-200 bg-sand-50 p-5 text-sm text-sand-600">
                <p class="font-medium text-sand-700 mb-1">No action needed from you</p>
                <p class="text-xs leading-relaxed">
                    {{ $application->currentStageLabel() }}.
                </p>
            </div>
        @endif

        {{-- ---------- HR: post the approved leave to the ledger ---------- --}}
        @if ($isHr && $application->isFullyApproved())
            <div class="card overflow-hidden">
                <div class="px-5 py-4 border-b border-sand-100 flex items-center justify-between">
                    <h3 class="font-semibold text-sm text-sand-700">Post to ledger card</h3>
                    @if ($application->ledger_posted)
                        <x-badge color="green">Posted</x-badge>
                    @endif
                </div>

                <div class="p-5">
                    @if ($application->ledger_posted)
                        <p class="text-sm text-sand-600">
                            This leave was recorded on {{ $employee->name }}'s ledger card.
                        </p>
                        <a href="{{ route('admin.leave.ledger', $employee) }}"
                           class="mt-3 inline-block text-xs font-medium text-maroon-700 hover:text-maroon-900">
                            Open ledger card →
                        </a>
                    @else
                        <p class="text-xs text-sand-500 mb-4 leading-relaxed">
                            The Campus Director has approved. Choose which card this leave is
                            written on, then record the days taken. Days the employee had no
                            credits for go in the <strong>without pay</strong> column.
                        </p>

                        <form method="POST" action="{{ route('admin.leave.review.post-to-ledger', $application) }}"
                              class="space-y-3">
                            @csrf

                            {{-- The campus keeps two cards. A day charged to service
                                 credits comes off that balance even when the leave
                                 itself was sick or vacation. --}}
                            <div x-data="{ card: 'leave' }" class="mb-4">
                                <span class="label">Record on</span>
                                <div class="grid grid-cols-2 gap-2 mt-1">
                                    <label class="cursor-pointer">
                                        <input type="radio" name="ledger" value="leave" x-model="card"
                                               class="sr-only" checked>
                                        <span :class="card === 'leave'
                                                ? 'border-maroon-700 bg-maroon-50 text-maroon-900'
                                                : 'border-sand-200 text-sand-600 hover:border-sand-300'"
                                              class="block rounded-lg border px-3 py-2 text-center transition">
                                            <span class="block text-[13px] font-medium">Leave ledger</span>
                                            <span class="block text-[10px] mt-0.5">charges VL or SL</span>
                                        </span>
                                    </label>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="ledger" value="service" x-model="card"
                                               class="sr-only">
                                        <span :class="card === 'service'
                                                ? 'border-maroon-700 bg-maroon-50 text-maroon-900'
                                                : 'border-sand-200 text-sand-600 hover:border-sand-300'"
                                              class="block rounded-lg border px-3 py-2 text-center transition">
                                            <span class="block text-[13px] font-medium">Service credits</span>
                                            <span class="block text-[10px] mt-0.5">charges service credits</span>
                                        </span>
                                    </label>
                                </div>
                            </div>


                            <div class="grid grid-cols-2 gap-2">
                                <label class="block">
                                    <span class="label">From</span>
                                    <input type="date" name="period_from" required
                                           value="{{ old('period_from', $application->date_from?->format('Y-m-d')) }}"
                                           class="input mt-1">
                                </label>
                                <label class="block">
                                    <span class="label">To</span>
                                    <input type="date" name="period_to" required
                                           value="{{ old('period_to', $application->date_to?->format('Y-m-d')) }}"
                                           class="input mt-1">
                                </label>
                            </div>

                            <label class="block">
                                <span class="label">Total days</span>
                                <input type="number" step="0.01" min="0" name="days" required
                                       value="{{ old('days', number_format((float) $application->days, 2, '.', '')) }}"
                                       class="input mt-1">
                            </label>

                            <div class="pt-2 border-t border-sand-100">
                                <p class="section-label mb-2">
                                    Vacation leave charged
                                </p>
                                <div class="grid grid-cols-2 gap-2">
                                    <label class="block">
                                        <span class="label">With pay</span>
                                        <input type="number" step="0.01" min="0" name="vl_used"
                                               value="{{ old('vl_used', $application->leave_type === 'VL' ? number_format((float) $application->days, 2, '.', '') : '0') }}"
                                               class="input mt-1">
                                    </label>
                                    <label class="block">
                                        <span class="label">Without pay</span>
                                        <input type="number" step="0.01" min="0" name="vl_used_wop" value="{{ old('vl_used_wop', '0') }}"
                                               class="input mt-1">
                                    </label>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-sand-100">
                                <p class="section-label mb-2">
                                    Sick leave charged
                                </p>
                                <div class="grid grid-cols-2 gap-2">
                                    <label class="block">
                                        <span class="label">With pay</span>
                                        <input type="number" step="0.01" min="0" name="sl_used"
                                               value="{{ old('sl_used', $application->leave_type === 'SL' ? number_format((float) $application->days, 2, '.', '') : '0') }}"
                                               class="input mt-1">
                                    </label>
                                    <label class="block">
                                        <span class="label">Without pay</span>
                                        <input type="number" step="0.01" min="0" name="sl_used_wop" value="{{ old('sl_used_wop', '0') }}"
                                               class="input mt-1">
                                    </label>
                                </div>
                            </div>

                            <label class="block pt-2 border-t border-sand-100">
                                <span class="label">Service credits used</span>
                                <input type="number" step="0.01" min="0" name="service_used" value="{{ old('service_used', '0') }}"
                                       class="input mt-1">
                            </label>

                            <label class="block">
                                <span class="label">Remarks on the card <span class="text-red-500">*</span></span>
                                <input type="text" name="remarks" required maxlength="255"
                                       value="{{ old('remarks', $application->leave_type . ' — ' . ($application->reason ?: 'Approved leave')) }}"
                                       class="input mt-1">
                            </label>

                            <button class="btn btn-lg btn-primary w-full">
                                Post to ledger
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        {{-- ---------- Print the approval sheet ---------- --}}
        @if ($application->isFullyApproved())
            <a href="{{ route('admin.leave.review.print', $application) }}" target="_blank"
               class="btn btn-md btn-secondary">
                <x-heroicon-o-printer class="w-4 h-4" />
                Print approval sheet
            </a>
        @endif
    </div>
</div>

@endsection
