@extends('layouts.app')
@section('title', 'Edit Employee')

@section('content')
<x-page-header title="Edit Employee" :subtitle="$employee->name" />

<x-card>
    <form method="POST" action="{{ route('admin.employees.update', $employee) }}" class="space-y-5">
        @csrf @method('PUT')

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="label">Employee Number</label>
                <input type="text" name="employee_number" value="{{ old('employee_number', $employee->employee_number) }}"
                       class="input">
            </div>
            <div>
                <label class="label">Full Name</label>
                <input type="text" name="name" value="{{ old('name', $employee->name) }}"
                       class="input">
            </div>
            <div>
                <label class="label">Email</label>
                <input type="email" name="email" value="{{ old('email', $employee->email) }}"
                       class="input">
            </div>
            <div>
                <label class="label">Contact Number</label>
                <input type="text" name="contact_number" value="{{ old('contact_number', $employee->contact_number) }}"
                       class="input">
            </div>
            <div>
                <label class="label">Position</label>
                <input type="text" name="position" value="{{ old('position', $employee->position) }}"
                       class="input">
                </div>
                <div><label class="label">System Role</label><select name="role" class="select"><option value="employee" @selected(old('role', $employee->role) === 'employee')>Employee</option><option value="dean" @selected(old('role', $employee->role) === 'dean')>Dean</option><option value="campus_director" @selected(old('role', $employee->role) === 'campus_director')>Campus Director</option></select></div>

            <div>
                <label class="label">First day of government service</label>
                <input type="date" name="first_day_of_service"
                       value="{{ old('first_day_of_service', $employee->first_day_of_service?->format('Y-m-d')) }}"
                       class="input">
                <p class="mt-1 text-xs text-sand-400">Printed on the leave ledger card and service record.</p>
            </div>

            @include('admin.employees.partials.college-program-fields', ['employee' => $employee])
            <div>
                <label class="label">New Password (optional)</label>
                <input type="password" name="password" class="input">
            </div>
            <div>
                <label class="label">Confirm New Password</label>
                <input type="password" name="password_confirmation" class="input">
            </div>
        </div>

        <p class="text-xs text-sand-400">Account status (Active/Inactive) is managed from the Employee Accounts list.</p>

        <div class="flex gap-2 pt-2 border-t border-sand-100">
            <button class="btn btn-md btn-primary mt-4">Update Employee</button>
            <a href="{{ route('admin.employees.index') }}" class="btn btn-md btn-secondary mt-4">Cancel</a>
        </div>
    </form>
</x-card>
@endsection
