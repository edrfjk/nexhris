@extends('layouts.app')
@section('title', 'Edit Employee')

@section('content')
<x-page-header title="Edit Employee" :subtitle="$employee->name" />

<x-card>
    <form method="POST" action="{{ route('admin.employees.update', $employee) }}" class="space-y-5">
        @csrf @method('PUT')

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
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                <input type="text" name="department" value="{{ old('department', $employee->department) }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">New Password (optional)</label>
                <input type="password" name="password" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                <input type="password" name="password_confirmation" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
            </div>
        </div>

        <p class="text-xs text-gray-400">Account status (Active/Inactive) is managed from the Employee Accounts list.</p>

        <div class="flex gap-2 pt-2 border-t border-gray-100">
            <button class="bg-maroon-800 text-white px-4 py-2 rounded-lg text-sm hover:bg-maroon-900 transition mt-4">Update Employee</button>
            <a href="{{ route('admin.employees.index') }}" class="px-4 py-2 rounded-lg text-sm border border-gray-300 hover:bg-gray-50 transition mt-4">Cancel</a>
        </div>
    </form>
</x-card>
@endsection