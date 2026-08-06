@php
    $selectedCollege = old('department', $employee->department ?? '');
    $selectedProgram = old('program', $employee->program ?? '');
@endphp

<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">College / Office</label>
    <select name="department" id="college-select"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
        <option value="">Select College/Office</option>
        @foreach ($colleges as $code => $college)
            <option value="{{ $code }}" @selected($selectedCollege === $code)>{{ $code }} — {{ $college['name'] }}</option>
        @endforeach
    </select>
</div>

<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Program / Office Unit</label>
    <select name="program" id="program-select"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
        <option value="">Select Program</option>
    </select>
</div>

<script>
(function () {
    const collegePrograms = @json(collect($colleges)->map(fn ($c) => $c['programs']));
    const collegeSelect = document.getElementById('college-select');
    const programSelect = document.getElementById('program-select');
    const preselectedProgram = @json($selectedProgram);

    function populatePrograms(collegeCode, keepValue) {
        programSelect.innerHTML = '<option value="">Select Program</option>';
        const programs = collegePrograms[collegeCode] || [];
        programs.forEach(function (program) {
            const opt = document.createElement('option');
            opt.value = program;
            opt.textContent = program;
            if (program === keepValue) opt.selected = true;
            programSelect.appendChild(opt);
        });
    }

    if (collegeSelect.value) {
        populatePrograms(collegeSelect.value, preselectedProgram);
    }

    collegeSelect.addEventListener('change', function () {
        populatePrograms(this.value, null);
    });
})();
</script>