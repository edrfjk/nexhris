<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Barryvdh\DomPDF\Facade\Pdf;


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
        ->when($request->sort, function ($q, $sort) {
            match ($sort) {
                'newest' => $q->orderByDesc('created_at'),
                'oldest' => $q->orderBy('created_at'),
                'employee_number' => $q->orderBy('employee_number'),
                default => $q->orderBy('name'),
            };
        }, fn ($q) => $q->orderBy('name'))
        ->paginate(10)
        ->withQueryString();

    $colleges = config('colleges');

    // Global counts, unaffected by the current search/filter — same idea as
    // $pendingCount on the leave page.
    $activeCount      = User::where('role', 'employee')->where('status', 'active')->count();
    $inactiveCount    = User::where('role', 'employee')->where('status', 'inactive')->count();
    $newThisMonthCount = User::where('role', 'employee')
        ->whereMonth('created_at', now()->month)
        ->whereYear('created_at', now()->year)
        ->count();

    return view('admin.employees.index', compact(
        'employees', 'colleges', 'activeCount', 'inactiveCount', 'newThisMonthCount'
    ));
}

public function exportPdf(Request $request)
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
        ->when($request->sort, function ($q, $sort) {
            match ($sort) {
                'newest' => $q->orderByDesc('created_at'),
                'oldest' => $q->orderBy('created_at'),
                'employee_number' => $q->orderBy('employee_number'),
                default => $q->orderBy('name'),
            };
        }, fn ($q) => $q->orderBy('name'))
        ->get();

    $colleges = config('colleges');

    $filtersApplied = collect([
        'Search' => $request->search,
        'College/Office' => $request->college ? ($colleges[$request->college]['name'] ?? $request->college) : null,
        'Status' => $request->status ? ucfirst($request->status) : null,
    ])->filter();

    $pdf = Pdf::loadView('admin.employees.pdf', [
        'employees' => $employees,
        'filtersApplied' => $filtersApplied,
        'generatedAt' => now(),
        'generatedBy' => auth()->user()->name ?? 'Admin',
    ])->setPaper('a4', 'landscape');

    // stream() sends Content-Disposition: inline, so the browser previews the
    // PDF in the new tab instead of forcing a save dialog. The user can still
    // save it from the browser's own PDF viewer (Ctrl+S / download icon).
    return $pdf->stream('employee-directory-' . now()->format('Y-m-d') . '.pdf');
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