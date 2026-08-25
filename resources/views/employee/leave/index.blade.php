@extends('layouts.app')
@section('title', 'My Leave')

@section('content')

<x-page-header
    title="My Leave"
    subtitle="Download the form, fill it in, upload it — then track it through the approval chain">
    <x-slot:actions>
        {{-- One card covers both records now: the leave ledger and, on its
             own page, the service credits. It opens as a page rather than a
             file, so no new tab and no "(PDF)" in the label. --}}
        <a href="{{ route('leave.ledger.mine') }}" class="btn btn-md btn-secondary">
            <x-heroicon-o-book-open />
            My ledger card
        </a>
    </x-slot:actions>
</x-page-header>

{{-- ------------------------------------------------------------------
     Balances
     ------------------------------------------------------------------ --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-maroon-800 text-white rounded p-5 shadow-soft">
        <p class="text-[11px] font-medium text-white/70 uppercase tracking-wide">Vacation Leave</p>
        <p class="text-3xl font-bold mt-1">{{ number_format((float) ($balance->vl_balance ?? 0), 2) }}</p>
        <p class="text-[11px] text-white/60 mt-0.5">days available</p>
    </div>

    <div class="card p-5">
        <p class="section-label">Sick Leave</p>
        <p class="text-3xl font-bold mt-1 text-sand-800">{{ number_format((float) ($balance->sl_balance ?? 0), 2) }}</p>
        <p class="text-[11px] text-sand-400 mt-0.5">days available</p>
    </div>

    <div class="card p-5">
        <p class="section-label">Service Credits</p>
        <p class="text-3xl font-bold mt-1 text-sand-800">{{ number_format((float) ($balance->service_balance ?? 0), 2) }}</p>
        <p class="text-[11px] text-sand-400 mt-0.5">days available</p>
    </div>

    <div class="card p-5">
        <p class="section-label">Used this year</p>
        <p class="text-3xl font-bold mt-1 text-sand-800">{{ number_format((float) $approvedThisYear, 2) }}</p>
        <p class="text-[11px] text-sand-400 mt-0.5">approved days</p>
    </div>
</div>

{{-- ------------------------------------------------------------------
     Attention banners
     ------------------------------------------------------------------ --}}
@if ($needsAttention > 0)
    <div class="mb-6 rounded border border-red-200 bg-red-50 px-5 py-4 flex items-start gap-3">
        <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5" />
        <div>
            <p class="text-sm font-semibold text-red-800">
                {{ $needsAttention }} form{{ $needsAttention === 1 ? ' was' : 's were' }} returned to you
            </p>
            <p class="text-xs text-red-700 mt-0.5">
                Read the reviewer's remarks below, correct the form, and upload it again.
            </p>
        </div>
    </div>
@endif

@if ($readyToPrint > 0)
    <div class="mb-6 rounded border border-forest-200 bg-forest-50 px-5 py-4 flex items-start gap-3">
        <x-heroicon-o-check-circle class="w-5 h-5 text-forest-600 flex-shrink-0 mt-0.5" />
        <div>
            <p class="text-sm font-semibold text-forest-800">
                {{ $readyToPrint }} form{{ $readyToPrint === 1 ? ' is' : 's are' }} fully approved
            </p>
            <p class="text-xs text-forest-700 mt-0.5">
                The Dean, HR and the Campus Director have all signed off online.
                Print the approval sheet below and collect the wet signatures.
            </p>
        </div>
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- ============================================================
         LEFT — how it works + file a new leave
         ============================================================ --}}
    <div class="space-y-6">

        {{-- Step 1: get the form --}}
        <x-card>
            <div class="flex items-start gap-3 mb-4">
                <div class="w-7 h-7 rounded-full bg-maroon-800 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">1</div>
                <div>
                    <h3 class="font-semibold text-sm text-sand-800">Download the official form</h3>
                    <p class="text-xs text-sand-500 mt-0.5">
                        @if ($template)
                            {{ $template->label }} · {{ strtoupper($template->extension()) }} · {{ $template->sizeLabel() }}
                        @else
                            HR has not published a form yet — the standard form is provided.
                        @endif
                    </p>
                </div>
            </div>

            <a href="{{ route('leave.template.download') }}"
               class="btn btn-lg btn-primary w-full">
                <x-heroicon-o-arrow-up-tray class="w-4 h-4" />
                Download leave form
            </a>
        </x-card>

        {{-- Step 2: submit --}}
        <div class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-sand-100 flex items-start gap-3">
                <div class="w-7 h-7 rounded-full bg-maroon-800 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">2</div>
                <div>
                    <h3 class="font-semibold text-sm text-sand-800">Upload the filled-in form</h3>
                    <p class="text-xs text-sand-500 mt-0.5">It goes straight to your Dean.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('leave.store') }}" enctype="multipart/form-data" class="p-5 space-y-4">
                @csrf

                <label class="block">
                    <span class="label">Type of leave</span>
                    <select name="leave_type" required
                            class="select mt-1">
                        <option value="VL" @selected(old('leave_type') === 'VL')>Vacation Leave</option>
                        <option value="SL" @selected(old('leave_type') === 'SL')>Sick Leave</option>
                    </select>
                </label>

                <div class="grid grid-cols-2 gap-3">
                    <label class="block">
                        <span class="label">From</span>
                        <input type="date" name="date_from" required value="{{ old('date_from') }}"
                               class="input mt-1">
                    </label>
                    <label class="block">
                        <span class="label">To</span>
                        <input type="date" name="date_to" required value="{{ old('date_to') }}"
                               class="input mt-1">
                    </label>
                </div>

                <label class="block">
                    <span class="label">Reason <span class="text-sand-400">(optional)</span></span>
                    <textarea name="reason" rows="2" maxlength="500"
                              class="textarea mt-1"
                              placeholder="Brief reason for the leave">{{ old('reason') }}</textarea>
                </label>

                <label class="block">
                    <span class="label">Accomplished form <span class="text-red-500">*</span></span>
                    <input type="file" name="leave_form" required accept=".pdf,.xlsx,.xls,.doc,.docx"
                           class="file-input mt-1 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-maroon-50 file:text-maroon-800 hover:file:bg-maroon-100 file:cursor-pointer">
                    <span class="hint">PDF, Excel or Word · up to 10 MB</span>
                </label>

                <button class="btn btn-lg btn-primary w-full">
                    Submit for review
                </button>
            </form>
        </div>

        {{-- Step 3 explainer --}}
        <x-card>
            <div class="flex items-start gap-3 mb-3">
                <div class="w-7 h-7 rounded-full bg-maroon-800 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">3</div>
                <div>
                    <h3 class="font-semibold text-sm text-sand-800">Print only once approved</h3>
                </div>
            </div>
            <p class="text-xs text-sand-500 leading-relaxed">
                Your form is checked online by the <strong>Dean</strong>, then the
                <strong>HR Administrator</strong>, then the <strong>Campus Director</strong>.
                Only when all three approve does the print button unlock — so you never print a hard
                copy and chase signatures for a form that was going to be sent back.
            </p>
        </x-card>

        {{-- Recent ledger movement --}}
        @if ($ledger->isNotEmpty())
            <x-card title="Recent ledger activity">
                <ul class="space-y-2.5">
                    @foreach ($ledger as $row)
                        <li class="flex items-start justify-between gap-3 text-sm">
                            <div class="min-w-0">
                                <p class="text-sand-700 truncate">{{ $row->remarks ?: ucfirst($row->type) }}</p>
                                <p class="text-[11px] text-sand-400">{{ $row->periodLabel() }}</p>
                            </div>
                            <div class="text-right flex-shrink-0 text-xs">
                                <p class="text-sand-500">VL {{ number_format((float) $row->vl_balance, 2) }}</p>
                                <p class="text-sand-500">SL {{ number_format((float) $row->sl_balance, 2) }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif
    </div>

    {{-- ============================================================
         RIGHT — my applications
         ============================================================ --}}
    <div class="lg:col-span-2">
        <div class="card overflow-hidden">
            <div class="px-5 py-3.5 border-b border-sand-100 flex items-center justify-between gap-3 flex-wrap">
                <h3 class="font-semibold text-sm text-sand-700">My leave applications</h3>

                <div class="flex items-center gap-2 text-xs">
                    @if ($inReview > 0)
                        <span class="px-2 py-0.5 rounded-full bg-gold-50 text-gold-700 ring-1 ring-gold-100 font-medium">
                            {{ $inReview }} in review
                        </span>
                    @endif
                    <form method="GET">
                        <select name="status" onchange="this.form.submit()"
                                class="select">
                            <option value="">All statuses</option>
                            <option value="submitted" @selected(request('status') === 'submitted')>Awaiting Dean</option>
                            <option value="dean_approved" @selected(request('status') === 'dean_approved')>Awaiting HR</option>
                            <option value="hr_approved" @selected(request('status') === 'hr_approved')>Awaiting Campus Director</option>
                            <option value="cd_approved" @selected(request('status') === 'cd_approved')>Ready to print</option>
                            <option value="completed" @selected(request('status') === 'completed')>Completed</option>
                        </select>
                    </form>
                </div>
            </div>

            @if ($applications->isEmpty())
                <x-empty-state message="You have not filed any leave yet. Download the form to get started." />
            @else
                <ul class="divide-y divide-sand-100">
                    @foreach ($applications as $application)
                        <li class="p-5" x-data="{ fixing: false }">
                            <div class="flex items-start justify-between gap-4 flex-wrap mb-4">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <x-badge :color="$application->leave_type === 'VL' ? 'blue' : 'purple'">
                                            {{ $application->leave_type === 'VL' ? 'Vacation' : 'Sick' }}
                                        </x-badge>
                                        <p class="text-sm font-semibold text-sand-800">
                                            {{ $application->date_from?->format('M j, Y') }}
                                            @if ($application->date_to && ! $application->date_to->eq($application->date_from))
                                                – {{ $application->date_to->format('M j, Y') }}
                                            @endif
                                        </p>
                                        <span class="text-xs text-sand-400">
                                            {{ rtrim(rtrim(number_format((float) $application->days, 2), '0'), '.') }} day(s)
                                        </span>
                                    </div>
                                    @if ($application->reason)
                                        <p class="text-xs text-sand-500 mt-1">{{ $application->reason }}</p>
                                    @endif
                                </div>

                                <x-leave.status-pill :application="$application" />
                            </div>

                            <x-leave.stepper :application="$application" class="mb-4" />

                            {{-- Reviewer remarks when returned --}}
                            @if ($application->isReturned() && $application->remarks)
                                <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 mb-3">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-red-700 mb-1">
                                        What needs correcting
                                    </p>
                                    <p class="text-sm text-red-800">{{ $application->remarks }}</p>
                                </div>
                            @endif

                            <div class="flex items-center gap-2 flex-wrap">
                                @if ($application->file_path)
                                    {{-- The same converted copy the Dean, HR and
                                         the Campus Director read, so you can see
                                         exactly what they are signing. --}}
                                    <a href="{{ route('leave.form.pdf', $application) }}" target="_blank"
                                       class="btn btn-sm btn-primary">
                                        <x-heroicon-o-document-text class="w-3.5 h-3.5" />
                                        View as PDF
                                    </a>
                                    <a href="{{ $application->employeeFormUrl() }}" target="_blank"
                                       class="btn btn-sm btn-secondary">
                                        My upload
                                    </a>
                                @endif

                                @if ($application->isFullyApproved())
                                    <a href="{{ route('leave.print', $application) }}" target="_blank"
                                       class="btn btn-sm btn-success">
                                        <x-heroicon-o-printer class="w-3.5 h-3.5" />
                                        Print approval sheet
                                    </a>
                                @elseif ($application->isReturned())
                                    <button @click="fixing = !fixing"
                                            class="btn btn-sm btn-primary">
                                        Upload corrected form
                                    </button>
                                @endif
                            </div>

                            {{-- Re-upload --}}
                            @if ($application->isReturned())
                                <form method="POST" action="{{ route('leave.resubmit', $application) }}"
                                      enctype="multipart/form-data" x-show="fixing" x-cloak
                                      class="mt-3 p-4 rounded-lg bg-sand-50 border border-sand-200 space-y-3">
                                    @csrf
                                    <label class="block">
                                        <span class="label">Corrected form</span>
                                        <input type="file" name="leave_form" required accept=".pdf,.xlsx,.xls,.doc,.docx"
                                               class="file-input mt-1 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-maroon-50 file:text-maroon-800 hover:file:bg-maroon-100 file:cursor-pointer">
                                    </label>
                                    <div class="flex gap-2">
                                        <button class="btn btn-sm btn-primary">
                                            Re-submit to Dean
                                        </button>
                                        <button type="button" @click="fixing = false"
                                                class="btn btn-sm btn-secondary">
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>

                <div class="px-5 py-3 border-t border-sand-100">
                    {{ $applications->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

@endsection
