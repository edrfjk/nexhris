@extends('layouts.app')
@section('title', 'Edit Employee')

@section('content')
{{-- Laid out to match Add Employee: the same sections in the same order, so
     the two screens that do the same job do not look like different systems. --}}
<x-page-header title="Edit Employee" :subtitle="$employee->name">
    <x-slot:actions>
        <a href="{{ route('admin.employees.show', $employee) }}" class="btn btn-md btn-secondary">
            <x-heroicon-o-arrow-left class="w-4 h-4" />
            Back
        </a>
    </x-slot:actions>
</x-page-header>

<form method="POST" action="{{ route('admin.employees.update', $employee) }}"
      enctype="multipart/form-data" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    @csrf @method('PUT')

    {{-- Left: whose record this is. Editing a form with no name on it is how
         the wrong person gets changed. --}}
    <div class="lg:col-span-1">
        <div class="card sticky top-20 p-6 text-center">
            <div class="mx-auto h-28 w-28 overflow-hidden rounded-full bg-sand-100 ring-4 ring-white shadow-soft">
                @if ($employee->profile_photo_path)
                    <img src="{{ asset('storage/' . $employee->profile_photo_path) }}"
                         alt="{{ $employee->name }}" class="h-full w-full object-cover">
                @else
                    <div class="flex h-full w-full items-center justify-center text-3xl font-bold text-sand-400">
                        {{ strtoupper(mb_substr($employee->name, 0, 1)) }}
                    </div>
                @endif
            </div>

            <p class="mt-4 font-semibold text-sand-900">{{ $employee->name }}</p>
            <p class="text-xs text-sand-500">{{ $employee->employee_number ?: 'No employee number' }}</p>

            <div class="mt-3 flex items-center justify-center gap-2">
                <x-badge color="maroon">{{ $employee->roleLabel() }}</x-badge>
                <x-badge :color="$employee->status === 'active' ? 'green' : 'gray'">
                    {{ ucfirst($employee->status) }}
                </x-badge>
            </div>

            <div class="mt-5 space-y-2 border-t border-sand-100 pt-5 text-left">
                <div class="flex justify-between gap-3 text-[13px]">
                    <span class="text-sand-400">College</span>
                    <span class="text-right text-sand-700">{{ $employee->collegeName() ?: '—' }}</span>
                </div>
                <div class="flex justify-between gap-3 text-[13px]">
                    <span class="text-sand-400">Department</span>
                    <span class="text-right text-sand-700">{{ $employee->departmentName() ?: '—' }}</span>
                </div>
            </div>

            <div class="mt-5 border-t border-sand-100 pt-5 text-left">
                <label class="label">Profile Photo <span class="font-normal text-sand-400">(optional)</span></label>
                <input type="file" name="photo" accept="image/*" class="file-input w-full text-xs">
                <p class="hint mt-1">JPG or PNG, maximum 2 MB. Leave blank to keep the current photo.</p>
                @error('photo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    {{-- Right: the fields, grouped as on Add Employee. --}}
    <div class="lg:col-span-2">
        <div class="card">
            <div class="border-b border-sand-100 px-6 py-4">
                <h3 class="font-semibold text-sand-800">Employee Information</h3>
                <p class="mt-0.5 text-xs text-sand-400">
                    Changes take effect immediately. The college decides who approves this person's leave.
                </p>
            </div>

            <div class="space-y-6 p-6">
                <div>
                    <p class="section-label mb-3">Identity</p>
                    @php($ledgerName = $employee->nameParts())
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label class="label">Employee Number</label>
                            <input type="text" name="employee_number"
                                   value="{{ old('employee_number', $employee->employee_number) }}"
                                   class="input @error('employee_number') input-error @enderror">
                            @error('employee_number') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">First Name</label>
                            <input type="text" name="first_name" value="{{ old('first_name', $employee->first_name ?: $ledgerName['first']) }}" class="input @error('first_name') input-error @enderror">
                        </div>
                        <div>
                            <label class="label">Middle Name</label>
                            <input type="text" name="middle_name" value="{{ old('middle_name', $employee->middle_name) }}" class="input">
                            <span class="hint">Optional; the ledger uses its initial.</span>
                        </div>
                        <div>
                            <label class="label">Last Name</label>
                            <input type="text" name="last_name" value="{{ old('last_name', $employee->last_name ?: $ledgerName['family']) }}" class="input @error('last_name') input-error @enderror">
                        </div>
                    </div>
                </div>

                <div class="border-t border-sand-100 pt-5">
                    <p class="section-label mb-3">Contact</p>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label">Email</label>
                            <input type="email" name="email" value="{{ old('email', $employee->email) }}"
                                   class="input @error('email') input-error @enderror">
                            @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Contact Number</label>
                            <input type="text" name="contact_number"
                                   value="{{ old('contact_number', $employee->contact_number) }}"
                                   class="input">
                        </div>
                    </div>
                </div>

                <div class="border-t border-sand-100 pt-5">
                    <p class="section-label mb-3">Employment</p>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        @include('admin.employees.partials.position-fields', [
                            'selectedPosition' => old('position', $employee->position),
                            'positionFieldId' => 'edit-employee',
                        ])

                        <div>
                            <label class="label">System Role</label>
                            <select name="role" class="select">
                                <option value="employee" @selected(old('role', $employee->role) === 'employee')>Employee</option>
                                <option value="dean" @selected(old('role', $employee->role) === 'dean')>Dean</option>
                                <option value="campus_director" @selected(old('role', $employee->role) === 'campus_director')>Campus Director</option>
                            </select>
                            <span class="hint">Decides which screens this person sees.</span>
                        </div>

                        <div>
                            <label class="label">First day of government service</label>
                            <input type="date" name="first_day_of_service"
                                   value="{{ old('first_day_of_service', $employee->first_day_of_service?->format('Y-m-d')) }}"
                                   class="input">
                            <span class="hint">Printed on the leave ledger card.</span>
                        </div>

                        @include('admin.employees.partials.college-program-fields', [
                            'employee' => $employee,
                            'organizationFieldId' => 'edit-employee',
                            'positionFieldId' => 'edit-employee',
                        ])
                    </div>
                </div>

                <div class="border-t border-sand-100 pt-5">
                    <p class="section-label mb-1">Reset Password</p>
                    <p class="mb-3 text-xs text-sand-400">
                        Leave both blank to keep the current password. Share a new one securely.
                    </p>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label">New Password</label>
                            <input type="password" name="password"
                                   class="input @error('password') input-error @enderror">
                            @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Confirm New Password</label>
                            <input type="password" name="password_confirmation" class="input">
                        </div>
                    </div>
                </div>

                <p class="hint border-t border-sand-100 pt-5">
                    Whether the account is active is set from the Employee Accounts list.
                </p>
            </div>

            <div class="flex justify-end gap-2 rounded-b-xl border-t border-sand-100 bg-sand-50 px-6 py-4">
                <a href="{{ route('admin.employees.index') }}" class="btn btn-md btn-secondary">Cancel</a>
                <button class="btn btn-lg btn-primary">Save Changes</button>
            </div>
        </div>
    </div>
</form>
@endsection
