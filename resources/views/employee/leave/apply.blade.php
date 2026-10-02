@extends('layouts.app')
@section('title', $application ? 'Correct Leave Form' : 'File Leave')

@php
    use App\Support\Leave\LeaveTypes;

    $editing = (bool) $application;
    $old = fn (string $key, $default = null) => old($key, data_get($values, $key, $default));

    // What the form needs to know about each type as the employee switches.
    $typeInfo = collect(LeaveTypes::ALL)->map(fn ($t, $code) => [
        'label' => $t['label'],
        'detail' => $t['detail'],
        'daysOnly' => LeaveTypes::get($code)['days_only'],
        'calendar' => LeaveTypes::get($code)['calendar'],
        'instruction' => $t['instruction'],
        'group' => $t['group'],
    ]);

    $initial = [
        'leave_type' => $old('leave_type'),
        'others_specify' => $old('others_specify'),
        'date_from' => $old('date_from'),
        'date_to' => $old('date_to'),
        'days' => $old('days'),
        'location' => $old('details.location'),
        'location_specify' => $old('details.location_specify'),
        'sickness' => $old('details.sickness'),
        'illness' => $old('details.illness'),
        'women_illness' => $old('details.women_illness'),
        'study' => $old('details.study'),
        'calamity_date' => $old('details.calamity_date'),
        'commutation' => $old('commutation', 'not_requested'),
    ];

    $existing = $application?->attachments() ?? [];
    $serverErrors = $errors->getMessages();

    $missing = array_keys(array_filter([
        'office or college' => blank($applicant['office']),
        'position' => blank($applicant['position']),
    ]));
@endphp

@section('content')
<x-page-header
    :title="$editing ? 'Correct Leave Form' : 'File Leave'"
    subtitle="CS Form No. 6 (Revised 2020) — fill it in here and the system prints the official form.">
    <x-slot:actions>
        <a href="{{ route('leave.index') }}" class="btn btn-md btn-secondary">← My leave</a>
    </x-slot:actions>
</x-page-header>

@if ($editing && $application->remarks)
    <div class="alert alert-error mb-5">
        <x-heroicon-o-arrow-uturn-left />
        <div>
            <p class="font-semibold">{{ $application->currentStageLabel() }}</p>
            <p class="mt-0.5">{{ $application->remarks }}</p>
        </div>
    </div>
@endif

<form method="POST"
      action="{{ $editing ? route('leave.update', $application) : route('leave.apply.store') }}"
      enctype="multipart/form-data"
      novalidate
      x-data="leaveForm(@js($initial), @js($typeInfo), @js($serverErrors))"
      x-init="start()"
      @input="touch($event)"
      @input.debounce.400ms="check()"
      @change="touch($event); check()"
      class="grid grid-cols-1 lg:grid-cols-[1fr_20rem] gap-6 items-start">
    @csrf
    @if ($editing)
        <input type="hidden" name="application" value="{{ $application->id }}">
    @endif

    {{-- ============================================================
         The form, in the form's own order
         ============================================================ --}}
    <div class="space-y-6 min-w-0">

        {{-- 1–5 --}}
        <section class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">1–5 · About you</h3>
                    <p class="text-xs text-sand-400 mt-0.5">Filled in from your employee record.</p>
                </div>
            </div>
            <div class="p-5">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                    <div>
                        <dt class="section-label">1. Office / Department</dt>
                        <dd class="mt-1 font-medium text-sand-800">{{ $applicant['office'] ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="section-label">2. Name <span class="normal-case font-normal">(last, first, middle)</span></dt>
                        <dd class="mt-1 font-medium text-sand-800">
                            {{ $applicant['last_name'] }}, {{ $applicant['first_name'] }} {{ $applicant['middle_name'] }}
                        </dd>
                    </div>
                    <div>
                        <dt class="section-label">3. Date of filing</dt>
                        <dd class="mt-1 font-medium text-sand-800">{{ now()->format('F j, Y') }}</dd>
                    </div>
                    <div>
                        <dt class="section-label">4. Position</dt>
                        <dd class="mt-1 font-medium text-sand-800">{{ $applicant['position'] ?: '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2 sm:max-w-xs">
                        <label for="salary" class="section-label block">5. Salary <span class="normal-case font-normal">(optional)</span></label>
                        <input id="salary" name="salary" type="text" maxlength="40" class="input mt-1"
                               value="{{ $old('salary') }}" placeholder="e.g. 32,870.00">
                        @error('salary') <span class="error-text">{{ $message }}</span> @enderror
                    </div>
                </dl>

                @if ($missing)
                    <p class="mt-4 text-xs text-gold-800 bg-gold-50 border border-gold-200 rounded-lg px-3 py-2">
                        Your record has no {{ implode(' or ', $missing) }}, so that box prints blank. Ask HR to update it.
                    </p>
                @endif
            </div>
        </section>

        {{-- 6.A --}}
        <section class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">6.A · Type of leave to be availed of</h3>
                    <p class="text-xs text-sand-400 mt-0.5">The types on the form, with the rule page 2 sets for each.</p>
                </div>
            </div>
            <div class="p-5 space-y-5">
                @foreach ([
                    LeaveTypes::FORM => null,
                    LeaveTypes::OTHER_PURPOSE => 'Other purpose',
                    LeaveTypes::OTHERS => 'Others',
                ] as $group => $heading)
                    @if ($heading)
                        <p class="section-label">{{ $heading }}</p>
                    @endif
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        @foreach (LeaveTypes::inGroup($group) as $code => $type)
                            <label class="cursor-pointer">
                                <input type="radio" name="leave_type" value="{{ $code }}" x-model="f.leave_type" class="sr-only">
                                <span :class="f.leave_type === @js($code)
                                        ? 'border-maroon-700 bg-maroon-50 ring-1 ring-maroon-700'
                                        : 'border-sand-200 hover:border-sand-300'"
                                      class="flex items-start gap-3 rounded-lg border px-3 py-2.5 transition h-full">
                                    <span :class="f.leave_type === @js($code) ? 'border-maroon-700' : 'border-sand-300'"
                                          class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2">
                                        <span x-show="f.leave_type === @js($code)" class="h-2 w-2 rounded-full bg-maroon-700"></span>
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block text-sm font-medium text-sand-800">{{ $type['label'] }}</span>
                                        @if ($type['citation'])
                                            <span class="block text-[11px] text-sand-400 leading-snug">{{ $type['citation'] }}</span>
                                        @endif
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                @endforeach

                <div x-show="f.leave_type === 'OTHERS'" x-cloak>
                    <label for="others_specify" class="label label-required">Specify</label>
                    <input id="others_specify" name="others_specify" type="text" maxlength="100" x-model="f.others_specify"
                           class="input" :class="err('others_specify') && 'input-error'" placeholder="What the leave is for">
                    <span class="error-text" x-show="err('others_specify')" x-text="err('others_specify')"></span>
                </div>

                <span class="error-text" x-show="err('leave_type')" x-text="err('leave_type')"></span>

                <div x-show="type" x-cloak class="rounded-lg bg-sand-50 border border-sand-200 px-4 py-3 text-sm text-sand-700 flex gap-2.5">
                    <x-heroicon-o-book-open class="w-4 h-4 text-sand-400 shrink-0 mt-0.5" />
                    <p><span class="font-medium" x-text="type?.label"></span> — <span x-text="type?.instruction"></span></p>
                </div>
            </div>
        </section>

        {{-- 6.B --}}
        <section class="card" x-show="type" x-cloak>
            <div class="card-header">
                <h3 class="card-title">6.B · Details of leave</h3>
            </div>
            <div class="p-5 space-y-4">

                {{-- Vacation / Special Privilege --}}
                <div x-show="type?.detail === 'location'" class="space-y-3">
                    <p class="text-sm text-sand-600">Where will you spend the leave?</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach (LeaveTypes::LOCATIONS as $value => $label)
                            <label class="cursor-pointer">
                                <input type="radio" name="details[location]" value="{{ $value }}" x-model="f.location" class="sr-only">
                                <span :class="f.location === @js($value) ? 'border-maroon-700 bg-maroon-50 text-maroon-900' : 'border-sand-200 text-sand-700 hover:border-sand-300'"
                                      class="inline-block rounded-lg border px-3 py-2 text-sm transition">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    <span class="error-text" x-show="err('details.location')" x-text="err('details.location')"></span>
                    <div x-show="f.location">
                        <label for="location_specify" class="label" :class="f.location === 'abroad' && 'label-required'"
                               x-text="f.location === 'abroad' ? 'Country' : 'Place (optional)'"></label>
                        <input id="location_specify" name="details[location_specify]" type="text" maxlength="100"
                               x-model="f.location_specify" class="input" :class="err('details.location_specify') && 'input-error'"
                               :placeholder="f.location === 'abroad' ? 'e.g. Japan' : 'e.g. Baguio City'">
                        <span class="error-text" x-show="err('details.location_specify')" x-text="err('details.location_specify')"></span>
                    </div>
                </div>

                {{-- Sick leave --}}
                <div x-show="type?.detail === 'sickness'" class="space-y-3">
                    <div class="flex flex-wrap gap-2">
                        @foreach (LeaveTypes::SICKNESS as $value => $label)
                            <label class="cursor-pointer">
                                <input type="radio" name="details[sickness]" value="{{ $value }}" x-model="f.sickness" class="sr-only">
                                <span :class="f.sickness === @js($value) ? 'border-maroon-700 bg-maroon-50 text-maroon-900' : 'border-sand-200 text-sand-700 hover:border-sand-300'"
                                      class="inline-block rounded-lg border px-3 py-2 text-sm transition">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    <span class="error-text" x-show="err('details.sickness')" x-text="err('details.sickness')"></span>
                    <div>
                        <label for="illness" class="label label-required">Specify illness</label>
                        <input id="illness" name="details[illness]" type="text" maxlength="200" x-model="f.illness"
                               class="input" :class="err('details.illness') && 'input-error'">
                        <span class="error-text" x-show="err('details.illness')" x-text="err('details.illness')"></span>
                    </div>
                </div>

                {{-- Special leave benefits for women --}}
                <div x-show="type?.detail === 'women'">
                    <label for="women_illness" class="label label-required">Specify illness</label>
                    <input id="women_illness" name="details[women_illness]" type="text" maxlength="200" x-model="f.women_illness"
                           class="input" :class="err('details.women_illness') && 'input-error'"
                           placeholder="The gynecological disorder or surgery">
                    <span class="error-text" x-show="err('details.women_illness')" x-text="err('details.women_illness')"></span>
                </div>

                {{-- Study leave --}}
                <div x-show="type?.detail === 'study'" class="space-y-2">
                    <div class="flex flex-wrap gap-2">
                        @foreach (LeaveTypes::STUDY as $value => $label)
                            <label class="cursor-pointer">
                                <input type="radio" name="details[study]" value="{{ $value }}" x-model="f.study" class="sr-only">
                                <span :class="f.study === @js($value) ? 'border-maroon-700 bg-maroon-50 text-maroon-900' : 'border-sand-200 text-sand-700 hover:border-sand-300'"
                                      class="inline-block rounded-lg border px-3 py-2 text-sm transition">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    <span class="error-text" x-show="err('details.study')" x-text="err('details.study')"></span>
                </div>

                {{-- Calamity leave --}}
                <div x-show="f.leave_type === 'SEL'" class="sm:max-w-xs">
                    <label for="calamity_date" class="label label-required">Date the calamity occurred</label>
                    <input id="calamity_date" name="details[calamity_date]" type="date" x-model="f.calamity_date"
                           max="{{ now()->toDateString() }}" class="input" :class="err('details.calamity_date') && 'input-error'">
                    <span class="hint" x-show="! err('details.calamity_date')">The leave is taken within thirty days of it. Not printed on the form.</span>
                    <span class="error-text" x-show="err('details.calamity_date')" x-text="err('details.calamity_date')"></span>
                </div>

                <p x-show="! type?.detail && f.leave_type !== 'SEL'" class="text-sm text-sand-500">
                    Nothing to add here for this type of leave.
                </p>
            </div>
        </section>

        {{-- 6.C --}}
        <section class="card" x-show="type" x-cloak>
            <div class="card-header">
                <h3 class="card-title" x-text="type?.daysOnly ? '6.C · Number of days' : '6.C · Number of working days and inclusive dates'"></h3>
            </div>
            <div class="p-5">
                <div x-show="! type?.daysOnly" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-start">
                    <div>
                        <label for="date_from" class="label label-required">From</label>
                        <input id="date_from" name="date_from" type="date" x-model="f.date_from"
                               class="input" :class="err('date_from') && 'input-error'">
                    </div>
                    <div>
                        <label for="date_to" class="label label-required">To</label>
                        <input id="date_to" name="date_to" type="date" x-model="f.date_to" :min="f.date_from"
                               class="input" :class="err('date_to') && 'input-error'">
                    </div>
                    <div class="rounded-lg bg-sand-50 border border-sand-200 px-4 py-2.5">
                        <p class="section-label" x-text="type?.calendar ? 'Days' : 'Working days'"></p>
                        <p class="text-xl font-bold text-sand-800 tabular" x-text="result.days ? number(result.days) : '—'"></p>
                    </div>
                    <div class="sm:col-span-3 -mt-2">
                        <span class="error-text" x-show="err('date_from')" x-text="err('date_from')"></span>
                        <span class="error-text" x-show="err('date_to')" x-text="err('date_to')"></span>
                        <span class="hint" x-show="! err('date_from') && ! err('date_to') && ! type?.calendar">Weekends are not counted.</span>
                    </div>
                </div>

                <div x-show="type?.daysOnly" class="sm:max-w-xs">
                    <label for="days" class="label label-required">Number of days</label>
                    <input id="days" name="days" type="number" step="0.5" min="0" max="999" x-model="f.days"
                           class="input" :class="err('days') && 'input-error'">
                    <span class="error-text" x-show="err('days')" x-text="err('days')"></span>
                </div>
            </div>
        </section>

        {{-- 6.D --}}
        <section class="card" x-show="type" x-cloak>
            <div class="card-header">
                <h3 class="card-title">6.D · Commutation</h3>
            </div>
            <div class="p-5">
                <div class="flex flex-wrap gap-2">
                    @foreach (LeaveTypes::COMMUTATION as $value => $label)
                        <label class="cursor-pointer">
                            <input type="radio" name="commutation" value="{{ $value }}" x-model="f.commutation" class="sr-only">
                            <span :class="f.commutation === @js($value) ? 'border-maroon-700 bg-maroon-50 text-maroon-900' : 'border-sand-200 text-sand-700 hover:border-sand-300'"
                                  class="inline-block rounded-lg border px-3 py-2 text-sm transition">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                <span class="error-text" x-show="err('commutation')" x-text="err('commutation')"></span>
            </div>
        </section>

        {{-- Supporting documents --}}
        <section class="card" x-show="type" x-cloak>
            <div class="card-header">
                <div>
                    <h3 class="card-title">Supporting documents</h3>
                    <p class="text-xs text-sand-400 mt-0.5">PDF, JPG or PNG · up to five files, 10 MB each. Only you and your reviewers can open them.</p>
                </div>
            </div>
            <div class="p-5 space-y-4">
                <template x-if="result.documents.length">
                    <div class="rounded-lg border border-sand-200 bg-sand-50 px-4 py-3">
                        <p class="text-xs font-semibold text-sand-700 mb-1.5">This leave needs</p>
                        <ul class="list-disc pl-4 space-y-1 text-xs text-sand-600">
                            <template x-for="doc in result.documents" :key="doc">
                                <li x-text="doc"></li>
                            </template>
                        </ul>
                    </div>
                </template>

                @if ($existing)
                    <ul class="divide-y divide-sand-100 rounded-lg border border-sand-200">
                        @foreach ($existing as $i => $file)
                            <li class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                                <a href="{{ route('leave.attachment', [$application, $i]) }}" target="_blank"
                                   class="truncate text-maroon-700 hover:text-maroon-900">{{ $file['name'] }}</a>
                                <label class="flex items-center gap-1.5 text-xs text-sand-500 shrink-0">
                                    <input type="checkbox" name="remove_attachments[]" value="{{ $i }}" x-model="removing">
                                    Remove
                                </label>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <div>
                    <input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png"
                           @change="newFiles = $event.target.files.length"
                           class="file-input file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-maroon-50 file:text-maroon-800 hover:file:bg-maroon-100 file:cursor-pointer">
                    @foreach (['attachments', 'attachments.0', 'attachments.1', 'attachments.2', 'attachments.3', 'attachments.4'] as $key)
                        @error($key) <span class="error-text">{{ $message }}</span> @enderror
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Note --}}
        <section class="card" x-show="type" x-cloak>
            <div class="card-header">
                <div>
                    <h3 class="card-title">Note to your reviewers <span class="text-sand-400 font-normal">(optional)</span></h3>
                    <p class="text-xs text-sand-400 mt-0.5">Shown to the reviewers with your form. Not printed on it.</p>
                </div>
            </div>
            <div class="p-5">
                <textarea name="reason" rows="2" maxlength="500" class="textarea"
                          placeholder="Anything the reviewers should know">{{ $old('reason') }}</textarea>
                @error('reason') <span class="error-text">{{ $message }}</span> @enderror
            </div>
        </section>
    </div>

    {{-- ============================================================
         The check, and sending it
         ============================================================ --}}
    <aside class="space-y-4 lg:sticky lg:top-6">
        <div class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-sand-100 flex items-center justify-between gap-2">
                <h3 class="font-semibold text-sm text-sand-700">Checked against page 2</h3>
                <span x-show="checking" x-cloak class="text-[11px] text-sand-400">Checking…</span>
            </div>
            <div class="p-5 space-y-3 text-sm">
                <p x-show="! type" class="text-sand-500">Choose a type of leave to start.</p>

                <template x-if="type && errorList().length">
                    <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2.5">
                        <p class="text-xs font-semibold text-red-800 mb-1">Fix before sending</p>
                        <ul class="space-y-1 text-xs text-red-700 list-disc pl-4">
                            <template x-for="message in errorList()" :key="message">
                                <li x-text="message"></li>
                            </template>
                        </ul>
                    </div>
                </template>

                <template x-if="type && result.warnings.length">
                    <div class="rounded-lg border border-gold-200 bg-gold-50 px-3 py-2.5">
                        <p class="text-xs font-semibold text-gold-900 mb-1">Please note</p>
                        <ul class="space-y-1 text-xs text-gold-900 list-disc pl-4">
                            <template x-for="message in result.warnings" :key="message">
                                <li x-text="message"></li>
                            </template>
                        </ul>
                    </div>
                </template>

                <template x-if="type && ready && ! errorList().length && ! result.warnings.length">
                    <div class="rounded-lg border border-forest-200 bg-forest-50 px-3 py-2.5 text-xs text-forest-800 flex gap-2">
                        <x-heroicon-o-check-circle class="w-4 h-4 shrink-0" />
                        <span>Everything matches the form's instructions.</span>
                    </div>
                </template>

                <dl class="grid grid-cols-3 gap-2 pt-1 text-center">
                    <div class="rounded-lg bg-sand-50 py-2">
                        <dt class="section-label">VL</dt>
                        <dd class="font-bold text-sand-800 tabular">{{ number_format((float) ($balance->vl_balance ?? 0), 3) }}</dd>
                    </div>
                    <div class="rounded-lg bg-sand-50 py-2">
                        <dt class="section-label">SL</dt>
                        <dd class="font-bold text-sand-800 tabular">{{ number_format((float) ($balance->sl_balance ?? 0), 3) }}</dd>
                    </div>
                    <div class="rounded-lg bg-sand-50 py-2">
                        <dt class="section-label">Service</dt>
                        <dd class="font-bold text-sand-800 tabular">{{ number_format((float) ($balance->service_balance ?? 0), 3) }}</dd>
                    </div>
                </dl>
                <p class="text-[11px] text-sand-400 leading-relaxed">
                    HR certifies these on the form (7.A) when they approve.
                </p>
            </div>
        </div>

        <div class="card p-5 space-y-3">
            <p class="text-xs text-sand-500 leading-relaxed">
                It goes to the
                @foreach ($chainLabels as $label)
                    <strong>{{ $label }}</strong>@if (! $loop->last), then the @else.@endif
                @endforeach
                @if ($dean)
                    {{ $dean }} is named under 7.B.
                @endif
                Each decision is printed on the form as it is made.
            </p>

            <button type="submit" formaction="{{ route('leave.apply.preview') }}" formtarget="_blank"
                    formnovalidate :disabled="! f.leave_type"
                    class="btn btn-md btn-secondary w-full">
                <x-heroicon-o-document-magnifying-glass class="w-4 h-4" />
                Preview the printed form
            </button>

            <button type="submit" formaction="{{ $editing ? route('leave.update', $application) : route('leave.apply.store') }}"
                    formtarget="_self" :disabled="! f.leave_type"
                    class="btn btn-lg btn-primary w-full">
                {{ $editing ? 'Resubmit to the ' . ($chainLabels[0] ?? 'reviewer') : 'Submit to the ' . ($chainLabels[0] ?? 'reviewer') }}
            </button>
        </div>
    </aside>
</form>

<script>
    function leaveForm(initial, types, serverErrors) {
        return {
            f: initial,
            types,
            serverErrors,
            touched: {},
            result: { errors: {}, warnings: [], documents: [], days: 0 },
            ready: false,
            checking: false,
            removing: [],
            newFiles: 0,
            existing: {{ count($existing) }},
            pending: null,

            get type() {
                return this.types[this.f.leave_type] ?? null;
            },

            // Once a field is changed, its error from the last submission
            // gives way to the live check.
            touch(event) {
                const name = event.target?.name;

                if (name) {
                    this.touched[name.replace(/\[\]$/, '').replace(/\[(\w+)\]/g, '.$1')] = true;
                }
            },

            start() {
                if (this.f.leave_type) {
                    this.check();
                }
            },

            // A server-side error stands until the field it is about changes;
            // the live check speaks for the rest.
            err(key) {
                if (this.serverErrors[key] && ! this.touched[key]) {
                    return this.serverErrors[key][0];
                }

                return this.ready ? (this.result.errors[key] ?? null) : null;
            },

            errorList() {
                const keys = new Set([...Object.keys(this.result.errors), ...Object.keys(this.serverErrors)]);

                return [...keys].map((key) => this.err(key)).filter(Boolean);
            },

            number(value) {
                return Number(value).toFixed(2).replace(/\.?0+$/, '');
            },

            async check() {
                if (! this.f.leave_type) {
                    return;
                }

                const form = this.$root;
                const data = new FormData(form);
                data.delete('attachments[]');
                data.delete('_method');
                data.set('has_attachments', (this.existing - this.removing.length + this.newFiles) > 0 ? '1' : '0');

                this.pending?.abort();
                this.pending = new AbortController();
                this.checking = true;

                try {
                    const response = await fetch(@js(route('leave.apply.check')), {
                        method: 'POST',
                        body: data,
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        signal: this.pending.signal,
                    });

                    if (response.ok) {
                        this.result = await response.json();
                        this.ready = true;
                    }
                } catch (e) {
                    // Superseded by a newer check, or offline: the server
                    // checks again on submit either way.
                } finally {
                    this.checking = false;
                }
            },
        };
    }
</script>
@endsection
