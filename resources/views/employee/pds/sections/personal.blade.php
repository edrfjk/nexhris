@php
    use App\Support\Pds\PdsFormSchema;
    $f = fn (array $args) => array_merge(['values' => $values, 'locked' => $locked], $args);
@endphp

<fieldset class="space-y-4">
    <legend class="section-label mb-3">Name and birth</legend>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @include('employee.pds.partials.field', $f(['key' => 'surname', 'label' => '1. Surname', 'required' => true]))
        @include('employee.pds.partials.field', $f(['key' => 'first_name', 'label' => '2. First name', 'required' => true]))
        @include('employee.pds.partials.field', $f(['key' => 'middle_name', 'label' => 'Middle name']))
        @include('employee.pds.partials.field', $f(['key' => 'name_extension', 'label' => 'Name extension', 'placeholder' => 'Jr., Sr., III']))
        @include('employee.pds.partials.field', $f(['key' => 'date_of_birth', 'label' => '3. Date of birth', 'type' => 'date', 'required' => true]))
        <div class="sm:col-span-1 lg:col-span-3">
            @include('employee.pds.partials.field', $f(['key' => 'place_of_birth', 'label' => '4. Place of birth', 'placeholder' => 'City/Municipality, Province']))
        </div>
    </div>
</fieldset>

<fieldset class="space-y-4" x-data="{ civil: @js(old('civil_status', $values['civil_status'] ?? '')), citizenship: @js(old('citizenship', $values['citizenship'] ?? 'filipino')) }">
    <legend class="section-label mb-3">Status and citizenship</legend>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @include('employee.pds.partials.field', $f(['key' => 'sex', 'label' => '5. Sex at birth', 'type' => 'select', 'options' => PdsFormSchema::SEX, 'required' => true]))

        <div x-on:change="civil = $event.target.value">
            @include('employee.pds.partials.field', $f(['key' => 'civil_status', 'label' => '6. Civil status', 'type' => 'select', 'options' => PdsFormSchema::CIVIL_STATUS, 'required' => true]))
        </div>
        <div x-show="civil === 'other'" x-cloak>
            @include('employee.pds.partials.field', $f(['key' => 'civil_status_other', 'label' => 'Specify civil status', 'placeholder' => 'e.g. Annulled']))
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div x-on:change="citizenship = $event.target.value">
            @include('employee.pds.partials.field', $f(['key' => 'citizenship', 'label' => '16. Citizenship', 'type' => 'select', 'options' => PdsFormSchema::CITIZENSHIP, 'required' => true]))
        </div>
        <div x-show="citizenship === 'dual'" x-cloak>
            @include('employee.pds.partials.field', $f(['key' => 'dual_by', 'label' => 'Dual citizenship acquired', 'type' => 'select', 'options' => PdsFormSchema::DUAL_BY]))
        </div>
        <div x-show="citizenship === 'dual'" x-cloak>
            @include('employee.pds.partials.field', $f(['key' => 'dual_country', 'label' => 'Other country', 'placeholder' => 'e.g. United States']))
        </div>
    </div>
</fieldset>

<fieldset class="space-y-4">
    <legend class="section-label mb-3">Physical details</legend>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        @include('employee.pds.partials.field', $f(['key' => 'height', 'label' => '7. Height (m)', 'type' => 'number', 'placeholder' => '1.60']))
        @include('employee.pds.partials.field', $f(['key' => 'weight', 'label' => '8. Weight (kg)', 'type' => 'number', 'placeholder' => '55']))
        @include('employee.pds.partials.field', $f(['key' => 'blood_type', 'label' => '9. Blood type', 'placeholder' => 'O+']))
    </div>
</fieldset>

<fieldset class="space-y-4">
    <legend class="section-label mb-3">Government ID numbers</legend>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @include('employee.pds.partials.field', $f(['key' => 'umid', 'label' => '10. UMID ID no.']))
        @include('employee.pds.partials.field', $f(['key' => 'pagibig', 'label' => '11. PAG-IBIG ID no.']))
        @include('employee.pds.partials.field', $f(['key' => 'philhealth', 'label' => '12. PhilHealth no.']))
        @include('employee.pds.partials.field', $f(['key' => 'philsys', 'label' => '13. PhilSys Number (PSN)']))
        @include('employee.pds.partials.field', $f(['key' => 'tin', 'label' => '14. TIN no.']))
        @include('employee.pds.partials.field', $f(['key' => 'agency_employee_no', 'label' => '15. Agency employee no.']))
    </div>
</fieldset>

@foreach (['residential' => '17. Residential address', 'permanent' => '18. Permanent address'] as $prefix => $legend)
    <fieldset class="space-y-4">
        <legend class="section-label mb-3">{{ $legend }}</legend>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach (PdsFormSchema::addressParts() as $part => $label)
                @include('employee.pds.partials.field', $f(['key' => "{$prefix}.{$part}", 'label' => $label]))
            @endforeach
        </div>
    </fieldset>
@endforeach

<fieldset class="space-y-4">
    <legend class="section-label mb-3">Contact</legend>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        @include('employee.pds.partials.field', $f(['key' => 'telephone', 'label' => '19. Telephone no.']))
        @include('employee.pds.partials.field', $f(['key' => 'mobile', 'label' => '20. Mobile no.']))
        @include('employee.pds.partials.field', $f(['key' => 'email', 'label' => '21. E-mail address', 'type' => 'email']))
    </div>
</fieldset>
