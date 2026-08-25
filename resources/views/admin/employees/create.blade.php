@extends('layouts.app')
@section('title', 'Add Employee')

@section('content')
<x-page-header title="Add Employee" subtitle="Create a new employee account. Credentials will be shared by HR.">
    <x-slot:actions>
        <a href="{{ route('admin.employees.index') }}"
           class="btn btn-md btn-secondary">
            <x-heroicon-o-arrow-left class="w-4 h-4" />
            Back
        </a>
    </x-slot:actions>
</x-page-header>

<form method="POST" action="{{ route('admin.employees.store') }}" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    @csrf

    <!-- Left: photo upload -->
    <div class="lg:col-span-1">
        <div class="card p-6 text-center sticky top-20">
            <p class="section-label mb-4">ID Photo</p>

            <div x-data="{ preview: null }">
                <label for="photo-input" class="cursor-pointer group block">
                    <div class="w-32 h-32 mx-auto rounded-full border-2 border-dashed border-sand-200 group-hover:border-maroon-700 overflow-hidden bg-sand-50 flex items-center justify-center transition">
                        <template x-if="!preview">
                            <x-heroicon-o-user-circle class="w-10 h-10 text-sand-300 group-hover:text-maroon-700 transition" />
                        </template>
                        <img x-show="preview" :src="preview" class="w-full h-full object-cover">
                    </div>
                    <p class="text-xs text-maroon-800 font-medium mt-3 group-hover:underline">Click to upload photo</p>
                </label>
                <input id="photo-input" type="file" name="photo" accept="image/*" class="file-input hidden"
                       @change="preview = $refs.photoInput.files[0] ? URL.createObjectURL($refs.photoInput.files[0]) : null"
                       x-ref="photoInput">
            </div>

            <p class="text-xs text-sand-400 mt-4">JPG or PNG. Max 2MB. This photo will appear on the employee's Digital ID.</p>
            @error('photo') <p class="text-red-600 text-xs mt-2">{{ $message }}</p> @enderror
        </div>
    </div>

    <!-- Right: form fields -->
    <div class="lg:col-span-2">
        <div class="card">
            <div class="px-6 py-4 border-b border-sand-100">
                <h3 class="font-semibold text-sand-800">Employee Information</h3>
                <p class="text-xs text-sand-400 mt-0.5">Fill in the new employee's details and initial login credentials.</p>
            </div>

            <div class="p-6 space-y-6">
                <div>
                    <p class="section-label mb-3">Identity</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="label">Employee Number</label>
                            <input type="text" name="employee_number" value="{{ old('employee_number') }}"
                                   class="input">
                            @error('employee_number') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Full Name</label>
                            <input type="text" name="name" value="{{ old('name') }}"
                                   class="input">
                            @error('name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <div class="pt-5 border-t border-sand-100">
                    <p class="section-label mb-3">Contact</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="label">Email</label>
                            <input type="email" name="email" value="{{ old('email') }}"
                                   class="input">
                            @error('email') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Contact Number</label>
                            <input type="text" name="contact_number" value="{{ old('contact_number') }}"
                                   class="input">
                        </div>
                    </div>
                </div>

                <div class="pt-5 border-t border-sand-100">
                    <p class="section-label mb-3">Employment</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="label">Position</label>
                            <input type="text" name="position" value="{{ old('position') }}"
                                   class="input">
                        </div>
                        <div><label class="label">System Role</label><select name="role" class="select"><option value="employee">Employee</option><option value="dean" @selected(old('role') === 'dean')>Dean</option><option value="campus_director" @selected(old('role') === 'campus_director')>Campus Director</option></select></div>

                        <div>
                            <label class="label">First day of government service</label>
                            <input type="date" name="first_day_of_service" value="{{ old('first_day_of_service') }}"
                                   class="input">
                            <p class="mt-1 text-xs text-sand-400">Printed on the leave ledger card and service record.</p>
                        </div>

                        @include('admin.employees.partials.college-program-fields', ['employee' => null])
                    </div>
                </div>

                <div class="pt-5 border-t border-sand-100">
                    <p class="text-xs font-semibold text-sand-400 uppercase tracking-wider mb-1">Login Credentials</p>
                    <p class="text-xs text-sand-400 mb-3">Share this temporary password with the employee securely.</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="label">Temporary Password</label>
                            <input type="password" name="password"
                                   class="input">
                            @error('password') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Confirm Password</label>
                            <input type="password" name="password_confirmation"
                                   class="input">
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 px-6 py-4 border-t border-sand-100 bg-sand-50 rounded-b-xl">
                <a href="{{ route('admin.employees.index') }}" class="btn btn-md btn-secondary">Cancel</a>
                <button class="btn btn-lg btn-primary">Save Employee</button>
            </div>
        </div>
    </div>
</form>
@endsection
