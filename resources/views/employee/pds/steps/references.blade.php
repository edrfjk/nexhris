<form method="POST" action="{{ route('pds.references.store') }}" class="grid grid-cols-3 gap-3 items-end mb-6 text-sm bg-gray-50 p-4 rounded">
@csrf

<div>
<label>Name</label>
<input name="name" class="w-full border rounded px-3 py-2">
</div>

<div>
<label>Address</label>
<input name="address" class="w-full border rounded px-3 py-2">
</div>

<div>
<label>Contact No. / Email</label>
<input name="contact_no_email" class="w-full border rounded px-3 py-2">
</div>

<button class="bg-gray-700 text-white rounded px-3 py-2">+ Add</button>

</form>

<table class="w-full border text-sm">
<thead class="bg-gray-100">
<tr>
<th>Name</th>
<th>Address</th>
<th>Contact</th>
<th></th>
</tr>
</thead>

<tbody>
@forelse($user->pdsReferences as $item)
<tr class="border-t">
<td class="px-3 py-2">{{ $item->name }}</td>
<td class="px-3 py-2">{{ $item->address }}</td>
<td class="px-3 py-2">{{ $item->contact_no_email }}</td>

<td class="text-right">
<form action="{{ route('pds.references.destroy',$item) }}" method="POST">
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