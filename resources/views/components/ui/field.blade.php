@props([
    'label' => null,
    'name' => null,
    'required' => false,
    'hint' => null,
])

{{--
    Label + control + hint + validation message in one place, so every form in
    the app reports errors the same way instead of each view inventing its own.
--}}
<div {{ $attributes->merge(['class' => 'block']) }}>
    @if ($label)
        <label @if ($name) for="{{ $name }}" @endif
               class="label @if ($required) label-required @endif">
            {{ $label }}
        </label>
    @endif

    {{ $slot }}

    @if ($hint && ! ($name && $errors->has($name)))
        <span class="hint">{{ $hint }}</span>
    @endif

    @if ($name)
        @error($name)
            <span class="error-text">{{ $message }}</span>
        @enderror
    @endif
</div>
