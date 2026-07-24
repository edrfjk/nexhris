<form method="POST" action="{{ route('pds.voluntary.store') }}" class="grid grid-cols-3 gap-3 items-end mb-6 text-sm bg-gray-50 p-4 rounded">
@csrf

<div>
<label>Organization</label>
<input name="organization_name_address" class="w-full border rounded px-3 py-2">
</div>

<div>
<label>From</label>
<input type="date" name="date_from" class="w-full border rounded px-3 py-2">
</div>

<div>
<label>To</label>
<input type="date" name="date_to" class="w-full border rounded px-3 py-2">
</div>

<div>
<label>Hours</label>
<input name="number_of_hours" class="w-full border rounded px-3 py-2">
</div>

<div>
<label>Nature of Work</label>
<input name="position_nature_of_work" class="w-full border rounded px-3 py-2">
</div>

<button class="bg-gray-700 text-white rounded px-3 py-2">+ Add</button>

</form>

<table class="w-full border text-sm">
<thead class="bg-gray-100">
<tr>
<th>Organization</th>
<th>Hours</th>
<th></th>
</tr>
</thead>

<tbody>
@forelse($user->pdsVoluntaryWorks as $item)
<tr class="border-t">
<td class="px-3 py-2">{{ $item->organization_name_address }}</td>
<td class="px-3 py-2">{{ $item->number_of_hours }}</td>

<td class="text-right">
<form action="{{ route('pds.voluntary.destroy',$item) }}" method="POST">
@csrf
@method('DELETE')
<button class="text-red-600 text-xs">Remove</button>
</form>
</td>
</tr>
@empty
<tr><td colspan="3" class="text-center py-4 text-gray-400">No records yet.</td></tr>
@endforelse
</tbody>
</table>