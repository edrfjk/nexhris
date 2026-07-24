<form method="POST" action="{{ route('pds.eligibility.store') }}" class="grid grid-cols-3 gap-3 items-end mb-6 text-sm bg-gray-50 p-4 rounded">
    @csrf

    <div>
        <label class="block mb-1">Eligibility</label>
        <input name="eligibility_name" class="w-full border rounded px-3 py-2" required>
    </div>

    <div>
        <label class="block mb-1">Rating</label>
        <input name="rating" class="w-full border rounded px-3 py-2">
    </div>

    <div>
        <label class="block mb-1">Exam Date</label>
        <input type="date" name="exam_date" class="w-full border rounded px-3 py-2">
    </div>

    <div>
        <label class="block mb-1">Exam Place</label>
        <input name="exam_place" class="w-full border rounded px-3 py-2">
    </div>

    <div>
        <label class="block mb-1">License Number</label>
        <input name="license_number" class="w-full border rounded px-3 py-2">
    </div>

    <div>
        <label class="block mb-1">Valid Until</label>
        <input type="date" name="license_valid_until" class="w-full border rounded px-3 py-2">
    </div>

    <button class="bg-gray-700 text-white rounded px-3 py-2">+ Add</button>
</form>

<table class="w-full text-sm border">
<thead class="bg-gray-100">
<tr>
<th class="px-3 py-2 text-left">Eligibility</th>
<th class="px-3 py-2 text-left">Rating</th>
<th class="px-3 py-2 text-left">Exam Date</th>
<th></th>
</tr>
</thead>

<tbody>
@forelse($user->pdsCivilServiceEligibilities as $item)
<tr class="border-t">
<td class="px-3 py-2">{{ $item->eligibility_name }}</td>
<td class="px-3 py-2">{{ $item->rating }}</td>
<td class="px-3 py-2">{{ $item->exam_date }}</td>

<td class="px-3 py-2 text-right">
<form action="{{ route('pds.eligibility.destroy',$item) }}" method="POST">
@csrf
@method('DELETE')
<button class="text-red-600 text-xs">Remove</button>
</form>
</td>
</tr>
@empty
<tr><td colspan="4" class="text-center py-4 text-gray-400">No records yet.</td></tr>
@endforelse
</tbody>
</table>