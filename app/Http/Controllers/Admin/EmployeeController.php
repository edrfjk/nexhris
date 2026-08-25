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
    $employees = User::with(['college', 'departmentRecord'])->whereIn('role', ['employee', 'dean', 'campus_director'])
        ->when($request->search, function ($query, $search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('employee_number', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        })
        ->when($request->college, fn ($q, $college) => $q->where('college_id', $college))
        // A Dean may only browse their own college's staff.
        ->visibleTo($request->user())
        ->when($request->department, fn ($q, $id) => $q->where('department_id', $id))
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

    $colleges = \App\Models\College::active()->with('activeDepartments')->orderBy('name')->get();

    // Global counts, unaffected by the current search/filter — same idea as
    // $pendingCount on the leave page.
    $activeCount      = User::whereIn('role', ['employee', 'dean', 'campus_director'])->where('status', 'active')->count();
    $inactiveCount    = User::whereIn('role', ['employee', 'dean', 'campus_director'])->where('status', 'inactive')->count();
    $newThisMonthCount = User::whereIn('role', ['employee', 'dean', 'campus_director'])
        ->whereMonth('created_at', now()->month)
        ->whereYear('created_at', now()->year)
        ->count();

    return view('admin.employees.index', compact(
        'employees', 'colleges', 'activeCount', 'inactiveCount', 'newThisMonthCount'
    ));
}

public function exportPdf(Request $request)
{
    $employees = User::whereIn('role', ['employee', 'dean', 'campus_director'])
        ->when($request->search, function ($query, $search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('employee_number', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        })
        ->when($request->college, fn ($q, $college) => $q->where('college_id', $college))
        // A Dean may only browse their own college's staff.
        ->visibleTo($request->user())
        ->when($request->department, fn ($q, $id) => $q->where('department_id', $id))
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

    $colleges = \App\Models\College::active()->with('activeDepartments')->orderBy('name')->get();

    $filtersApplied = collect([
        'Search' => $request->search,
        'College/Office' => $request->college ? optional($colleges->firstWhere('id', (int) $request->college))->name : null,
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
        $colleges = \App\Models\College::active()->with('activeDepartments')->orderBy('name')->get();

        return view('admin.employees.create', compact('colleges'));
    }

public function store(Request $request)
{
    $validated = $request->validate([
        'employee_number' => ['required', 'string', 'unique:users,employee_number'],
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'unique:users,email'],
        'position' => ['nullable', 'string', 'max:255'],
        'college_id' => ['nullable', 'exists:colleges,id'],
        'department_id' => ['nullable', 'exists:departments,id'],
        'role' => ['required', Rule::in(['employee', 'dean', 'campus_director'])],
        // Printed in the header of the leave ledger card and the service record.
        'first_day_of_service' => ['nullable', 'date'],
        'date_hired' => ['nullable', 'date'],
        'contact_number' => ['nullable', 'string', 'max:20'],
        'password' => ['required', 'string', 'min:8', 'confirmed'],
        'photo' => ['nullable', 'image', 'max:2048'],
    ]);

    $photo = $request->hasFile('photo') ? $request->file('photo')->store('profile-photos', 'public') : null;

    $this->assertDepartmentBelongsToCollege($validated);

    // `department` and `program` are the legacy strings the ledger card,
    // service record and approval sheet still print. They are derived from the
    // real records so the two can never drift apart.
    $validated = $this->withLegacyOrgStrings($validated);

    User::create([
        ...collect($validated)->except('photo')->toArray(),
        'role' => $validated['role'],
        'status' => 'active',
        'password' => Hash::make($validated['password']),
        'profile_photo_path' => $photo,
    ]);

    return redirect()->route('admin.employees.index')
        ->with('success', 'Employee account created successfully.');
}

public function edit(User $employee)
{
    $colleges = \App\Models\College::active()->with('activeDepartments')->orderBy('name')->get();

    return view('admin.employees.edit', compact('employee', 'colleges'));
}

    public function update(Request $request, User $employee)
    {
        $validated = $request->validate([
            'employee_number' => ['required', 'string', Rule::unique('users', 'employee_number')->ignore($employee->id)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($employee->id)],
            'position' => ['nullable', 'string', 'max:255'],
            'college_id' => ['nullable', 'exists:colleges,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'role' => ['nullable', Rule::in(['employee', 'dean', 'campus_director'])],
            // Printed in the header of the leave ledger card and the service record.
            'first_day_of_service' => ['nullable', 'date'],
            'date_hired' => ['nullable', 'date'],
            'contact_number' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        foreach (['college_id', 'department_id', 'role', 'contact_number', 'position', 'first_day_of_service', 'date_hired'] as $field) {
            if (!$request->has($field)) {
                unset($validated[$field]);
            }
        }

        if (array_key_exists('college_id', $validated) || array_key_exists('department_id', $validated)) {
            $this->assertDepartmentBelongsToCollege($validated, $employee);
            $validated = $this->withLegacyOrgStrings($validated, $employee);
        }

        if (empty($validated['role'])) {
            $validated['role'] = $employee->role;
        }

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $employee->update($validated);

        return redirect()->route('admin.employees.show', $employee)
            ->with('success', 'Employee account updated successfully.');
    }

    /**
     * A department belongs to exactly one college. Posting one from a
     * different college would silently file the person under the wrong Dean's
     * reporting line, so it is rejected rather than quietly corrected.
     */
    private function assertDepartmentBelongsToCollege(array $data, ?User $employee = null): void
    {
        $departmentId = $data['department_id'] ?? null;

        if (! $departmentId) {
            return;
        }

        $collegeId = $data['college_id'] ?? $employee?->college_id;

        $department = \App\Models\Department::find($departmentId);

        if (! $department || (int) $department->college_id !== (int) $collegeId) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'department_id' => 'That department does not belong to the selected college.',
            ]);
        }
    }

    /**
     * Mirrors the college code and department name onto the legacy `department`
     * and `program` columns, which the printed documents still read.
     */
    private function withLegacyOrgStrings(array $data, ?User $employee = null): array
    {
        $collegeId = $data['college_id'] ?? $employee?->college_id;
        $departmentId = $data['department_id'] ?? null;

        $data['department'] = $collegeId
            ? optional(\App\Models\College::find($collegeId))->code
            : null;

        $data['program'] = $departmentId
            ? optional(\App\Models\Department::find($departmentId))->name
            : null;

        return $data;
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
    $colleges = \App\Models\College::active()->with('activeDepartments')->orderBy('name')->get();

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
