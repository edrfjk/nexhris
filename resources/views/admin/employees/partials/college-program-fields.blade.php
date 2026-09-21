@php
    $selectedCollege = (int) old('college_id', $employee->college_id ?? 0);
    $selectedDepartment = (int) old('department_id', $employee->department_id ?? 0);
    $organizationFieldId ??= 'employee-org';
    $positionFieldId ??= 'employee-position';
    $departmentsByCollege = $colleges->mapWithKeys(fn ($college) => [$college->id => $college->activeDepartments->map(fn ($department) => ['id' => $department->id, 'label' => $department->code . ' — ' . $department->name, 'head' => $department->head ? ['id' => $department->head->id, 'name' => $department->head->name] : null])->values()]);
    $deansByCollege = $colleges->mapWithKeys(fn ($college) => [$college->id => $college->dean ? ['id' => $college->dean->id, 'name' => $college->dean->name] : null]);
@endphp

<div>
    <label class="label">College / Office</label>
    <select name="college_id" id="{{ $organizationFieldId }}-college" class="select">
        <option value="">Select College / Office</option>
        @foreach ($colleges as $college)
            <option value="{{ $college->id }}" @selected($selectedCollege === $college->id)>{{ $college->code }} — {{ $college->name }}</option>
        @endforeach
    </select>
    <span class="hint">Decides which Dean approves this person's leave.</span>
    @error('college_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>

<div>
    <label class="label">Department / Programme</label>
    <select name="department_id" id="{{ $organizationFieldId }}-department" class="select"><option value="">Select Department</option></select>
    <span class="hint" id="{{ $organizationFieldId }}-department-hint">Pick a college first.</span>
    @error('department_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>

<input type="hidden" name="confirm_replace_dean" value="0">
<input type="hidden" name="confirm_replace_program_head" value="0">

<script>
(() => {
    const byCollege = @json($departmentsByCollege), deansByCollege = @json($deansByCollege);
    const college = document.getElementById(@json($organizationFieldId . '-college'));
    const department = document.getElementById(@json($organizationFieldId . '-department'));
    const hint = document.getElementById(@json($organizationFieldId . '-department-hint'));
    const position = document.getElementById(@json($positionFieldId . '-position'));
    const form = college.closest('form'), employeeId = @json($employee?->id), preselected = @json($selectedDepartment);
    function populate(collegeId, keepId) {
        department.innerHTML = '<option value="">Select Department</option>';
        const list = byCollege[collegeId] || [];
        list.forEach((item) => department.add(new Option(item.label, item.id, false, Number(item.id) === Number(keepId))));
        hint.textContent = !collegeId ? 'Pick a college first.' : list.length ? 'Select the programme this employee belongs to.' : 'This college has no departments yet. Add them under Colleges & Offices.';
    }
    populate(college.value, preselected);
    college.addEventListener('change', () => populate(college.value, null));
    form.addEventListener('submit', (event) => {
        const deanConfirmed = form.querySelector('[name="confirm_replace_dean"]');
        const headConfirmed = form.querySelector('[name="confirm_replace_program_head"]');
        deanConfirmed.value = '0'; headConfirmed.value = '0';
        if (position?.value === 'College Dean') {
            const dean = deansByCollege[college.value];
            if (dean && Number(dean.id) !== Number(employeeId)) {
                if (!confirm(`${dean.name} is currently the Dean for this college. Replace them? Their Dean system access will be removed; their job title will remain for HR to update.`)) { event.preventDefault(); return; }
                deanConfirmed.value = '1';
            }
        }
        if (position?.value === 'Program Chair / Program Head') {
            const selected = (byCollege[college.value] || []).find((item) => Number(item.id) === Number(department.value));
            if (selected?.head && Number(selected.head.id) !== Number(employeeId)) {
                if (!confirm(`${selected.head.name} is currently the Programme Head for ${selected.label}. Replace them? Their account and job title will not be changed.`)) { event.preventDefault(); return; }
                headConfirmed.value = '1';
            }
        }
    });
})();
</script>
