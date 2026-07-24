@php $detailsLabel = $detailsLabel ?? 'If yes, give details'; @endphp
<div>
    <p class="text-sm mb-1">{{ $label }}</p>
    <label class="inline-flex items-center gap-1 text-sm mr-4">
        <input type="radio" name="{{ $field }}" value="1" @checked(old($field, $value))> Yes
    </label>
    <label class="inline-flex items-center gap-1 text-sm">
        <input type="radio" name="{{ $field }}" value="0" @checked(!old($field, $value))> No
    </label>
    <input name="{{ $field }}_details" placeholder="{{ $detailsLabel }}"
           value="{{ old($field . '_details', $details) }}"
           class="mt-1 w-full border rounded px-2 py-1.5 text-sm">
</div>