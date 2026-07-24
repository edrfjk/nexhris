@php $p = $user->pdsPersonalInformation; @endphp
<form method="POST" action="{{ route('pds.personal.update') }}" class="space-y-4">
    @csrf @method('PUT')
    <input type="hidden" name="next_step" value="{{ $step + 1 }}">

    <div class="grid grid-cols-3 gap-4">
        <div><label class="block text-sm mb-1">Surname</label><input name="surname" value="{{ old('surname', $p->surname ?? '') }}" class="w-full border rounded px-3 py-2 text-sm" required></div>
        <div><label class="block text-sm mb-1">First Name</label><input name="first_name" value="{{ old('first_name', $p->first_name ?? '') }}" class="w-full border rounded px-3 py-2 text-sm" required></div>
        <div><label class="block text-sm mb-1">Middle Name</label><input name="middle_name" value="{{ old('middle_name', $p->middle_name ?? '') }}" class="w-full border rounded px-3 py-2 text-sm"></div>
    </div>

    <div class="grid grid-cols-3 gap-4">
        <div><label class="block text-sm mb-1">Name Extension</label><input name="name_extension" value="{{ old('name_extension', $p->name_extension ?? '') }}" class="w-full border rounded px-3 py-2 text-sm"></div>
        <div><label class="block text-sm mb-1">Date of Birth</label><input type="date" name="date_of_birth" value="{{ old('date_of_birth', optional($p->date_of_birth ?? null)->format('Y-m-d')) }}" class="w-full border rounded px-3 py-2 text-sm" required></div>
        <div><label class="block text-sm mb-1">Place of Birth</label><input name="place_of_birth" value="{{ old('place_of_birth', $p->place_of_birth ?? '') }}" class="w-full border rounded px-3 py-2 text-sm" required></div>
    </div>

    <div class="grid grid-cols-3 gap-4">
        <div>
            <label class="block text-sm mb-1">Sex</label>
            <select name="sex" class="w-full border rounded px-3 py-2 text-sm" required>
                <option value="Male" @selected(old('sex', $p->sex ?? '') === 'Male')>Male</option>
                <option value="Female" @selected(old('sex', $p->sex ?? '') === 'Female')>Female</option>
            </select>
        </div>
        <div>
            <label class="block text-sm mb-1">Civil Status</label>
            <select name="civil_status" class="w-full border rounded px-3 py-2 text-sm" required>
                @foreach (['Single','Married','Widowed','Separated','Solo Parent','Others'] as $status)
                    <option value="{{ $status }}" @selected(old('civil_status', $p->civil_status ?? '') === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </div>
        <div><label class="block text-sm mb-1">Blood Type</label><input name="blood_type" value="{{ old('blood_type', $p->blood_type ?? '') }}" class="w-full border rounded px-3 py-2 text-sm"></div>
    </div>

    <div class="grid grid-cols-3 gap-4">
        <div><label class="block text-sm mb-1">Height (m)</label><input type="number" step="0.01" name="height_m" value="{{ old('height_m', $p->height_m ?? '') }}" class="w-full border rounded px-3 py-2 text-sm"></div>
        <div><label class="block text-sm mb-1">Weight (kg)</label><input type="number" step="0.01" name="weight_kg" value="{{ old('weight_kg', $p->weight_kg ?? '') }}" class="w-full border rounded px-3 py-2 text-sm"></div>
        <div><label class="block text-sm mb-1">Citizenship</label><input name="citizenship" value="{{ old('citizenship', $p->citizenship ?? 'Filipino') }}" class="w-full border rounded px-3 py-2 text-sm" required></div>
    </div>

    <h3 class="font-semibold text-sm text-maroon-800 pt-2">Government IDs</h3>
    <div class="grid grid-cols-4 gap-4">
        <div><label class="block text-sm mb-1">GSIS/UMID No.</label><input name="gsis_umid_no" value="{{ old('gsis_umid_no', $p->gsis_umid_no ?? '') }}" class="w-full border rounded px-3 py-2 text-sm"></div>
        <div><label class="block text-sm mb-1">Pag-IBIG No.</label><input name="pagibig_no" value="{{ old('pagibig_no', $p->pagibig_no ?? '') }}" class="w-full border rounded px-3 py-2 text-sm"></div>
        <div><label class="block text-sm mb-1">PhilHealth No.</label><input name="philhealth_no" value="{{ old('philhealth_no', $p->philhealth_no ?? '') }}" class="w-full border rounded px-3 py-2 text-sm"></div>
        <div><label class="block text-sm mb-1">SSS No.</label><input name="sss_no" value="{{ old('sss_no', $p->sss_no ?? '') }}" class="w-full border rounded px-3 py-2 text-sm"></div>
        <div><label class="block text-sm mb-1">PhilSys (PSN)</label><input name="psn_no" value="{{ old('psn_no', $p->psn_no ?? '') }}" class="w-full border rounded px-3 py-2 text-sm"></div>
        <div><label class="block text-sm mb-1">TIN No.</label><input name="tin_no" value="{{ old('tin_no', $p->tin_no ?? '') }}" class="w-full border rounded px-3 py-2 text-sm"></div>
        <div><label class="block text-sm mb-1">Agency Employee No.</label><input name="agency_employee_no" value="{{ old('agency_employee_no', $p->agency_employee_no ?? '') }}" class="w-full border rounded px-3 py-2 text-sm"></div>
    </div>

    <h3 class="font-semibold text-sm text-maroon-800 pt-2">Residential Address</h3>
    <div class="grid grid-cols-3 gap-4">
        <input name="res_house_block_lot" placeholder="House/Block/Lot No." value="{{ old('res_house_block_lot', $p->res_house_block_lot ?? '') }}" class="border rounded px-3 py-2 text-sm">
        <input name="res_street" placeholder="Street" value="{{ old('res_street', $p->res_street ?? '') }}" class="border rounded px-3 py-2 text-sm">
        <input name="res_subdivision_village" placeholder="Subdivision/Village" value="{{ old('res_subdivision_village', $p->res_subdivision_village ?? '') }}" class="border rounded px-3 py-2 text-sm">
        <input name="res_barangay" placeholder="Barangay" value="{{ old('res_barangay', $p->res_barangay ?? '') }}" class="border rounded px-3 py-2 text-sm">
        <input name="res_city_municipality" placeholder="City/Municipality" value="{{ old('res_city_municipality', $p->res_city_municipality ?? '') }}" class="border rounded px-3 py-2 text-sm">
        <input name="res_province" placeholder="Province" value="{{ old('res_province', $p->res_province ?? '') }}" class="border rounded px-3 py-2 text-sm">
        <input name="res_zip_code" placeholder="ZIP Code" value="{{ old('res_zip_code', $p->res_zip_code ?? '') }}" class="border rounded px-3 py-2 text-sm">
    </div>

    <h3 class="font-semibold text-sm text-maroon-800 pt-2">Permanent Address</h3>
    <div class="grid grid-cols-3 gap-4">
        <input name="perm_house_block_lot" placeholder="House/Block/Lot No." value="{{ old('perm_house_block_lot', $p->perm_house_block_lot ?? '') }}" class="border rounded px-3 py-2 text-sm">
        <input name="perm_street" placeholder="Street" value="{{ old('perm_street', $p->perm_street ?? '') }}" class="border rounded px-3 py-2 text-sm">
        <input name="perm_subdivision_village" placeholder="Subdivision/Village" value="{{ old('perm_subdivision_village', $p->perm_subdivision_village ?? '') }}" class="border rounded px-3 py-2 text-sm">
        <input name="perm_barangay" placeholder="Barangay" value="{{ old('perm_barangay', $p->perm_barangay ?? '') }}" class="border rounded px-3 py-2 text-sm">
        <input name="perm_city_municipality" placeholder="City/Municipality" value="{{ old('perm_city_municipality', $p->perm_city_municipality ?? '') }}" class="border rounded px-3 py-2 text-sm">
        <input name="perm_province" placeholder="Province" value="{{ old('perm_province', $p->perm_province ?? '') }}" class="border rounded px-3 py-2 text-sm">
        <input name="perm_zip_code" placeholder="ZIP Code" value="{{ old('perm_zip_code', $p->perm_zip_code ?? '') }}" class="border rounded px-3 py-2 text-sm">
    </div>

    <div class="grid grid-cols-3 gap-4 pt-2">
        <div><label class="block text-sm mb-1">Telephone No.</label><input name="telephone_no" value="{{ old('telephone_no', $p->telephone_no ?? '') }}" class="w-full border rounded px-3 py-2 text-sm"></div>
        <div><label class="block text-sm mb-1">Mobile No.</label><input name="mobile_no" value="{{ old('mobile_no', $p->mobile_no ?? '') }}" class="w-full border rounded px-3 py-2 text-sm"></div>
        <div><label class="block text-sm mb-1">Email Address</label><input type="email" name="email_address" value="{{ old('email_address', $p->email_address ?? '') }}" class="w-full border rounded px-3 py-2 text-sm"></div>
    </div>

    <button class="bg-maroon-800 text-white px-6 py-2 rounded text-sm hover:bg-maroon-900">Save & Continue →</button>
</form>