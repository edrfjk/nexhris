{{-- ================= CHILDREN ================= --}}
<h3 class="font-semibold text-sm text-maroon-800 mb-3">Children</h3>

<form method="POST" action="{{ route('pds.children.store') }}" class="grid grid-cols-3 gap-3 items-end mb-4 text-sm">
    @csrf

    <div>
        <label class="block mb-1">Full Name</label>
        <input name="full_name" class="w-full border rounded px-3 py-2" required>
    </div>

    <div>
        <label class="block mb-1">Date of Birth</label>
        <input type="date" name="date_of_birth" class="w-full border rounded px-3 py-2" required>
    </div>

    <button type="submit" class="bg-gray-700 text-white rounded px-3 py-2 w-fit">
        + Add Child
    </button>
</form>

<table class="w-full text-sm border mb-8">
    <thead class="bg-gray-100">
        <tr>
            <th class="px-3 py-2 text-left">Name</th>
            <th class="px-3 py-2 text-left">Date of Birth</th>
            <th></th>
        </tr>
    </thead>

    <tbody>
        @forelse ($user->pdsChildren as $child)
            <tr class="border-t">
                <td class="px-3 py-2">{{ $child->full_name }}</td>
                <td class="px-3 py-2">{{ $child->date_of_birth->format('M d, Y') }}</td>
                <td class="px-3 py-2 text-right">
                    <form action="{{ route('pds.children.destroy', $child) }}"
                          method="POST"
                          onsubmit="return confirm('Remove this record?')">
                        @csrf
                        @method('DELETE')
                        <button class="text-red-600 text-xs">Remove</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="3" class="px-3 py-4 text-center text-gray-400">
                    No children added.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>

<hr class="my-6">

{{-- ================= FAMILY BACKGROUND ================= --}}
@php $f = $user->pdsFamilyBackground; @endphp

<form method="POST" action="{{ route('pds.family.update') }}" class="space-y-4">
    @csrf
    @method('PUT')

    <input type="hidden" name="next_step" value="{{ $step + 1 }}">

    <h3 class="font-semibold text-sm text-maroon-800">Spouse</h3>

    <div class="grid grid-cols-4 gap-4">
        <input name="spouse_surname" placeholder="Surname"
               value="{{ old('spouse_surname', $f->spouse_surname ?? '') }}"
               class="border rounded px-3 py-2 text-sm">

        <input name="spouse_first_name" placeholder="First Name"
               value="{{ old('spouse_first_name', $f->spouse_first_name ?? '') }}"
               class="border rounded px-3 py-2 text-sm">

        <input name="spouse_middle_name" placeholder="Middle Name"
               value="{{ old('spouse_middle_name', $f->spouse_middle_name ?? '') }}"
               class="border rounded px-3 py-2 text-sm">

        <input name="spouse_name_extension" placeholder="Ext. (Jr, Sr)"
               value="{{ old('spouse_name_extension', $f->spouse_name_extension ?? '') }}"
               class="border rounded px-3 py-2 text-sm">

        <input name="spouse_occupation" placeholder="Occupation"
               value="{{ old('spouse_occupation', $f->spouse_occupation ?? '') }}"
               class="border rounded px-3 py-2 text-sm">

        <input name="spouse_employer_business_name" placeholder="Employer/Business Name"
               value="{{ old('spouse_employer_business_name', $f->spouse_employer_business_name ?? '') }}"
               class="border rounded px-3 py-2 text-sm">

        <input name="spouse_business_address" placeholder="Business Address"
               value="{{ old('spouse_business_address', $f->spouse_business_address ?? '') }}"
               class="border rounded px-3 py-2 text-sm">

        <input name="spouse_telephone_no" placeholder="Telephone No."
               value="{{ old('spouse_telephone_no', $f->spouse_telephone_no ?? '') }}"
               class="border rounded px-3 py-2 text-sm">
    </div>

    <h3 class="font-semibold text-sm text-maroon-800 pt-2">Father</h3>

    <div class="grid grid-cols-4 gap-4">
        <input name="father_surname" placeholder="Surname"
               value="{{ old('father_surname', $f->father_surname ?? '') }}"
               class="border rounded px-3 py-2 text-sm">

        <input name="father_first_name" placeholder="First Name"
               value="{{ old('father_first_name', $f->father_first_name ?? '') }}"
               class="border rounded px-3 py-2 text-sm">

        <input name="father_middle_name" placeholder="Middle Name"
               value="{{ old('father_middle_name', $f->father_middle_name ?? '') }}"
               class="border rounded px-3 py-2 text-sm">

        <input name="father_name_extension" placeholder="Ext. (Jr, Sr)"
               value="{{ old('father_name_extension', $f->father_name_extension ?? '') }}"
               class="border rounded px-3 py-2 text-sm">
    </div>

    <h3 class="font-semibold text-sm text-maroon-800 pt-2">
        Mother's Maiden Name
    </h3>

    <div class="grid grid-cols-3 gap-4">
        <input name="mother_maiden_surname" placeholder="Surname"
               value="{{ old('mother_maiden_surname', $f->mother_maiden_surname ?? '') }}"
               class="border rounded px-3 py-2 text-sm">

        <input name="mother_first_name" placeholder="First Name"
               value="{{ old('mother_first_name', $f->mother_first_name ?? '') }}"
               class="border rounded px-3 py-2 text-sm">

        <input name="mother_middle_name" placeholder="Middle Name"
               value="{{ old('mother_middle_name', $f->mother_middle_name ?? '') }}"
               class="border rounded px-3 py-2 text-sm">
    </div>

    <button type="submit"
            class="bg-maroon-800 text-white px-6 py-2 rounded text-sm hover:bg-maroon-900">
        Save & Continue →
    </button>
</form>