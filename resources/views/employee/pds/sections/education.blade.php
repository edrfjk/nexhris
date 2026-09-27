@php
    use App\Support\Pds\PdsFormSchema;
    $f = fn (array $args) => array_merge(['values' => $values, 'locked' => $locked], $args);
@endphp

@foreach (PdsFormSchema::EDUCATION_LEVELS as $level => $title)
    <fieldset class="space-y-4">
        <legend class="section-label mb-3">{{ $title }}</legend>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @include('employee.pds.partials.field', $f(['key' => "{$level}.school", 'label' => 'Name of school (write in full)']))
            @include('employee.pds.partials.field', $f(['key' => "{$level}.degree", 'label' => 'Basic education / degree / course (write in full)']))
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            @include('employee.pds.partials.field', $f(['key' => "{$level}.from", 'label' => 'Attended from', 'type' => 'year', 'placeholder' => 'YYYY or N/A']))
            @include('employee.pds.partials.field', $f(['key' => "{$level}.to", 'label' => 'Attended to', 'type' => 'year', 'placeholder' => 'YYYY or N/A']))
            @include('employee.pds.partials.field', $f(['key' => "{$level}.year_graduated", 'label' => 'Year graduated', 'type' => 'year', 'placeholder' => 'YYYY or N/A']))
            @include('employee.pds.partials.field', $f(['key' => "{$level}.units", 'label' => 'Highest level / units earned', 'hint' => 'If not graduated']))
        </div>
        @include('employee.pds.partials.field', $f(['key' => "{$level}.honors", 'label' => 'Scholarship / academic honors received']))
    </fieldset>
@endforeach
