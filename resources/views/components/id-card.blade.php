@props(['employee', 'uploadRoute' => null])

<div class="max-w-sm mx-auto">
    <div class="bg-white rounded-lg shadow-lg border border-sand-200 overflow-hidden">
        <!-- Header strip -->
        <div class="bg-maroon-800 px-5 py-3 flex items-center gap-2.5">
            <img src="{{ asset('images/ispsc-logo.png') }}" class="w-9 h-9 rounded-full object-cover flex-shrink-0">
            <div class="leading-tight text-white">
                <p class="text-xs font-bold">ISPSC TAGUDIN CAMPUS</p>
                <p class="text-[10px] text-sand-300">Employee Identification Card</p>
            </div>
        </div>

        <!-- Photo -->
        <div class="flex flex-col items-center pt-6 pb-4 px-5">
            <div class="w-28 h-28 rounded-full border-4 border-maroon-800 overflow-hidden bg-sand-100 flex items-center justify-center">
                @if ($employee->profile_photo_path)
                    <img src="{{ asset('storage/' . $employee->profile_photo_path) }}" class="w-full h-full object-cover">
                @else
                    <span class="text-3xl font-bold text-sand-400">{{ strtoupper(substr($employee->name, 0, 1)) }}</span>
                @endif
            </div>

            <h2 class="mt-3 text-lg font-bold text-sand-800 text-center">{{ $employee->name }}</h2>
            <p class="text-sm text-maroon-800 font-medium">{{ $employee->position ?: 'Employee' }}</p>
        </div>

        <!-- Details -->
        <div class="px-6 pb-6 space-y-2 text-sm border-t border-sand-100 pt-4">
            <div class="flex justify-between">
                <span class="text-sand-400">Employee No.</span>
                <span class="font-medium text-sand-700">{{ $employee->employee_number ?: '—' }}</span>
            </div>
            <div class="flex justify-between gap-3">
                <span class="text-sand-400 shrink-0">College / Office</span>
                <span class="font-medium text-sand-700 text-right">
                    {{ $employee->college->name ?? $employee->department ?: '—' }}
                </span>
            </div>
            <div class="flex justify-between">
                <span class="text-sand-400">Status</span>
                <x-badge :color="$employee->status === 'active' ? 'green' : 'gray'">{{ ucfirst($employee->status) }}</x-badge>
            </div>
            <div class="flex justify-between">
                <span class="text-sand-400">Contact No.</span>
                <span class="font-medium text-sand-700">{{ $employee->contact_number ?: '—' }}</span>
            </div>
        </div>

        <!-- Verification QR -->
        @if ($employee->verification_token)
            @php $verifyUrl = route('verify.show', $employee->verification_token); @endphp
            <div class="px-6 pb-5 pt-4 border-t border-sand-100 flex items-center gap-4">
                <div class="shrink-0 p-1.5 bg-white border border-sand-200 rounded">
                    {{-- Scanning this reaches a public page showing only name,
                         position, college and active status. --}}
                    {!! SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(84)->margin(0)->generate($verifyUrl) !!}
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold text-sand-700">Scan to verify</p>
                    <p class="text-[10px] text-sand-500 leading-relaxed mt-0.5">
                        Confirms name, position, college and whether this
                        account is active. No other details are shown.
                    </p>
                </div>
            </div>
        @endif

        <!-- Footer -->
        <div class="bg-sand-50 px-6 py-2.5 text-center border-t border-sand-100">
            <p class="text-[10px] text-sand-400">This ID is property of ISPSC Tagudin Campus. If found, please return.</p>
        </div>
    </div>

    @if ($uploadRoute)
        <form method="POST" action="{{ $uploadRoute }}" enctype="multipart/form-data" class="card mt-4 p-4">
            @csrf
            <label class="label">Update Photo</label>
            <input type="file" name="photo" accept="image/*" required class="file-input mb-3">
            <button class="w-full bg-maroon-800 text-white py-2 rounded-lg text-sm font-medium hover:bg-maroon-900 transition">
                Upload Photo
            </button>
        </form>
    @endif
</div>