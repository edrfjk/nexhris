@php
    $o = $user->pdsOtherInformation;
    $toText = fn ($arr) => is_array($arr) ? implode("\n", $arr) : '';
@endphp

<form method="POST" action="{{ route('pds.other.update') }}" class="space-y-4">
    @csrf @method('PUT')
    <input type="hidden" name="next_step" value="{{ $step + 1 }}">

    <p class="text-xs text-gray-500 mb-2">Enter one item per line for each box below.</p>

    <div>
        <label class="block text-sm font-medium mb-1">Special Skills and Hobbies</label>
        <textarea name="special_skills_hobbies" rows="4" class="w-full border rounded px-3 py-2 text-sm"
        >{{ old('special_skills_hobbies', $toText($o->special_skills_hobbies ?? [])) }}</textarea>
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Non-Academic Distinctions / Recognition</label>
        <textarea name="non_academic_distinctions" rows="4" class="w-full border rounded px-3 py-2 text-sm"
        >{{ old('non_academic_distinctions', $toText($o->non_academic_distinctions ?? [])) }}</textarea>
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Membership in Association/Organization</label>
        <textarea name="membership_associations" rows="4" class="w-full border rounded px-3 py-2 text-sm"
        >{{ old('membership_associations', $toText($o->membership_associations ?? [])) }}</textarea>
    </div>

    <button class="bg-maroon-800 text-white px-6 py-2 rounded text-sm hover:bg-maroon-900">Save & Continue →</button>
</form>