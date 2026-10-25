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
<input type="hidden" name="confirm_replace_campus_director" value="0">

{{-- Shown before a save that would take a one-holder post from someone else.
     Opened over the form (it may itself be inside a dialog), so it lives in
     the top layer and never submits anything on its own. --}}
<dialog id="{{ $organizationFieldId }}-replace" class="card w-[min(28rem,92vw)] p-0 backdrop:bg-sand-900/40">
    <div class="card-header">
        <h3 class="card-title"><x-heroicon-o-exclamation-triangle />Replace current holder?</h3>
    </div>
    <div class="card-body space-y-3">
        <p class="text-[13px] text-sand-600">Saving this account will take over a post that only one person can hold.</p>
        <ul class="space-y-2" data-replace-list></ul>
    </div>
    <div class="card-footer flex justify-end gap-2">
        <button type="button" class="btn btn-md btn-secondary" data-replace-cancel>Cancel</button>
        <button type="button" class="btn btn-md btn-primary" data-replace-proceed>Proceed</button>
    </div>
</dialog>

<script>
(() => {
    const byCollege = @json($departmentsByCollege), deansByCollege = @json($deansByCollege);
    const campusDirector = @json(isset($campusDirector) ? ['id' => $campusDirector->id, 'name' => $campusDirector->name] : null);
    const college = document.getElementById(@json($organizationFieldId . '-college'));
    const department = document.getElementById(@json($organizationFieldId . '-department'));
    const hint = document.getElementById(@json($organizationFieldId . '-department-hint'));
    const position = document.getElementById(@json($positionFieldId . '-position'));
    const dialog = document.getElementById(@json($organizationFieldId . '-replace'));
    const form = college.closest('form'), employeeId = @json($employee?->id), preselected = @json($selectedDepartment);
    const isSomeoneElse = (holder) => holder && Number(holder.id) !== Number(employeeId);
    let confirmed = false;

    function populate(collegeId, keepId) {
        department.innerHTML = '<option value="">Select Department</option>';
        const list = byCollege[collegeId] || [];
        list.forEach((item) => department.add(new Option(item.label, item.id, false, Number(item.id) === Number(keepId))));
        hint.textContent = !collegeId ? 'Pick a college first.' : list.length ? 'Select the programme this employee belongs to.' : 'This college has no departments yet. Add them under Colleges & Offices.';
    }
    populate(college.value, preselected);
    college.addEventListener('change', () => populate(college.value, null));

    // Every post this save would take from someone else, with what happens to them.
    function replacements() {
        const found = [];
        if (form.querySelector('[name="role"]')?.value === 'campus_director' && isSomeoneElse(campusDirector)) {
            found.push({ field: 'confirm_replace_campus_director', name: campusDirector.name, post: 'Campus Director',
                effect: 'Their system role will change to Employee (regular employee). Their job title stays for HR to update.' });
        }
        if (position?.value === 'College Dean' && isSomeoneElse(deansByCollege[college.value])) {
            found.push({ field: 'confirm_replace_dean', name: deansByCollege[college.value].name, post: 'Dean of this college',
                effect: 'Their Dean system access will be removed. Their job title stays for HR to update.' });
        }
        if (position?.value === 'Program Chair / Program Head') {
            const selected = (byCollege[college.value] || []).find((item) => Number(item.id) === Number(department.value));
            if (isSomeoneElse(selected?.head)) {
                found.push({ field: 'confirm_replace_program_head', name: selected.head.name, post: `Programme Head for ${selected.label}`,
                    effect: 'Their account and job title will not be changed.' });
            }
        }
        return found;
    }

    function render(found) {
        const list = dialog.querySelector('[data-replace-list]');
        list.replaceChildren(...found.map((item) => {
            const row = document.createElement('li');
            row.className = 'rounded-lg border border-sand-200 bg-sand-50 p-3 text-[13px] text-sand-700';
            const lead = document.createElement('p');
            const who = document.createElement('span');
            who.className = 'font-medium text-sand-900';
            who.textContent = item.name;
            lead.append(who, ` is currently the ${item.post}.`);
            const effect = document.createElement('p');
            effect.className = 'mt-0.5 text-xs text-sand-500';
            effect.textContent = item.effect;
            row.append(lead, effect);
            return row;
        }));
    }

    dialog.querySelector('[data-replace-cancel]').addEventListener('click', () => dialog.close());
    dialog.querySelector('[data-replace-proceed]').addEventListener('click', () => {
        replacements().forEach((item) => { form.querySelector(`[name="${item.field}"]`).value = '1'; });
        dialog.close();
        confirmed = true;
        form.requestSubmit();
    });

    form.addEventListener('submit', (event) => {
        if (confirmed) { confirmed = false; return; }
        ['confirm_replace_campus_director', 'confirm_replace_dean', 'confirm_replace_program_head']
            .forEach((field) => { form.querySelector(`[name="${field}"]`).value = '0'; });
        const found = replacements();
        if (!found.length) return;
        event.preventDefault();
        render(found);
        dialog.showModal();
    });
})();
</script>
