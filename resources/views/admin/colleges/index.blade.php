@extends('layouts.app')
@section('title', 'Colleges & Offices')

@section('content')

<x-page-header
    title="Colleges &amp; Offices"
    subtitle="The college decides which Dean signs a leave form. Departments group people inside it.">
    <x-slot:actions>
        <button type="button" class="btn btn-md btn-primary"
                onclick="document.getElementById('add-college').showModal()">
            <x-heroicon-o-plus />
            Add College
        </button>
    </x-slot:actions>
</x-page-header>

{{-- ------------------------------------------------------------------
     Org summary
     ------------------------------------------------------------------ --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
    <x-stat-card label="Colleges & Offices" :value="$colleges->count()" icon="building-library" />

    <x-stat-card label="Departments" :value="$totalDepartments" color="blue" icon="rectangle-group" />

    <x-stat-card label="Without a Dean"
                 :value="$colleges->whereNull('dean_id')->count()"
                 :color="$colleges->whereNull('dean_id')->count() > 0 ? 'amber' : 'gray'"
                 icon="user-minus" />

    <x-stat-card label="Staff Unassigned" :value="$unassigned"
                 :color="$unassigned > 0 ? 'red' : 'gray'"
                 hint="no college set" icon="exclamation-triangle"
                 :href="route('admin.employees.index')" />
</div>

@if ($unassigned > 0)
    <div class="alert alert-warning mb-4">
        <x-heroicon-o-exclamation-triangle />
        <div>
            <p class="font-medium">{{ $unassigned }} account{{ $unassigned === 1 ? ' is' : 's are' }} not assigned to a college</p>
            <p class="text-[13px] mt-0.5">
                Leave filed by an unassigned employee has no Dean to route to.
                <a href="{{ route('admin.employees.index') }}" class="underline underline-offset-2">Assign them &rarr;</a>
            </p>
        </div>
    </div>
@endif

@if ($withoutDepartment > 0)
    <div class="alert alert-info mb-4">
        <x-heroicon-o-information-circle />
        <span>
            {{ $withoutDepartment }} {{ $withoutDepartment === 1 ? 'person has' : 'people have' }}
            a college but no department yet. Leave approval still works &mdash; a department is
            for grouping and reporting only.
        </span>
    </div>
@endif

<form method="GET" class="toolbar mb-4">
    <x-heroicon-o-magnifying-glass class="w-4 h-4 text-sand-400" />
    <input type="text" name="search" value="{{ request('search') }}"
           placeholder="Search by name or code…" class="input input-sm flex-1 min-w-[200px]">
    <select name="status" class="select select-sm w-auto">
        <option value="">All</option>
        <option value="active" @selected(request('status') === 'active')>Active only</option>
        <option value="inactive" @selected(request('status') === 'inactive')>Inactive only</option>
    </select>
    <button class="btn btn-sm btn-primary"><x-heroicon-o-funnel />Filter</button>
    @if (request()->hasAny(['search', 'status']))
        <a href="{{ route('admin.colleges.index') }}" class="btn btn-sm btn-ghost">Clear</a>
    @endif
</form>

{{-- ------------------------------------------------------------------
     Colleges, each expanding to its departments
     ------------------------------------------------------------------ --}}
@if ($colleges->isEmpty())
    <x-card>
        <x-empty-state title="No colleges yet"
                       message="Add the colleges and administrative offices your campus is organised into."
                       icon="building-library" />
    </x-card>
@else
    <div class="space-y-3">
        @foreach ($colleges as $college)
            <div x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }" class="card overflow-hidden">

                {{-- College row --}}
                <div class="flex items-start gap-3 p-4">
                    <button type="button" @click="open = !open"
                            class="mt-0.5 shrink-0 w-6 h-6 rounded flex items-center justify-center
                                   text-sand-500 hover:bg-sand-100 transition-colors"
                            :aria-expanded="open" aria-label="Toggle departments">
                        <x-heroicon-o-chevron-right class="w-4 h-4 transition-transform"
                                                    ::class="open && 'rotate-90'" />
                    </button>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="badge badge-maroon">{{ $college->code }}</span>
                            <h2 class="text-[15px] font-semibold text-sand-900">{{ $college->name }}</h2>
                            @unless ($college->is_active)
                                <span class="badge badge-slate">Inactive</span>
                            @endunless
                        </div>

                        <div class="flex items-center gap-4 mt-1.5 text-[11px] text-sand-500 flex-wrap">
                            <span class="inline-flex items-center gap-1">
                                <x-heroicon-o-users class="w-3.5 h-3.5" />
                                {{ $college->employees_count }} {{ Str::plural('person', $college->employees_count) }}
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <x-heroicon-o-rectangle-group class="w-3.5 h-3.5" />
                                {{ $college->departments_count }} {{ Str::plural('department', $college->departments_count) }}
                            </span>
                            @if ($college->dean)
                                <span class="inline-flex items-center gap-1">
                                    <x-heroicon-o-user-circle class="w-3.5 h-3.5" />
                                    Dean: {{ $college->dean->name }}
                                </span>
                            @else
                                <span class="badge badge-amber">
                                    <x-heroicon-o-exclamation-triangle />
                                    No Dean assigned
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5 shrink-0">
                        <button type="button" class="btn btn-xs btn-secondary"
                                onclick="document.getElementById('add-dept-{{ $college->id }}').showModal()">
                            <x-heroicon-o-plus />
                            Department
                        </button>
                        <button type="button" class="btn btn-xs btn-secondary"
                                onclick="document.getElementById('edit-{{ $college->id }}').showModal()">
                            Edit
                        </button>
                        <form method="POST" action="{{ route('admin.colleges.destroy', $college) }}"
                              onsubmit="return confirm({{ $college->isDeletable()
                                  ? Js::from('Delete this empty college?')
                                  : Js::from('This college still has people or departments, so it will be deactivated instead. Continue?') }})">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-danger-soft">
                                {{ $college->isDeletable() ? 'Delete' : 'Deactivate' }}
                            </button>
                        </form>
                    </div>
                </div>

                {{-- Departments under this college --}}
                <div x-show="open" x-cloak class="border-t border-sand-200 bg-sand-50/60">
                    @if ($college->departments->isEmpty())
                        <div class="px-5 py-6 text-center">
                            <p class="text-[13px] text-sand-600">No departments under {{ $college->code }} yet.</p>
                            <button type="button" class="btn btn-sm btn-secondary mt-2.5"
                                    onclick="document.getElementById('add-dept-{{ $college->id }}').showModal()">
                                <x-heroicon-o-plus />
                                Add the first one
                            </button>
                        </div>
                    @else
                        <table class="w-full text-[13px]">
                            <thead>
                                <tr class="text-[11px] uppercase tracking-wide text-sand-500">
                                    <th class="text-left font-semibold px-5 py-2 pl-14">Code</th>
                                    <th class="text-left font-semibold px-4 py-2">Department / Programme</th>
                                    <th class="text-right font-semibold px-4 py-2">People</th>
                                    <th class="text-left font-semibold px-4 py-2 hidden md:table-cell">Head</th>
                                    <th class="px-5 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-sand-200">
                                @foreach ($college->departments as $department)
                                    <tr class="hover:bg-white/70 transition-colors">
                                        <td class="px-5 py-2.5 pl-14">
                                            <span class="badge badge-slate">{{ $department->code }}</span>
                                        </td>
                                        <td class="px-4 py-2.5">
                                            <span class="text-sand-900">{{ $department->name }}</span>
                                            @unless ($department->is_active)
                                                <span class="badge badge-slate ml-1.5">Inactive</span>
                                            @endunless
                                        </td>
                                        <td class="px-4 py-2.5 text-right tabular text-sand-700">
                                            {{ $department->employees_count }}
                                        </td>
                                        <td class="px-4 py-2.5 hidden md:table-cell text-sand-600">
                                            {{ $department->head?->name ?? '—' }}
                                        </td>
                                        <td class="px-5 py-2.5 text-right whitespace-nowrap">
                                            <button type="button" class="btn btn-xs btn-secondary"
                                                    onclick="document.getElementById('edit-dept-{{ $department->id }}').showModal()">
                                                Edit
                                            </button>
                                            <form method="POST" action="{{ route('admin.departments.destroy', $department) }}"
                                                  class="inline"
                                                  onsubmit="return confirm({{ $department->isDeletable()
                                                      ? Js::from('Delete this empty department?')
                                                      : Js::from('This department still has employees, so it will be deactivated instead. Continue?') }})">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-xs btn-danger-soft">
                                                    {{ $department->isDeletable() ? 'Delete' : 'Deactivate' }}
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif

{{-- ==================================================================
     Dialogs
     ================================================================== --}}

<dialog id="add-college" class="card w-[min(30rem,92vw)] p-0 backdrop:bg-sand-900/40">
    <form method="POST" action="{{ route('admin.colleges.store') }}">
        @csrf
        <div class="card-header">
            <h3 class="card-title"><x-heroicon-o-building-library />Add College or Office</h3>
        </div>

        <div class="card-body space-y-4">
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="label label-required">Code</label>
                    <input type="text" name="code" required maxlength="20" class="input" placeholder="CAS">
                </div>
                <div class="col-span-2">
                    <label class="label label-required">Full name</label>
                    <input type="text" name="name" required maxlength="150" class="input"
                           placeholder="College of Arts and Sciences">
                </div>
            </div>

            <div>
                <label class="label">Assign Dean</label>
                <select name="dean_id" class="select">
                    <option value="">No Dean yet</option>
                    @foreach ($availableDeans as $dean)
                        <option value="{{ $dean->id }}">{{ $dean->name }}</option>
                    @endforeach
                </select>
                <span class="hint">A Dean signs for exactly one college; assigning here moves them.</span>
            </div>

            <div>
                <label class="label">Description</label>
                <textarea name="description" rows="2" maxlength="500" class="textarea"></textarea>
            </div>
        </div>

        <div class="card-footer flex justify-end gap-2">
            <button type="button" class="btn btn-md btn-secondary"
                    onclick="document.getElementById('add-college').close()">Cancel</button>
            <button class="btn btn-md btn-primary">Add College</button>
        </div>
    </form>
</dialog>

@foreach ($colleges as $college)
    {{-- Edit college --}}
    <dialog id="edit-{{ $college->id }}" class="card w-[min(30rem,92vw)] p-0 backdrop:bg-sand-900/40">
        <form method="POST" action="{{ route('admin.colleges.update', $college) }}">
            @csrf @method('PUT')
            <div class="card-header">
                <h3 class="card-title"><x-heroicon-o-pencil-square />Edit {{ $college->code }}</h3>
            </div>

            <div class="card-body space-y-4">
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="label label-required">Code</label>
                        <input type="text" name="code" required maxlength="20"
                               value="{{ $college->code }}" class="input">
                    </div>
                    <div class="col-span-2">
                        <label class="label label-required">Full name</label>
                        <input type="text" name="name" required maxlength="150"
                               value="{{ $college->name }}" class="input">
                    </div>
                </div>

                <div>
                    <label class="label">Assign Dean</label>
                    <select name="dean_id" class="select">
                        <option value="">No Dean yet</option>
                        @foreach ($availableDeans as $dean)
                            <option value="{{ $dean->id }}" @selected($college->dean_id === $dean->id)>
                                {{ $dean->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="label">Description</label>
                    <textarea name="description" rows="2" maxlength="500"
                              class="textarea">{{ $college->description }}</textarea>
                </div>

                <label class="flex items-center gap-2.5 text-[13px] text-sand-700 cursor-pointer">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked($college->is_active)
                           class="rounded border-sand-300 text-maroon-800 focus:ring-maroon-500">
                    Active — appears in pickers and reports
                </label>
            </div>

            <div class="card-footer flex justify-end gap-2">
                <button type="button" class="btn btn-md btn-secondary"
                        onclick="document.getElementById('edit-{{ $college->id }}').close()">Cancel</button>
                <button class="btn btn-md btn-primary">Save changes</button>
            </div>
        </form>
    </dialog>

    {{-- Add a department to this college --}}
    <dialog id="add-dept-{{ $college->id }}" class="card w-[min(30rem,92vw)] p-0 backdrop:bg-sand-900/40">
        <form method="POST" action="{{ route('admin.departments.store', $college) }}">
            @csrf
            <div class="card-header">
                <h3 class="card-title">
                    <x-heroicon-o-rectangle-group />
                    Add department to {{ $college->code }}
                </h3>
            </div>

            <div class="card-body space-y-4">
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="label label-required">Code</label>
                        <input type="text" name="code" required maxlength="30" class="input" placeholder="BAEL">
                    </div>
                    <div class="col-span-2">
                        <label class="label label-required">Full name</label>
                        <input type="text" name="name" required maxlength="150" class="input"
                               placeholder="Bachelor of Arts in English Language">
                    </div>
                </div>

                <div>
                    <label class="label">Head / Programme chair</label>
                    <select name="head_id" class="select">
                        <option value="">Not assigned</option>
                        @foreach ($college->employees as $person)
                            <option value="{{ $person->id }}">{{ $person->name }}</option>
                        @endforeach
                    </select>
                    <span class="hint">Optional. Leave approval still routes to the Dean.</span>
                </div>

                <div>
                    <label class="label">Description</label>
                    <textarea name="description" rows="2" maxlength="500" class="textarea"></textarea>
                </div>
            </div>

            <div class="card-footer flex justify-end gap-2">
                <button type="button" class="btn btn-md btn-secondary"
                        onclick="document.getElementById('add-dept-{{ $college->id }}').close()">Cancel</button>
                <button class="btn btn-md btn-primary">Add Department</button>
            </div>
        </form>
    </dialog>

    {{-- Edit each department --}}
    @foreach ($college->departments as $department)
        <dialog id="edit-dept-{{ $department->id }}" class="card w-[min(30rem,92vw)] p-0 backdrop:bg-sand-900/40">
            <form method="POST" action="{{ route('admin.departments.update', $department) }}">
                @csrf @method('PUT')
                <div class="card-header">
                    <h3 class="card-title"><x-heroicon-o-pencil-square />Edit {{ $department->code }}</h3>
                </div>

                <div class="card-body space-y-4">
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="label label-required">Code</label>
                            <input type="text" name="code" required maxlength="30"
                                   value="{{ $department->code }}" class="input">
                        </div>
                        <div class="col-span-2">
                            <label class="label label-required">Full name</label>
                            <input type="text" name="name" required maxlength="150"
                                   value="{{ $department->name }}" class="input">
                        </div>
                    </div>

                    <div>
                        <label class="label">Head / Programme chair</label>
                        <select name="head_id" class="select">
                            <option value="">Not assigned</option>
                            @foreach ($college->employees as $person)
                                <option value="{{ $person->id }}" @selected($department->head_id === $person->id)>
                                    {{ $person->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="label">Description</label>
                        <textarea name="description" rows="2" maxlength="500"
                                  class="textarea">{{ $department->description }}</textarea>
                    </div>

                    <label class="flex items-center gap-2.5 text-[13px] text-sand-700 cursor-pointer">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked($department->is_active)
                               class="rounded border-sand-300 text-maroon-800 focus:ring-maroon-500">
                        Active — appears in pickers
                    </label>
                </div>

                <div class="card-footer flex justify-end gap-2">
                    <button type="button" class="btn btn-md btn-secondary"
                            onclick="document.getElementById('edit-dept-{{ $department->id }}').close()">Cancel</button>
                    <button class="btn btn-md btn-primary">Save changes</button>
                </div>
            </form>
        </dialog>
    @endforeach
@endforeach

@endsection
