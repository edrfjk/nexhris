<form method="POST" action="{{ route('pds.education.store') }}" class="grid grid-cols-4 gap-3 items-end mb-6 text-sm bg-gray-50 p-4 rounded">
    @csrf
    <div>
        <label class="block mb-1">Level</label>
        <select name="level" class="w-full border rounded px-3 py-2" required>
            @foreach (['Elementary','Secondary','Vocational/Trade Course','College','Graduate Studies'] as $lvl)
                <option value="{{ $lvl }}">{{ $lvl }}</option>
            @endforeach
        </select>
    </div>
    <div><label class="block mb-1">School Name</label><input name="school_name" class="w-full border rounded px-3 py-2" required></div>
    <div><label class="block mb-1">Course/Degree</label><input name="degree_course" class="w-full border rounded px-3 py-2"></div>
    <div><label class="block mb-1">Year Graduated</label><input name="year_graduated" class="w-full border rounded px-3 py-2"></div>
    <div><label class="block mb-1">Period From</label><input type="date" name="period_from" class="w-full border rounded px-3 py-2"></div>
    <div><label class="block mb-1">Period To</label><input type="date" name="period_to" class="w-full border rounded px-3 py-2"></div>
    <div><label class="block mb-1">Units Earned (if not graduated)</label><input name="highest_level_units" class="w-full border rounded px-3 py-2"></div>
    <div><label class="block mb-1">Honors/Scholarship</label><input name="scholarship_honors" class="w-full border rounded px-3 py-2"></div>
    <button type="submit" class="bg-gray-700 text-white rounded px-3 py-2 w-fit">+ Add</button>
</form>

<table class="w-full text-sm border">
    <thead class="bg-gray-100">
        <tr>
            <th class="px-3 py-2 text-left">Level</th>
            <th class="px-3 py-2 text-left">School</th>
            <th class="px-3 py-2 text-left">Course</th>
            <th class="px-3 py-2 text-left">Year Grad.</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($user->pdsEducationalBackgrounds as $edu)
            <tr class="border-t">
                <td class="px-3 py-2">{{ $edu->level }}</td>
                <td class="px-3 py-2">{{ $edu->school_name }}</td>
                <td class="px-3 py-2">{{ $edu->degree_course }}</td>
                <td class="px-3 py-2">{{ $edu->year_graduated }}</td>
                <td class="px-3 py-2 text-right">
                    <form action="{{ route('pds.education.destroy', $edu) }}" method="POST" class="inline" onsubmit="return confirm('Remove this record?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-red-600 text-xs">Remove</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-3 py-4 text-center text-gray-400">No education records yet.</td></tr>
        @endforelse
    </tbody>
</table>