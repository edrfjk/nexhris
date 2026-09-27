{{--
    One of the PDS's repeating tables (work experience, trainings, …), as a
    list of cards the employee adds to and removes from.

    @param string $list        the key the rows post under ("items", "skills")
    @param array  $definition  PdsFormSchema::lists()[section][list]
    @param array  $values      the section's saved answers
    @param bool   $locked
--}}
@php
    $columns = $definition['columns'];
    $blank = array_fill_keys(array_keys($columns), '');
    $rows = array_values(old($list, $values[$list] ?? []));
    $rows = array_map(fn ($row) => array_merge($blank, array_intersect_key((array) $row, $blank)), $rows);

    // Validation reports "items.3.position"; hand the messages to the page so
    // each lands under its own box.
    $messages = collect($errors->getMessages())
        ->filter(fn ($m, $key) => str_starts_with($key, $list . '.'))
        ->map(fn ($m) => $m[0]);
    $listError = $errors->first($list);
@endphp

<fieldset class="space-y-3"
          x-data="{ rows: @js($rows ?: [$blank]), blank: @js($blank), errors: @js($messages) }">
    <legend class="section-label mb-3">{{ $definition['label'] }}</legend>

    @if ($listError)
        <span class="error-text">{{ $listError }}</span>
    @endif

    <template x-for="(row, i) in rows" :key="i">
        <div class="rounded-lg border border-sand-200 bg-sand-50/40 p-4">
            <div class="mb-3 flex items-center justify-between gap-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-sand-500"
                   x-text="'{{ ucfirst($definition['item']) }} ' + (i + 1)"></p>
                @unless ($locked)
                    <button type="button" class="btn btn-xs btn-ghost"
                            x-on:click="rows.splice(i, 1); if (! rows.length) rows.push({ ...blank })">
                        <x-heroicon-o-trash class="w-3.5 h-3.5" />
                        Remove
                    </button>
                @endunless
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach ($columns as $column => $spec)
                    @php
                        $span = ($spec['wide'] ?? false) ? 'sm:col-span-2' : '';
                        $required = $column === $definition['key'];
                    @endphp
                    <div class="block {{ $span }}">
                        <label class="label @if ($required) label-required @endif"
                               :for="'pds-{{ $list }}-' + i + '-{{ $column }}'">{{ $spec['label'] }}</label>

                        @if ($spec['type'] === 'select')
                            <select class="select"
                                    :id="'pds-{{ $list }}-' + i + '-{{ $column }}'"
                                    :name="'{{ $list }}[' + i + '][{{ $column }}]'"
                                    x-model="row.{{ $column }}"
                                    :class="errors['{{ $list }}.' + i + '.{{ $column }}'] && 'input-error'"
                                    @disabled($locked)>
                                <option value="">Select…</option>
                                @foreach ($spec['options'] as $optionValue => $optionLabel)
                                    <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
                                @endforeach
                            </select>
                        @else
                            <input class="input"
                                   type="{{ $spec['type'] === 'number' ? 'number' : $spec['type'] }}"
                                   @if ($spec['type'] === 'number') step="0.01" min="0" @endif
                                   @if (! empty($spec['placeholder'])) placeholder="{{ $spec['placeholder'] }}" @endif
                                   :id="'pds-{{ $list }}-' + i + '-{{ $column }}'"
                                   :name="'{{ $list }}[' + i + '][{{ $column }}]'"
                                   x-model="row.{{ $column }}"
                                   :class="errors['{{ $list }}.' + i + '.{{ $column }}'] && 'input-error'"
                                   @disabled($locked)>
                        @endif

                        <span class="error-text" x-show="errors['{{ $list }}.' + i + '.{{ $column }}']"
                              x-text="errors['{{ $list }}.' + i + '.{{ $column }}']" x-cloak></span>
                        @if (! empty($spec['hint']))
                            <span class="hint" x-show="! errors['{{ $list }}.' + i + '.{{ $column }}']">{{ $spec['hint'] }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </template>

    @unless ($locked)
        <button type="button" class="btn btn-sm btn-secondary"
                x-show="rows.length < {{ $definition['max'] }}" x-on:click="rows.push({ ...blank })">
            <x-heroicon-o-plus class="w-4 h-4" />
            Add {{ $definition['item'] }}
        </button>
    @endunless
</fieldset>
