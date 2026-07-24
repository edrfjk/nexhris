<form method="POST" action="{{ route('pds.work.store') }}" class="grid grid-cols-4 gap-3 items-end mb-6 text-sm bg-gray-50 p-4 rounded">
@csrf

<div>
<label class="block mb-1">From</label>
<input type="date" name="date_from" class="w-full border rounded px-3 py-2">
</div>

<div>
<label class="block mb-1">To</label>
<input type="date" name="date_to" class="w-full border rounded px-3 py-2">
</div>

<div>
<label class="block mb-1">Position</label>
<input name="position_title" class="w-full border rounded px-3 py-2">
</div>

<div>
<label class="block mb-1">Office</label>
<input name="department_agency_office_company" class="w-full border rounded px-3 py-2">
</div>

<div>
<label class="block mb-1">Salary</label>
<input name="monthly_salary" class="w-full border rounded px-3 py-2">
</div>

<div>
<label class="block mb-1">Salary Grade</label>
<input name="salary_grade" class="w-full border rounded px-3 py-2">
</div>

<div>
<label class="block mb-1">Appointment Status</label>
<input name="status_of_appointment" class="w-full border rounded px-3 py-2">
</div>

<div class="flex items-center gap-2">
<input type="checkbox" name="is_government_service" value="1">
<label>Government Service</label>
</div>

<button class="bg-gray-700 text-white rounded px-3 py-2">+ Add</button>
</form>

<table class="w-full text-sm border">
<thead class="bg-gray-100">
<tr>
<th>Position</th>
<th>Office</th>
<th>From-To</th>
<th></th>
</tr>
</thead>

<tbody>
@forelse($user->pdsWorkExperiences as $item)
<tr class="border-t">
<td class="px-3 py-2">{{ $item->position_title }}</td>
<td class="px-3 py-2">{{ $item->department_agency_office_company }}</td>
<td class="px-3 py-2">{{ $item->date_from }} - {{ $item->date_to }}</td>

<td class="text-right">
<form action="{{ route('pds.work.destroy',$item) }}" method="POST">
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