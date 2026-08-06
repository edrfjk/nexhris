<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;


class EmployeeController extends Controller
{
public function index(Request $request)
{
    $employees = User::where('role', 'employee')
        ->when($request->search, function ($query, $search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('employee_number', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        })
        ->when($request->college, fn ($q, $college) => $q->where('department', $college))
        ->when($request->program, fn ($q, $program) => $q->where('program', $program))
        ->when($request->status, fn ($q, $status) => $q->where('status', $status))
        ->orderBy('name')
        ->paginate(10)
        ->withQueryString();

    $colleges = config('colleges');

    return view('admin.employees.index', compact('employees', 'colleges'));
}

    public function create()
    {
        $colleges = config('colleges');

        return view('admin.employees.create', compact('colleges'));
    }

public function store(Request $request)
{
    $validated = $request->validate([
        'employee_number' => ['required', 'string', 'unique:users,employee_number'],
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'unique:users,email'],
        'position' => ['nullable', 'string', 'max:255'],
        'department' => ['nullable', 'string', 'in:' . implode(',', array_keys(config('colleges')))],
        'program' => ['nullable', 'string', 'max:255'],
        'contact_number' => ['nullable', 'string', 'max:20'],
        'password' => ['required', 'string', 'min:8', 'confirmed'],
        'photo' => ['nullable', 'image', 'max:2048'],
    ]);

    $photo = $request->hasFile('photo') ? $request->file('photo')->store('profile-photos', 'public') : null;

    User::create([
        ...collect($validated)->except('photo')->toArray(),
        'role' => 'employee',
        'status' => 'active',
        'password' => Hash::make($validated['password']),
        'profile_photo_path' => $photo,
    ]);

    return redirect()->route('admin.employees.index')
        ->with('success', 'Employee account created successfully.');
}

public function edit(User $employee)
{
    $colleges = config('colleges');

    return view('admin.employees.edit', compact('employee', 'colleges'));
}

    public function update(Request $request, User $employee)
    {
        $validated = $request->validate([
            'employee_number' => ['required', 'string', Rule::unique('users', 'employee_number')->ignore($employee->id)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($employee->id)],
            'position' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'in:' . implode(',', array_keys(config('colleges')))],
            'program' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $employee->update($validated);

        return redirect()->route('admin.employees.index')
            ->with('success', 'Employee account updated successfully.');
    }

    public function updateStatus(Request $request, User $employee)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $employee->update(['status' => $data['status']]);

        return back()->with('success', "{$employee->name}'s account is now " . $data['status'] . '.');
    }

public function show(User $employee)
{
    $colleges = config('colleges');

    return view('admin.employees.show', compact('employee', 'colleges'));
}

    public function updatePhoto(Request $request, User $employee)
    {
        $request->validate([
            'photo' => ['required', 'image', 'max:2048'],
        ]);

        if ($employee->profile_photo_path) {
            Storage::disk('public')->delete($employee->profile_photo_path);
        }

        $path = $request->file('photo')->store('profile-photos', 'public');
        $employee->update(['profile_photo_path' => $path]);

        return back()->with('success', 'Photo updated successfully.');
    }
}