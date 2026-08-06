@extends('layouts.app')
@section('title', 'Add Employee')

@section('content')
<x-page-header title="Add Employee" subtitle="Create a new employee account. Credentials will be shared by HR.">
    <x-slot:actions>
        <a href="{{ route('admin.employees.index') }}"
           class="inline-flex items-center gap-1.5 text-sm text-gray-600 border border-gray-300 rounded-lg px-3 py-2 hover:bg-gray-50 transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            Back
        </a>
    </x-slot:actions>
</x-page-header>

<form method="POST" action="{{ route('admin.employees.store') }}" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    @csrf

    <!-- Left: photo upload -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 text-center sticky top-20">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-4">ID Photo</p>

            <div x-data="{ preview: null }">
                <label for="photo-input" class="cursor-pointer group block">
                    <div class="w-32 h-32 mx-auto rounded-full border-2 border-dashed border-gray-300 group-hover:border-maroon-700 overflow-hidden bg-gray-50 flex items-center justify-center transition">
                        <template x-if="!preview">
                            <svg class="w-10 h-10 text-gray-300 group-hover:text-maroon-700 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                            </svg>
                        </template>
                        <img x-show="preview" :src="preview" class="w-full h-full object-cover">
                    </div>
                    <p class="text-xs text-maroon-800 font-medium mt-3 group-hover:underline">Click to upload photo</p>
                </label>
                <input id="photo-input" type="file" name="photo" accept="image/*" class="hidden"
                       @change="preview = $refs.photoInput.files[0] ? URL.createObjectURL($refs.photoInput.files[0]) : null"
                       x-ref="photoInput">
            </div>

            <p class="text-xs text-gray-400 mt-4">JPG or PNG. Max 2MB. This photo will appear on the employee's Digital ID.</p>
            @error('photo') <p class="text-red-600 text-xs mt-2">{{ $message }}</p> @enderror
        </div>
    </div>

    <!-- Right: form fields -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-800">Employee Information</h3>
                <p class="text-xs text-gray-400 mt-0.5">Fill in the new employee's details and initial login credentials.</p>
            </div>

            <div class="p-6 space-y-6">
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Identity</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Employee Number</label>
                            <input type="text" name="employee_number" value="{{ old('employee_number') }}"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
                            @error('employee_number') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                            <input type="text" name="name" value="{{ old('name') }}"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
                            @error('name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <div class="pt-5 border-t border-gray-100">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Contact</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" name="email" value="{{ old('email') }}"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
                            @error('email') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Contact Number</label>
                            <input type="text" name="contact_number" value="{{ old('contact_number') }}"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
                        </div>
                    </div>
                </div>

                <div class="pt-5 border-t border-gray-100">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Employment</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Position</label>
                            <input type="text" name="position" value="{{ old('position') }}"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
                        </div>
                        <div></div>
                        @include('admin.employees.partials.college-program-fields', ['employee' => null])
                    </div>
                </div>

                <div class="pt-5 border-t border-gray-100">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Login Credentials</p>
                    <p class="text-xs text-gray-400 mb-3">Share this temporary password with the employee securely.</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Temporary Password</label>
                            <input type="password" name="password"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
                            @error('password') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                            <input type="password" name="password_confirmation"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-xl">
                <a href="{{ route('admin.employees.index') }}" class="px-4 py-2 rounded-lg text-sm border border-gray-300 hover:bg-white transition">Cancel</a>
                <button class="bg-maroon-800 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-maroon-900 transition">Save Employee</button>
            </div>
        </div>
    </div>
</form>
@endsection