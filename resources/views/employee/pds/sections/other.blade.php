{{-- Every table in this section, as defined in PdsFormSchema::lists(). --}}
@foreach (\App\Support\Pds\PdsFormSchema::lists()[$section] as $list => $definition)
    @include('employee.pds.partials.table', ['list' => $list, 'definition' => $definition, 'values' => $values, 'locked' => $locked])
@endforeach
