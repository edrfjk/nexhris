@extends('layouts.app')
@section('title', 'Leave Ledger Cards')

@section('content')

@php $me = auth()->user(); @endphp

<x-page-header
    title="Leave Ledger Cards"
    :subtitle="$me->isDean()
        ? 'Employees registered under your program'
        : 'Employee leave balances, credits and ledger cards'">
    <x-slot:actions>
        <a href="{{ route('admin.leave.calendar') }}"
           class="btn btn-md btn-secondary">
            <x-heroicon-o-calendar-days class="w-4 h-4" />
            Calendar
        </a>

        <a href="{{ route('admin.leave.export.pdf') }}" target="_blank"
           class="btn btn-md btn-secondary">PDF</a>

        <a href="{{ route('admin.leave.export.excel') }}"
           class="btn btn-md btn-secondary">Excel</a>

        <a href="{{ route('admin.leave.review.index') }}"
           class="btn btn-md btn-primary relative">
            Leave Reviews
            @if ($pendingCount > 0)
                <span class="absolute -top-2 -right-2 min-w-[1.25rem] h-5 px-1 rounded-full bg-ispscgold
                             text-maroon-900 text-[10px] font-bold flex items-center justify-center">
                    {{ $pendingCount }}
                </span>
            @endif
        </a>
    </x-slot:actions>
</x-page-header>

{{-- ------------------------------------------------------------------
     HR-only: what still needs posting, and the published form
     ------------------------------------------------------------------ --}}
@if ($me->isAdmin())
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">

        <div @class([
            'rounded border p-5 shadow-soft',
            'bg-gold-50 border-gold-200' => $awaitingPosting > 0,
            'bg-white border-sand-200' => $awaitingPosting === 0,
        ])>
            <p class="section-label">
                Approved, awaiting ledger posting
            </p>
            <p class="text-3xl font-bold mt-1 {{ $awaitingPosting > 0 ? 'text-gold-800' : 'text-sand-800' }}">
                {{ $awaitingPosting }}
            </p>
            <p class="text-xs text-sand-500 mt-1 leading-relaxed">
                Forms the Campus Director has approved. Record the days and credits used
                so the employee's ledger card stays current.
            </p>
            @if ($awaitingPosting > 0)
                <a href="{{ route('admin.leave.review.index') }}"
                   class="mt-3 inline-block text-xs font-semibold text-gold-800 hover:underline">
                    Post them now →
                </a>
            @endif
        </div>

        <div class="card p-5">
            <p class="section-label">
                Published leave form
            </p>
            @if ($activeTemplate)
                <p class="text-sm font-semibold text-sand-800 mt-1.5">{{ $activeTemplate->label }}</p>
                <p class="text-xs text-sand-400 mt-0.5">
                    {{ $activeTemplate->original_filename }} ·
                    uploaded {{ $activeTemplate->created_at->format('M j, Y') }}
                </p>
            @else
                <p class="text-sm text-sand-500 mt-1.5">
                    No form published — employees fall back to the bundled default.
                </p>
            @endif
            <a href="{{ route('admin.leave.templates.index') }}"
               class="mt-3 inline-block text-xs font-semibold text-maroon-700 hover:underline">
                Manage templates →
            </a>
        </div>
    </div>

    {{-- ---------- Bulk monthly accrual ---------- --}}
    <div x-data="{ open: false }" class="card mb-6 overflow-hidden">
        <button @click="open = !open"
                class="w-full px-5 py-4 flex items-center justify-between text-left hover:bg-sand-50 transition">
            <div>
                <p class="text-sm font-semibold text-sand-800">Post monthly credits to all employees</p>
                <p class="text-xs text-sand-500 mt-0.5">
                    The standard 1.25 VL and 1.25 SL accrual, applied in one go.
                </p>
            </div>
            <x-heroicon-o-chevron-down class="w-4 h-4 text-sand-400 transition-transform" />
        </button>

        <form method="POST" action="{{ route('admin.leave.bulk-earned.store') }}"
              x-show="open" x-cloak class="px-5 pb-5 pt-1 border-t border-sand-100">
            @csrf
            {{-- Monthly accrual belongs to the leave card. Service credits are
                 earned per event and are recorded on an employee's own card. --}}
            <input type="hidden" name="ledger" value="leave">
            <div class="grid grid-cols-2 lg:grid-cols-6 gap-3 items-end">
                <label class="block">
                    <span class="label">Period from</span>
                    <input type="date" name="period_from" required value="{{ now()->startOfMonth()->format('Y-m-d') }}"
                           class="input mt-1">
                </label>
                <label class="block">
                    <span class="label">Period to</span>
                    <input type="date" name="period_to" required value="{{ now()->endOfMonth()->format('Y-m-d') }}"
                           class="input mt-1">
                </label>
                <label class="block">
                    <span class="label">VL earned</span>
                    <input type="number" step="0.01" min="0" name="vl_earned" value="1.25"
                           class="input mt-1">
                </label>
                <label class="block">
                    <span class="label">SL earned</span>
                    <input type="number" step="0.01" min="0" name="sl_earned" value="1.25"
                           class="input mt-1">
                </label>

                <button class="btn btn-md btn-primary">
                    Post to all
                </button>
            </div>
            <label class="block mt-3">
                <span class="label">Remarks</span>
                <input type="text" name="remarks" maxlength="255" placeholder="e.g. Monthly accrual for {{ now()->format('F Y') }}"
                       class="input mt-1">
            </label>
        </form>
    </div>
@endif

{{-- ------------------------------------------------------------------
     Filters
     ------------------------------------------------------------------ --}}
@php
    $allDepartments = $colleges->pluck('activeDepartments')->flatten();

    $chips = [
        ['key' => 'search', 'label' => 'Search', 'value' => request('search')],
        ['key' => 'college', 'label' => 'College',
         'value' => $colleges->firstWhere('id', (int) request('college'))?->name],
        ['key' => 'department', 'label' => 'Department',
         'value' => $allDepartments->firstWhere('id', (int) request('department'))?->name],
        ['key' => 'sort', 'label' => 'Sorted',
         'value' => request('sort') === 'low_balance' ? 'Lowest balance first' : null],
    ];
@endphp

<x-filter-bar :chips="$chips" :clear="route('admin.leave.index')">

    <x-filter-field label="Search">
        <x-filter-search placeholder="Name or employee number" />
    </x-filter-field>

    <x-filter-field label="College / Office">
        <select name="college" id="ledger-college" class="select">
            <option value="">All colleges / offices</option>
            @foreach ($colleges as $college)
                <option value="{{ $college->id }}" @selected((string) request('college') === (string) $college->id)>
                    {{ $college->code }} — {{ $college->name }}
                </option>
            @endforeach
        </select>
    </x-filter-field>

    <x-filter-field label="Department">
        <select name="department" id="ledger-department" class="select">
            <option value="">All departments</option>
        </select>
    </x-filter-field>

    <x-filter-field label="Order" hint="Lowest balance first surfaces who is close to running out.">
        <select name="sort" class="select">
            <option value="">Name (A–Z)</option>
            <option value="low_balance" @selected(request('sort') === 'low_balance')>Lowest balance first</option>
        </select>
    </x-filter-field>

</x-filter-bar>

{{-- ------------------------------------------------------------------
     Employees
     ------------------------------------------------------------------ --}}
<div class="card overflow-hidden">
    @if ($employees->isEmpty())
        <x-empty-state message="No employees match this filter." />
    @else
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th class="text-left">Employee</th>
                        <th class="text-left hidden md:table-cell">College / Office</th>
                        <th class="text-center">VL</th>
                        <th class="text-center">SL</th>
                        <th class="text-center">Service</th>
                        <th class="text-right">Ledger card</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($employees as $employee)
                        @php
                            $vl = (float) ($employee->leaveBalance->vl_balance ?? 0);
                            $sl = (float) ($employee->leaveBalance->sl_balance ?? 0);
                            $sc = (float) ($employee->leaveBalance->service_balance ?? 0);
                        @endphp
                        <tr class="hover:bg-sand-50/70 transition">
                            <td>
                                <div class="flex items-center gap-2">
                                    <p class="font-semibold text-sand-800">{{ $employee->name }}</p>
                                    @unless ($employee->isEmployee())
                                        <x-badge color="maroon">{{ $employee->roleLabel() }}</x-badge>
                                    @endunless
                                </div>
                                <p class="text-xs text-sand-400">{{ $employee->employee_number ?: '—' }}</p>
                            </td>
                            <td class="hidden md:table-cell">
                                <p class="text-sand-700">{{ $employee->collegeName() ?: '—' }}</p>
                                @if ($employee->departmentName())
                                    <p class="text-xs text-sand-400">{{ $employee->departmentName() }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-center font-semibold {{ $vl < 5 ? 'text-red-600' : 'text-sand-700' }}">
                                {{ number_format($vl, 2) }}
                            </td>
                            <td class="px-5 py-3.5 text-center font-semibold {{ $sl < 5 ? 'text-red-600' : 'text-sand-700' }}">
                                {{ number_format($sl, 2) }}
                            </td>
                            <td class="text-center">{{ number_format($sc, 2) }}</td>
                            <td class="text-right whitespace-nowrap">
                                <a href="{{ route('admin.leave.ledger', $employee) }}"
                                   class="btn btn-sm btn-secondary">
                                    Open
                                </a>
                                <a href="{{ route('admin.leave.ledger.pdf', $employee) }}" target="_blank"
                                   class="btn btn-sm btn-primary ml-1">
                                    PDF
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="px-5 py-3 border-t border-sand-100">
            {{ $employees->links() }}
        </div>
    @endif
</div>

{{-- Narrow the department list to the chosen college, so HR never scrolls
     past another college's programmes. --}}
<script>
(function () {
    const byCollege = @json($colleges->mapWithKeys(fn ($c) => [
        $c->id => $c->activeDepartments->map(fn ($d) => ['id' => $d->id, 'label' => $d->name])->values(),
    ]));
    const collegeSelect = document.getElementById('ledger-college');
    const departmentSelect = document.getElementById('ledger-department');
    const preselected = @json(request('department'));

    function populate(collegeId, keepId) {
        departmentSelect.innerHTML = '<option value="">All departments</option>';

        // With no college picked, every department is a valid choice.
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

@endsection
