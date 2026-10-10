@php
    $editing = (bool) $holiday;
    // Old input belongs to the dialog that was submitted, not to every dialog
    // on the page (each edit dialog would otherwise show the failed values).
    $mine = old('_dialog') === $dialogId;
    $o = fn (string $key, $default = null) => $mine ? old($key, $default) : $default;
    $category = $o('category', $holiday?->category ?? 'regular');
    $scope = $o('scope', $holiday?->scope ?? 'national');
    // An unticked box is not sent at all, so after a failed submit its
    // absence means "charged".
    $excluded = $mine
        ? (bool) old('excludes_from_leave', false)
        : (bool) ($holiday?->excludes_from_leave ?? \App\Models\Holiday::EXCLUDED_BY_DEFAULT[$category]);
@endphp

<dialog id="{{ $dialogId }}" class="card w-[min(30rem,92vw)] p-0 backdrop:bg-sand-900/40"
        x-data="{
            category: @js($category),
            scope: @js($scope),
            excluded: @js($excluded),
            defaults: @js(\App\Models\Holiday::EXCLUDED_BY_DEFAULT),
        }">
    <form method="POST" action="{{ $editing ? route('admin.holidays.update', $holiday) : route('admin.holidays.store') }}">
        @csrf
        <input type="hidden" name="_dialog" value="{{ $dialogId }}">
        @if ($editing) @method('PUT') @endif

        <div class="card-header">
            <h3 class="card-title">
                @if ($editing) <x-heroicon-o-pencil-square />Edit holiday @else <x-heroicon-o-sun />Add holiday @endif
            </h3>
        </div>

        <div class="card-body space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="sm:col-span-2">
                    <label for="{{ $dialogId }}-name" class="label label-required">Name</label>
                    <input id="{{ $dialogId }}-name" type="text" name="name" required maxlength="150" class="input"
                           value="{{ $o('name', $holiday?->name) }}" placeholder="Independence Day">
                </div>
                <div>
                    <label for="{{ $dialogId }}-date" class="label label-required">Date</label>
                    <input id="{{ $dialogId }}-date" type="date" name="date" required class="input"
                           value="{{ $o('date', $holiday?->date?->toDateString()) }}">
                </div>
            </div>

            <div>
                <label for="{{ $dialogId }}-category" class="label label-required">Category</label>
                {{-- Choosing a category sets the usual leave treatment; HR can still change it below. --}}
                <select id="{{ $dialogId }}-category" name="category" class="select" required
                        x-model="category" @change="excluded = defaults[category]">
                    @foreach (\App\Models\Holiday::CATEGORIES as $value => $label)
                        <option value="{{ $value }}" @selected($value === $category)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label for="{{ $dialogId }}-scope" class="label label-required">Applies</label>
                    <select id="{{ $dialogId }}-scope" name="scope" class="select" required x-model="scope">
                        @foreach (\App\Models\Holiday::SCOPES as $value => $label)
                            <option value="{{ $value }}" @selected($value === $scope)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2" x-show="scope === 'local'">
                    <label for="{{ $dialogId }}-locality" class="label label-required">Place</label>
                    <input id="{{ $dialogId }}-locality" type="text" name="locality" maxlength="150" class="input"
                           value="{{ $o('locality', $holiday?->locality) }}" placeholder="Tagudin, Ilocos Sur"
                           :required="scope === 'local'">
                </div>
            </div>

            <div>
                <label for="{{ $dialogId }}-source" class="label">Source</label>
                <input id="{{ $dialogId }}-source" type="text" name="source" maxlength="255" class="input"
                       value="{{ $o('source', $holiday?->source) }}" placeholder="Proclamation No. …">
            </div>

            <label class="flex items-start gap-2.5 rounded-lg border border-sand-200 bg-sand-50 p-3">
                <input type="checkbox" name="excludes_from_leave" value="1" class="mt-0.5" x-model="excluded">
                <span class="text-[13px] text-sand-700">
                    <span class="font-medium">Not charged on vacation or sick leave</span>
                    <span class="block text-xs text-sand-500 mt-0.5">
                        Leave this off for a special working day, and for a religious or local holiday that does not
                        apply to the campus.
                    </span>
                </span>
            </label>
        </div>

        <div class="card-footer flex justify-end gap-2">
            <button type="button" class="btn btn-md btn-secondary"
                    onclick="document.getElementById('{{ $dialogId }}').close()">Cancel</button>
            <button class="btn btn-md btn-primary">{{ $editing ? 'Save changes' : 'Add holiday' }}</button>
        </div>
    </form>
</dialog>
