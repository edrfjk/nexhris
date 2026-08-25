@props(['name' => null, 'size' => null])

<input
    @if ($name) name="{{ $name }}" id="{{ $attributes->get('id', $name) }}" @endif
    {{ $attributes->merge([
        'type' => 'text',
        'class' => 'input'
            . ($size === 'sm' ? ' input-sm' : '')
            . ($name && $errors->has($name) ? ' input-error' : ''),
    ]) }}>
