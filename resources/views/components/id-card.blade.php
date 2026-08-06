@props(['employee', 'uploadRoute' => null])

<div class="max-w-sm mx-auto">
    <div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden">
        <!-- Header strip -->
        <div class="bg-gradient-to-r from-maroon-900 via-maroon-800 to-maroon-900 px-5 py-3 flex items-center gap-2.5">
            <img src="{{ asset('images/ispsc-logo.png') }}" class="w-9 h-9 rounded-full object-cover flex-shrink-0">
            <div class="leading-tight text-white">
                <p class="text-xs font-bold">ISPSC TAGUDIN CAMPUS</p>
                <p class="text-[10px] text-gray-300">Employee Identification Card</p>
            </div>
        </div>

        <!-- Photo -->
        <div class="flex flex-col items-center pt-6 pb-4 px-5">
            <div class="w-28 h-28 rounded-full border-4 border-maroon-800 overflow-hidden bg-gray-100 flex items-center justify-center">
                @if ($employee->profile_photo_path)
                    <img src="{{ asset('storage/' . $employee->profile_photo_path) }}" class="w-full h-full object-cover">
                @else
                    <span class="text-3xl font-bold text-gray-400">{{ strtoupper(substr($employee->name, 0, 1)) }}</span>
                @endif
            </div>

            <h2 class="mt-3 text-lg font-bold text-gray-800 text-center">{{ $employee->name }}</h2>
            <p class="text-sm text-maroon-800 font-medium">{{ $employee->position ?: 'Employee' }}</p>
        </div>

        <!-- Details -->
        <div class="px-6 pb-6 space-y-2 text-sm border-t border-gray-100 pt-4">
            <div class="flex justify-between">
                <span class="text-gray-400">Employee No.</span>
                <span class="font-medium text-gray-700">{{ $employee->employee_number ?: '—' }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-400">Department</span>
                <span class="font-medium text-gray-700">{{ $employee->department ?: '—' }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-400">Status</span>
                <x-badge :color="$employee->status === 'active' ? 'green' : 'gray'">{{ ucfirst($employee->status) }}</x-badge>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-400">Contact No.</span>
                <span class="font-medium text-gray-700">{{ $employee->contact_number ?: '—' }}</span>
            </div>
        </div>

        <!-- Footer -->
        <div class="bg-gray-50 px-6 py-2.5 text-center border-t border-gray-100">
            <p class="text-[10px] text-gray-400">This ID is property of ISPSC Tagudin Campus. If found, please return.</p>
        </div>
    </div>

    @if ($uploadRoute)
        <form method="POST" action="{{ $uploadRoute }}" enctype="multipart/form-data" class="mt-4 bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            @csrf
            <label class="block text-sm font-medium text-gray-700 mb-2">Update Photo</label>
            <input type="file" name="photo" accept="image/*" required class="text-sm mb-3">
            <button class="w-full bg-maroon-800 text-white py-2 rounded-lg text-sm font-medium hover:bg-maroon-900 transition">
                Upload Photo
            </button>
        </form>
    @endif
</div>