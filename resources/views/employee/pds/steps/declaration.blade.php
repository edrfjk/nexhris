@php $d = $user->pdsDeclaration; @endphp

<a href="{{ route('pds.download') }}"
   class="inline-block bg-gray-700 text-white px-4 py-2 rounded text-sm hover:bg-gray-800 mb-4">
    📄 Download PDS (Official CS Form 212 Format)
</a>

<form method="POST" action="{{ route('pds.declaration.update') }}" enctype="multipart/form-data" class="space-y-4 mb-8">
    @csrf

    <div class="grid grid-cols-2 gap-6">
        <div>
            <label class="block text-sm font-medium mb-1">2x2 Photo</label>
            @if ($d && $d->photo_path)
                <img src="{{ asset('storage/' . $d->photo_path) }}" class="w-24 h-24 object-cover rounded border mb-2">
            @endif
            <input type="file" name="photo" accept="image/*" class="text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Signature</label>
            @if ($d && $d->signature_path)
                <img src="{{ asset('storage/' . $d->signature_path) }}" class="w-40 h-16 object-contain border rounded mb-2 bg-white">
            @endif
            <input type="file" name="signature" accept="image/*" class="text-sm">
        </div>
    </div>

    <div class="grid grid-cols-3 gap-4">
        <div>
            <label class="block text-sm mb-1">Government Issued ID</label>
            <input name="government_id_type" value="{{ old('government_id_type', $d->government_id_type ?? '') }}" placeholder="e.g. Passport, Driver's License" class="w-full border rounded px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm mb-1">ID/License/Passport No.</label>
            <input name="government_id_no" value="{{ old('government_id_no', $d->government_id_no ?? '') }}" class="w-full border rounded px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm mb-1">Date/Place of Issuance</label>
            <input name="id_issuance_date_place" value="{{ old('id_issuance_date_place', $d->id_issuance_date_place ?? '') }}" class="w-full border rounded px-3 py-2 text-sm">
        </div>
    </div>

    <div class="w-1/3">
        <label class="block text-sm mb-1">Date Accomplished</label>
        <input type="date" name="date_accomplished"
               value="{{ old('date_accomplished', optional($d->date_accomplished ?? null)->format('Y-m-d')) }}"
               class="w-full border rounded px-3 py-2 text-sm">
    </div>

    <button class="bg-maroon-800 text-white px-6 py-2 rounded text-sm hover:bg-maroon-900">Save Declaration</button>
</form>

<hr class="my-6">

@if ($submission->status === 'submitted')
    <div class="bg-blue-50 border border-blue-200 text-blue-800 text-sm rounded px-4 py-3">
        Your PDS for {{ $submission->applicable_year }} was submitted on {{ $submission->submitted_at->format('M d, Y g:i A') }} and is awaiting HR review.
    </div>
@elseif ($submission->status === 'approved')
    <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded px-4 py-3">
        Your PDS for {{ $submission->applicable_year }} has been reviewed and approved by HR.
    </div>
@elseif ($submission->status === 'returned')
    <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded px-4 py-3 mb-3">
        HR returned your PDS for revision: {{ $submission->return_remarks }}
    </div>
@endif

<form method="POST" action="{{ route('pds.submit') }}" onsubmit="return confirm('Submit your PDS to HR for review? Make sure all sections are complete.')">
    @csrf
    <button class="bg-green-700 text-white px-6 py-2 rounded text-sm hover:bg-green-800">
        Submit PDS to HR for Review
    </button>
    <a href="{{ route('pds.step', ['step' => 1]) }}" class="ml-2 text-sm text-gray-500 hover:underline">Review from the beginning</a>
</form>