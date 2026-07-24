@extends('layouts.app')
@section('title', 'Employee Accounts')

@section('content')
<div class="flex justify-between items-center mb-4">
    <form method="GET" class="flex gap-2">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name, ID, or email"
               class="border rounded px-3 py-2 text-sm w-64">
        <button class="bg-gray-200 px-3 py-2 rounded text-sm">Search</button>
    </form>
    <a href="{{ route('admin.employees.create') }}"
       class="bg-maroon-800 text-white px-4 py-2 rounded text-sm hover:bg-maroon-900">
        + Add Employee
    </a>
</div>

<div class="bg-white rounded shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-100 text-left">
            <tr>
                <th class="px-4 py-3">Employee No.</th>
                <th class="px-4 py-3">Name</th>
                <th class="px-4 py-3">Email</th>
                <th class="px-4 py-3">Department</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($employees as $employee)
                <tr class="border-t">
                    <td class="px-4 py-3">{{ $employee->employee_number }}</td>
                    <td class="px-4 py-3">{{ $employee->name }}</td>
                    <td class="px-4 py-3">{{ $employee->email }}</td>
                    <td class="px-4 py-3">{{ $employee->department }}</td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 rounded text-xs {{ $employee->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600' }}">
                            {{ ucfirst($employee->status) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right space-x-2">
                        <a href="{{ route('admin.employees.edit', $employee) }}" class="text-blue-600 hover:underline">Edit</a>
                        <form action="{{ route('admin.employees.destroy', $employee) }}" method="POST" class="inline"
                              onsubmit="return confirm('Delete this employee account?')">
                            @csrf @method('DELETE')
                            <button class="text-red-600 hover:underline">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">No employees found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $employees->links() }}</div>
@endsection