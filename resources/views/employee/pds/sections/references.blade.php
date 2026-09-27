@php
    $definition = \App\Support\Pds\PdsFormSchema::lists()['references']['references'];
    $f = fn (array $args) => array_merge(['values' => $values, 'locked' => $locked], $args);
@endphp

@include('employee.pds.partials.table', ['list' => 'references', 'definition' => $definition, 'values' => $values, 'locked' => $locked])

<fieldset class="space-y-4">
    <legend class="section-label mb-3">Government-issued ID</legend>
    <p class="hint -mt-1">Passport, GSIS, SSS, PRC, driver's license, etc. It is printed beside your signature under item 42.</p>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        @include('employee.pds.partials.field', $f(['key' => 'government_id.type', 'label' => 'Government-issued ID', 'placeholder' => 'e.g. PRC ID']))
        @include('employee.pds.partials.field', $f(['key' => 'government_id.number', 'label' => 'ID / license / passport no.']))
        @include('employee.pds.partials.field', $f(['key' => 'government_id.issued', 'label' => 'Date / place of issuance', 'placeholder' => 'e.g. 14/03/2019, Baguio City']))
    </div>
</fieldset>

<div class="alert alert-info text-[13px]">
    <x-heroicon-o-information-circle />
    <span>
        The photo, signature, right thumbmark and date accomplished are left for you to complete on the printed form,
        as are the oath lines — they have to be done in person.
    </span>
</div>
