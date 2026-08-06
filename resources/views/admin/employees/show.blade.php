@extends('layouts.app')
@section('title', $employee->name)

@section('content')
<x-page-header title="Employee Details">
    <x-slot:actions>
        <a href="{{ route('admin.employees.index') }}"
           class="inline-flex items-center gap-1.5 text-sm text-gray-600 border border-gray-300 rounded-lg px-3 py-2 hover:bg-gray-50 transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            Back
        </a>
    </x-slot:actions>
</x-page-header>

<div x-data="{ tab: 'profile' }">

    <!-- Profile header banner -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-6">
        <div class="h-24 bg-gradient-to-r from-maroon-900 via-maroon-800 to-maroon-900"></div>
        <div class="px-6 pb-6">
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 -mt-12">
                <div class="flex items-end gap-4">
                    <div class="w-24 h-24 rounded-full border-4 border-white bg-gray-100 overflow-hidden shadow-md flex-shrink-0">
                        @if ($employee->profile_photo_path)
                            <img src="{{ asset('storage/' . $employee->profile_photo_path) }}" class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-2xl font-bold text-gray-400">
                                {{ strtoupper(substr($employee->name, 0, 1)) }}
                            </div>
                        @endif
                    </div>
                    <div class="pb-1">
                        <h2 class="text-lg font-bold text-gray-800">{{ $employee->name }}</h2>
                        <p class="text-sm text-gray-500">{{ $employee->position ?: 'Employee' }} · {{ $employee->department ?: 'No department' }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-2 pb-1">
                    <a href="{{ route('admin.pds.show', $employee) }}"
                       class="text-sm text-gray-600 border border-gray-300 rounded-lg px-3 py-2 hover:bg-gray-50 transition">PDS</a>
                    <a href="{{ route('admin.leave.ledger', $employee) }}"
                       class="text-sm text-gray-600 border border-gray-300 rounded-lg px-3 py-2 hover:bg-gray-50 transition">Leave Ledger</a>

                    <form action="{{ route('admin.employees.status.update', $employee) }}" method="POST">
                        @csrf @method('PATCH')
                        <select name="status" onchange="this.form.submit()"
                                class="text-sm font-medium rounded-lg px-3 py-2 border cursor-pointer focus:ring-2 focus:ring-maroon-700 transition
                                {{ $employee->status === 'active' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-gray-50 border-gray-200 text-gray-600' }}">
                            <option value="active" @selected($employee->status === 'active')>● Active</option>
                            <option value="inactive" @selected($employee->status === 'inactive')>● Inactive</option>
                        </select>
                    </form>
                </div>
            </div>

            <!-- Quick stats row -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6 pt-5 border-t border-gray-100 text-sm">
                <div>
                    <p class="text-xs text-gray-400">Employee No.</p>
                    <p class="font-medium text-gray-700">{{ $employee->employee_number }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">Email</p>
                    <p class="font-medium text-gray-700 truncate">{{ $employee->email }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">Contact No.</p>
                    <p class="font-medium text-gray-700">{{ $employee->contact_number ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">Status</p>
                    <x-badge :color="$employee->status === 'active' ? 'green' : 'gray'">{{ ucfirst($employee->status) }}</x-badge>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="flex border-b border-gray-100 px-2">
            <button @click="tab = 'profile'" :class="tab === 'profile' ? 'border-maroon-800 text-maroon-800' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    class="px-4 py-3 text-sm font-medium border-b-2 transition">Profile Details</button>
            <button @click="tab = 'id'" :class="tab === 'id' ? 'border-maroon-800 text-maroon-800' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    class="px-4 py-3 text-sm font-medium border-b-2 transition">Digital ID</button>
            <button @click="tab = 'security'" :class="tab === 'security' ? 'border-maroon-800 text-maroon-800' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    class="px-4 py-3 text-sm font-medium border-b-2 transition">Security</button>
        </div>

        <!-- Tab: Profile Details -->
        <div x-show="tab === 'profile'" x-cloak class="p-6">
            <form method="POST" action="{{ route('admin.employees.update', $employee) }}" class="space-y-5">
                @csrf @method('PUT')
                <input type="hidden" name="_no_password" value="1">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Employee Number</label>
                        <input type="text" name="employee_number" value="{{ old('employee_number', $employee->employee_number) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                        <input type="text" name="name" value="{{ old('name', $employee->name) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" value="{{ old('email', $employee->email) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Contact Number</label>
                        <input type="text" name="contact_number" value="{{ old('contact_number', $employee->contact_number) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Position</label>
                        <input type="text" name="position" value="{{ old('position', $employee->position) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
                    </div>
@include('admin.employees.partials.college-program-fields', ['employee' => $employee])
                </div>

                <div class="flex justify-end pt-4 border-t border-gray-100">
                    <button class="bg-maroon-800 text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-maroon-900 transition">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>

        <!-- Tab: Digital ID -->
        <div x-show="tab === 'id'" x-cloak class="p-6 flex justify-center">
            <div class="w-full max-w-xs">
                <x-id-card :employee="$employee" :upload-route="route('admin.employees.photo.update', $employee)" />
            </div>
        </div>

        <!-- Tab: Security -->
        <div x-show="tab === 'security'" x-cloak class="p-6 max-w-md">
            <p class="text-sm text-gray-500 mb-4">Leave both fields blank to keep the employee's current password.</p>
            <form method="POST" action="{{ route('admin.employees.update', $employee) }}" class="space-y-4">
                @csrf @method('PUT')
                <input type="hidden" name="employee_number" value="{{ $employee->employee_number }}">
                <input type="hidden" name="name" value="{{ $employee->name }}">
                <input type="hidden" name="email" value="{{ $employee->email }}">
                <input type="hidden" name="contact_number" value="{{ $employee->contact_number }}">
                <input type="hidden" name="position" value="{{ $employee->position }}">
                <input type="hidden" name="department" value="{{ $employee->department }}">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                    <input type="password" name="password" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                    <input type="password" name="password_confirmation" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
                </div>
                <button class="bg-maroon-800 text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-maroon-900 transition">
                    Update Password
                </button>
            </form>
        </div>
    </div>
</div>
@endsection