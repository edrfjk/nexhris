@php
    $positionGroups = $positions->groupBy(fn ($position) => $position->category ?: 'Other')->sortKeys();
    $selectedPosition = old('position', $selectedPosition ?? '');
    $selectedCategory = old('position_category') ?: optional(
        $positions->firstWhere('name', $selectedPosition)
    )->category;
    $selectedCategory ??= 'Other';
    if ($selectedPosition && ! $positions->contains('name', $selectedPosition)) {
        $positionGroups->put('Other', collect([(object) ['name' => $selectedPosition]]));
    }
@endphp

<div>
    <label class="label">Position Category</label>
    <select id="{{ $positionFieldId }}-category" name="position_category" class="select">
        <option value="">Select category first</option>
        @foreach ($positionGroups as $category => $group)
            <option value="{{ $category }}" @selected($selectedCategory === $category)>{{ $category }}</option>
        @endforeach
    </select>
    @error('position_category')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>

<div>
    <label class="label">Position</label>
    <select id="{{ $positionFieldId }}-position" name="position" class="select">
        <option value="">Select position</option>
    </select>
    <span class="hint">Choose a category, then the exact position level.</span>
    @error('position')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>

<script>
(() => {
    const categories = @json($positionGroups->map(fn ($group) => $group->pluck('name')->values()));
    const category = document.getElementById(@json($positionFieldId . '-category'));
    const position = document.getElementById(@json($positionFieldId . '-position'));
    const chosen = @json($selectedPosition);

    function populate(keep) {
        position.innerHTML = '<option value="">Select position</option>';
        (categories[category.value] || []).forEach((name) => {
            const option = new Option(name, name, false, name === keep);
            position.add(option);
        });
        position.disabled = !category.value;
    }

    populate(chosen);
    category.addEventListener('change', () => populate(''));
})();
</script>
