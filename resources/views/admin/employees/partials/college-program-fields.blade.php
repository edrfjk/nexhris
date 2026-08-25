@php
    // Both are real records now. The department list is filtered to the chosen
    // college in the browser, so HR never has to scroll past another college's
    // programmes.
    $selectedCollege = (int) old('college_id', $employee->college_id ?? 0);
    $selectedDepartment = (int) old('department_id', $employee->department_id ?? 0);

    $departmentsByCollege = $colleges->mapWithKeys(fn ($college) => [
        $college->id => $college->activeDepartments->map(fn ($d) => [
            'id' => $d->id,
            'label' => $d->code . ' — ' . $d->name,
        ])->values(),
    ]);
@endphp

<div>
    <label class="label">College / Office</label>
    <select name="college_id" id="college-select" class="select">
        <option value="">Select College / Office</option>
        @foreach ($colleges as $college)
            <option value="{{ $college->id }}" @selected($selectedCollege === $college->id)>
                {{ $college->code }} — {{ $college->name }}
            </option>
        @endforeach
    </select>
    <span class="hint">Decides which Dean approves this person's leave.</span>
</div>

<div>
    <label class="label">Department / Programme</label>
    <select name="department_id" id="department-select" class="select">
        <option value="">Select Department</option>
    </select>
    <span class="hint" id="department-hint">Pick a college first.</span>
</div>

<script>
(function () {
    const byCollege = @json($departmentsByCollege);
    const collegeSelect = document.getElementById('college-select');
    const departmentSelect = document.getElementById('department-select');
    const hint = document.getElementById('department-hint');
    const preselected = @json($selectedDepartment);

    function populate(collegeId, keepId) {
        departmentSelect.innerHTML = '<option value="">Select Department</option>';

        const list = byCollege[collegeId] || [];

        list.forEach(function (department) {
            const option = document.createElement('option');
            option.value = department.id;
            option.textContent = department.label;
            if (Number(department.id) === Number(keepId)) option.selected = true;
            departmentSelect.appendChild(option);
        });

        if (!collegeId) {
            hint.textContent = 'Pick a college first.';
        } else if (list.length === 0) {
            // A college with no departments yet is a normal state, not an
            // error — say so instead of showing an empty box.
            hint.textContent = 'This college has no departments yet. You can add them under Colleges & Offices.';
        } else {
            hint.textContent = 'Optional. Used for grouping and reports, not for approval routing.';
        }
    }

    populate(collegeSelect.value, preselected);

    collegeSelect.addEventListener('change', function () {
        populate(this.value, null);
    });
})();
</script>
