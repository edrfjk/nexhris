@php
    use App\Support\Pds\PdsFormSchema;
    $f = fn (array $args) => array_merge(['values' => $values, 'locked' => $locked], $args);

    $children = old('children', $values['children'] ?? []);
    // Always offer at least one empty row, and never more than the form holds.
    $shown = min(PdsFormSchema::MAX_CHILDREN, max(1, count($children)));
@endphp

<fieldset class="space-y-4">
    <legend class="section-label mb-3">22. Spouse</legend>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @include('employee.pds.partials.field', $f(['key' => 'spouse.surname', 'label' => 'Surname']))
        @include('employee.pds.partials.field', $f(['key' => 'spouse.first_name', 'label' => 'First name']))
        @include('employee.pds.partials.field', $f(['key' => 'spouse.middle_name', 'label' => 'Middle name']))
        @include('employee.pds.partials.field', $f(['key' => 'spouse.name_extension', 'label' => 'Name extension', 'placeholder' => 'Jr., Sr.']))
        @include('employee.pds.partials.field', $f(['key' => 'spouse.occupation', 'label' => 'Occupation']))
        @include('employee.pds.partials.field', $f(['key' => 'spouse.employer', 'label' => 'Employer / business name']))
        @include('employee.pds.partials.field', $f(['key' => 'spouse.business_address', 'label' => 'Business address']))
        @include('employee.pds.partials.field', $f(['key' => 'spouse.telephone', 'label' => 'Telephone no.']))
    </div>
</fieldset>

<fieldset class="space-y-4">
    <legend class="section-label mb-3">24. Father</legend>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @include('employee.pds.partials.field', $f(['key' => 'father.surname', 'label' => 'Surname']))
        @include('employee.pds.partials.field', $f(['key' => 'father.first_name', 'label' => 'First name']))
        @include('employee.pds.partials.field', $f(['key' => 'father.middle_name', 'label' => 'Middle name']))
        @include('employee.pds.partials.field', $f(['key' => 'father.name_extension', 'label' => 'Name extension', 'placeholder' => 'Jr., Sr.']))
    </div>
</fieldset>

<fieldset class="space-y-4">
    <legend class="section-label mb-3">25. Mother's maiden name</legend>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        @include('employee.pds.partials.field', $f(['key' => 'mother.surname', 'label' => 'Surname']))
        @include('employee.pds.partials.field', $f(['key' => 'mother.first_name', 'label' => 'First name']))
        @include('employee.pds.partials.field', $f(['key' => 'mother.middle_name', 'label' => 'Middle name']))
    </div>
</fieldset>

<fieldset class="space-y-3" x-data="{ shown: {{ $shown }} }">
    <legend class="section-label mb-3">23. Children</legend>
    <p class="hint -mt-1">Full name of each child. The form has room for {{ PdsFormSchema::MAX_CHILDREN }}.</p>

    @for ($i = 0; $i < PdsFormSchema::MAX_CHILDREN; $i++)
        <div class="grid grid-cols-1 sm:grid-cols-[1fr_12rem] gap-4" x-show="{{ $i }} < shown" @if ($i >= $shown) x-cloak @endif>
            @include('employee.pds.partials.field', $f(['key' => "children.{$i}.name", 'label' => 'Child ' . ($i + 1) . ' — full name']))
            @include('employee.pds.partials.field', $f(['key' => "children.{$i}.date_of_birth", 'label' => 'Date of birth', 'type' => 'date']))
        </div>
    @endfor

    @unless ($locked)
        <button type="button" class="btn btn-sm btn-ghost"
                x-show="shown < {{ PdsFormSchema::MAX_CHILDREN }}" x-on:click="shown++">
            <x-heroicon-o-plus class="w-4 h-4" />
            Add a child
        </button>
    @endunless
</fieldset>
