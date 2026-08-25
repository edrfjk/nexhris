@props(['name' => null])

<textarea
    @if ($name) name="{{ $name }}" id="{{ $attributes->get('id', $name) }}" @endif
    {{ $attributes->merge([
        'rows' => 3,
        'class' => 'textarea' . ($name && $errors->has($name) ? ' input-error' : ''),
    ]) }}>{{ $slot }}</textarea>
