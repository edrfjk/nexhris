@extends('layouts.app')
@section('title', 'Leave Reviews')

@section('content')

<x-page-header
    title="Leave Reviews"
    :subtitle="$stageLabel ? 'Forms waiting on your signature as ' . $stageLabel : 'Leave approval queue'" />

{{-- ------------------------------------------------------------------
     Where this reviewer sits in the chain
     ------------------------------------------------------------------ --}}
<div class="card mb-6 p-5">
    <p class="section-label mb-3">Approval chain</p>

    <div class="flex flex-wrap items-center gap-2 text-sm">
        @foreach (\App\Services\LeaveChain::LABELS as $key => $label)
            <span @class([
                'px-3 py-1.5 rounded-lg font-medium ring-1',
                'bg-maroon-50 text-maroon-800 ring-maroon-200' => $key === $stage,
                'bg-sand-50 text-sand-500 ring-sand-200' => $key !== $stage,
            ])>
                {{ $label }}
                @if ($key === $stage)
                    <span class="ml-1 text-[10px] uppercase tracking-wide">· you</span>
                @endif
            </span>

            @unless ($loop->last)
                <x-heroicon-o-chevron-right class="w-4 h-4 text-sand-300" />
            @endunless
        @endforeach
    </div>

    <p class="mt-3 text-xs text-sand-500 leading-relaxed">
        Employees upload their filled-in leave form here first. Reviewing it online means nobody prints
        and chases signatures for a form that was going to be sent back.
        @if ($stage === 'dean')
            You see only employees registered under your program.
        @elseif ($stage === 'hr')
            Once the Campus Director approves, you post the leave to the employee's ledger card.
        @endif
    </p>
</div>

{{-- ------------------------------------------------------------------
     Filters
     ------------------------------------------------------------------ --}}
@php
    $typeLabels = ['VL' => 'Vacation Leave', 'SL' => 'Sick Leave'];
    $allDepartments = $colleges->pluck('activeDepartments')->flatten();

    $chips = [
        ['key' => 'search', 'label' => 'Search', 'value' => request('search')],
        ['key' => 'type', 'label' => 'Type', 'value' => $typeLabels[request('type')] ?? null],
        ['key' => 'college', 'label' => 'College',
         'value' => $colleges->firstWhere('id', (int) request('college'))?->name],
        ['key' => 'department', 'label' => 'Department',
         'value' => $allDepartments->firstWhere('id', (int) request('department'))?->name],
        ['key' => 'sort', 'label' => 'Sorted', 'value' => request('sort') === 'newest' ? 'Newest first' : null],
    ];
@endphp

<x-filter-bar :chips="$chips" :clear="route('admin.leave.review.index')">

    <x-filter-field label="Search" :span="$colleges->isNotEmpty() ? 1 : 2">
        <x-filter-search placeholder="Employee name or number" />
    </x-filter-field>

    <x-filter-field label="Leave type">
        <select name="type" class="select">
            <option value="">All leave types</option>
            @foreach ($typeLabels as $code => $label)
                <option value="{{ $code }}" @selected(request('type') === $code)>{{ $label }}</option>
            @endforeach
        </select>
    </x-filter-field>

    @if ($colleges->isNotEmpty())
        <x-filter-field label="College / Office">
            <select name="college" id="review-college" class="select">
                <option value="">All colleges / offices</option>
                @foreach ($colleges as $college)
                    <option value="{{ $college->id }}" @selected((string) request('college') === (string) $college->id)>
                        {{ $college->code }} — {{ $college->name }}
                    </option>
                @endforeach
            </select>
        </x-filter-field>

        <x-filter-field label="Department">
            <select name="department" id="review-department" class="select">
                <option value="">All departments</option>
            </select>
        </x-filter-field>
    @endif

    <x-filter-field label="Order" hint="Oldest first is the order an approval queue is worked in.">
        <select name="sort" class="select">
            <option value="">Longest waiting first</option>
            <option value="newest" @selected(request('sort') === 'newest')>Newest first</option>
        </select>
    </x-filter-field>

</x-filter-bar>

{{-- ------------------------------------------------------------------
     Queue
     ------------------------------------------------------------------ --}}
<div class="card overflow-hidden">
    <div class="px-5 py-3.5 border-b border-sand-100 flex items-center justify-between">
        <h3 class="font-semibold text-sm text-sand-700">Waiting on you</h3>
        <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-gold-50 text-gold-700 ring-1 ring-gold-100">
            {{ $applications->total() }} form{{ $applications->total() === 1 ? '' : 's' }}
        </span>
    </div>

    @if ($applications->isEmpty())
        <x-empty-state message="Nothing is waiting on your review right now." />
    @else
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th class="text-left">Employee</th>
                        <th class="text-left">Leave</th>
                        <th class="text-left">Inclusive dates</th>
                        <th class="text-center">Days</th>
                        <th class="text-left">Waiting</th>
                        <th class="text-left hidden lg:table-cell">Progress</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($applications as $application)
                        <tr class="hover:bg-sand-50/70 transition">
                            <td>
                                <p class="font-semibold text-sand-800">{{ $application->user->name }}</p>
                                <p class="text-xs text-sand-400">
                                    {{ $application->user->employee_number ?: '—' }} · {{ $application->user->orgLine() }}
                                </p>
                            </td>
                            <td>
                                <x-badge :color="$application->leave_type === 'VL' ? 'blue' : 'purple'">
                                    {{ $application->leave_type === 'VL' ? 'Vacation' : 'Sick' }}
                                </x-badge>
                            </td>
                            <td class="whitespace-nowrap">
                                {{ $application->date_from?->format('M j, Y') }}
                                @if ($application->date_to && ! $application->date_to->eq($application->date_from))
                                    <span class="text-sand-300">→</span>
                                    {{ $application->date_to->format('M j, Y') }}
                                @endif
                            </td>
                            <td class="text-center">
                                {{ rtrim(rtrim(number_format((float) $application->days, 2), '0'), '.') }}
                            </td>
                            @php $waiting = $application->daysWaiting(); @endphp
                            <td class="whitespace-nowrap">
                                @if ($waiting === null)
                                    <span class="text-sand-300">—</span>
                                @else
                                    {{-- A form sitting for a fortnight is the one that gets
                                         chased in person, so it has to be visible at a glance. --}}
                                    <span @class([
                                        'inline-flex items-center gap-1 text-xs font-medium px-2 py-1 rounded-full ring-1',
                                        'bg-red-50 text-red-700 ring-red-100' => $waiting >= 14,
                                        'bg-gold-50 text-gold-700 ring-gold-100' => $waiting >= 5 && $waiting < 14,
                                        'bg-sand-50 text-sand-600 ring-sand-100' => $waiting < 5,
                                    ])>
                                        @if ($waiting >= 14)
                                            <x-heroicon-o-exclamation-triangle class="w-3.5 h-3.5" />
                                        @endif
                                        {{ $waiting === 0 ? 'Today' : $waiting . ' day' . ($waiting === 1 ? '' : 's') }}
                                    </span>
                                @endif
                            </td>
                            <td class="hidden lg:table-cell w-52">
                                <x-leave.stepper :application="$application" compact />
                            </td>
                            <td class="text-right">
                                <a href="{{ route('admin.leave.review.show', $application) }}"
                                   class="btn btn-sm btn-primary">
                                    Review
                                    <x-heroicon-o-chevron-right class="w-3.5 h-3.5" />
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="px-5 py-3 border-t border-sand-100">
            {{ $applications->links() }}
        </div>
    @endif
</div>

{{-- ------------------------------------------------------------------
     Recently handled by this reviewer
     ------------------------------------------------------------------ --}}
@if ($recent->isNotEmpty())
    <div class="card mt-6 overflow-hidden">
        <div class="px-5 py-3.5 border-b border-sand-100">
            <h3 class="font-semibold text-sm text-sand-700">Recently reviewed by you</h3>
        </div>
        <ul class="divide-y divide-sand-100">
            @foreach ($recent as $application)
                <li class="px-5 py-3 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-sand-800 truncate">{{ $application->user->name }}</p>
                        <p class="text-xs text-sand-400">
                            {{ $application->leave_type === 'VL' ? 'Vacation' : 'Sick' }} leave ·
                            {{ $application->date_from?->format('M j, Y') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3 flex-shrink-0">
                        <x-leave.status-pill :application="$application" />
                        <a href="{{ route('admin.leave.review.show', $application) }}"
                           class="text-xs font-medium text-maroon-700 hover:text-maroon-900">View</a>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
@endif

@if ($colleges->isNotEmpty())
<script>
(function () {
    const byCollege = @json($colleges->mapWithKeys(fn ($c) => [
        $c->id => $c->activeDepartments->map(fn ($d) => ['id' => $d->id, 'label' => $d->name])->values(),
    ]));
    const collegeSelect = document.getElementById('review-college');
    const departmentSelect = document.getElementById('review-department');
    const preselected = @json(request('department'));

    function populate(collegeId, keepId) {
        departmentSelect.innerHTML = '<option value="">All departments</option>';

        const list = collegeId
            ? (byCollege[collegeId] || [])
            : Object.values(byCollege).flat();

        list.forEach(function (d) {
            const option = document.createElement('option');
            option.value = d.id;
            option.textContent = d.label;
            if (String(d.id) === String(keepId)) option.selected = true;
            departmentSelect.appendChild(option);
        });

        departmentSelect.disabled = list.length === 0;
    }

    populate(collegeSelect.value, preselected);
    collegeSelect.addEventListener('change', function () { populate(this.value, null); });
})();
</script>
@endif

@endsection
