{{--
    One PDS answer. $key is the answer's dotted path within the section
    ("residential.city"); the input is named with brackets so it posts back
    as the same nested structure the section is validated and stored as.

    @param string      $key
    @param string      $label
    @param string      $type      text | date | number | email | select | year
    @param array       $options   for select: value => label
    @param bool        $required
    @param string|null $hint
    @param string|null $placeholder
--}}
@php
    $type ??= 'text';
    $required ??= false;
    $hint ??= null;
    $placeholder ??= null;

    $parts = explode('.', $key);
    $name = array_shift($parts) . implode('', array_map(fn ($p) => "[{$p}]", $parts));
    $id = 'pds-' . str_replace(['.', '_'], '-', $key);
    $value = old($key, data_get($values, $key));
    $invalid = $errors->has($key);
@endphp

<div class="block">
    <label for="{{ $id }}" class="label @if ($required) label-required @endif">{{ $label }}</label>

    @if ($type === 'select')
        <select id="{{ $id }}" name="{{ $name }}" class="select @if ($invalid) input-error @endif"
                @disabled($locked) @required($required)>
            <option value="">Select…</option>
            @foreach ($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
            @endforeach
        </select>
    @else
        <input id="{{ $id }}" name="{{ $name }}" value="{{ $value }}"
               type="{{ $type === 'year' ? 'text' : $type }}"
               {{-- No digits-only pattern: "N/A" is a valid answer here. --}}
               @if ($type === 'year') maxlength="4" @endif
               @if ($type === 'number') step="0.01" min="0" @endif
               @if ($placeholder) placeholder="{{ $placeholder }}" @endif
               class="input @if ($invalid) input-error @endif"
               @disabled($locked) @required($required)>
    @endif

    @if ($invalid)
        <span class="error-text">{{ $errors->first($key) }}</span>
    @elseif ($hint)
        <span class="hint">{{ $hint }}</span>
    @endif
</div>
