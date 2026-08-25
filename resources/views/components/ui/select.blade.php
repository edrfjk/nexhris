@props(['name' => null, 'size' => null])

<select
    @if ($name) name="{{ $name }}" id="{{ $attributes->get('id', $name) }}" @endif
    {{ $attributes->merge([
        'class' => 'select'
            . ($size === 'sm' ? ' select-sm' : '')
            . ($name && $errors->has($name) ? ' input-error' : ''),
    ]) }}>
    {{ $slot }}
</select>
