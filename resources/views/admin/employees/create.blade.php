@extends('layouts.app')
@section('title', 'Add Employee')

@section('content')
<form method="POST" action="{{ route('admin.employees.store') }}" class="bg-white rounded shadow p-6 max-w-2xl space-y-4">
    @csrf

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">Employee Number</label>
            <input type="text" name="employee_number" value="{{ old('employee_number') }}"
                   class="w-full border rounded px-3 py-2 text-sm">
            @error('employee_number') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Full Name</label>
            <input type="text" name="name" value="{{ old('name') }}"
                   class="w-full border rounded px-3 py-2 text-sm">
            @error('name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Email</label>
            <input type="email" name="email" value="{{ old('email') }}"
                   class="w-full border rounded px-3 py-2 text-sm">
            @error('email') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Contact Number</label>
            <input type="text" name="contact_number" value="{{ old('contact_number') }}"
                   class="w-full border rounded px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Position</label>
            <input type="text" name="position" value="{{ old('position') }}"
                   class="w-full border rounded px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Department</label>
            <input type="text" name="department" value="{{ old('department') }}"
                   class="w-full border rounded px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Temporary Password</label>
            <input type="password" name="password"
                   class="w-full border rounded px-3 py-2 text-sm">
            @error('password') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Confirm Password</label>
            <input type="password" name="password_confirmation"
                   class="w-full border rounded px-3 py-2 text-sm">
        </div>
    </div>

    <div class="flex gap-2 pt-2">
        <button class="bg-maroon-800 text-white px-4 py-2 rounded text-sm hover:bg-maroon-900">Save Employee</button>
        <a href="{{ route('admin.employees.index') }}" class="px-4 py-2 rounded text-sm border">Cancel</a>
    </div>
</form>
@endsection