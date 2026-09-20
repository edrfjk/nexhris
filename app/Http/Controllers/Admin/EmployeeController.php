<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Position;
use App\Models\Department;
use App\Support\DocumentName;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\Rules\Password as PasswordRule;


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

    $colleges = $this->organisations();

    // Global counts, unaffected by the current search/filter — same idea as
    // $pendingCount on the leave page.
    $activeCount      = User::whereIn('role', ['employee', 'dean', 'campus_director'])->where('status', 'active')->count();
    $inactiveCount    = User::whereIn('role', ['employee', 'dean', 'campus_director'])->where('status', 'inactive')->count();
    $newThisMonthCount = User::whereIn('role', ['employee', 'dean', 'campus_director'])
        ->whereMonth('created_at', now()->month)
        ->whereYear('created_at', now()->year)
        ->count();

    $positions = Position::active()->orderBy('category')->orderBy('sort_order')->orderBy('name')->get();

    return view('admin.employees.index', compact(
        'employees', 'colleges', 'positions', 'activeCount', 'inactiveCount', 'newThisMonthCount'
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
    return $pdf->stream(DocumentName::employeeDirectory());
}

    public function create()
    {
        // Adding is intentionally kept on the directory page so HR can add
        // several accounts without a page round-trip. Preserve old bookmarks.
        return redirect()->route('admin.employees.index', ['add' => 1]);
    }

public function store(Request $request)
{
    $validated = $request->validate([
        'employee_number' => ['required', 'string', 'unique:users,employee_number'],
        'name' => ['nullable', 'string', 'max:255'],
        'first_name' => ['nullable', 'string', 'max:100'],
        'middle_name' => ['nullable', 'string', 'max:100'],
        'last_name' => ['nullable', 'string', 'max:100'],
        'email' => ['required', 'email', 'unique:users,email'],
        'position' => ['nullable', 'string', 'max:255'],
        'college_id' => ['nullable', 'exists:colleges,id'],
        'department_id' => ['nullable', 'exists:departments,id'],
        'role' => ['required', Rule::in(['employee', 'dean', 'campus_director'])],
        // Printed in the header of the leave ledger card. Without it the
        // official card goes out with the "First day of government service"
        // line blank, which the campus will not accept — so it is required for
        // a new account. Existing records that predate this are not blocked.
        'first_day_of_service' => ['required', 'date'],
        'date_hired' => ['nullable', 'date'],
        'contact_number' => ['nullable', 'string', 'max:20'],
        // The same rule the reset screen enforces. HR sets the passwords for the
        // accounts with the most access, so this is the last place that should
        // accept eight of anything.
        'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        'photo' => ['nullable', 'image', 'max:2048'],
        'confirm_replace_dean' => ['nullable', 'boolean'],
        'confirm_replace_program_head' => ['nullable', 'boolean'],
        ]);

    $this->normaliseStructuredName($validated, true);

    $photo = $request->hasFile('photo') ? $request->file('photo')->store('profile-photos', 'public') : null;

    $this->assertDepartmentBelongsToCollege($validated);
    $this->assertProgramHeadHasDepartment($validated['position'] ?? null, $validated['department_id'] ?? null);
    $this->assertCollegeDeanHasCollege($validated['position'] ?? null, $validated['college_id'] ?? null);

    $this->assertAssignmentCanBeReplaced($validated);

    // `department` and `program` are the legacy strings the ledger card,
    // service record and approval sheet still print. They are derived from the
    // real records so the two can never drift apart.
    $validated = $this->withLegacyOrgStrings($validated);

    $employee = User::create([
        ...collect($validated)->except(['photo', 'confirm_replace_dean', 'confirm_replace_program_head'])->toArray(),
        'role' => $validated['role'],
        'status' => 'active',
        'password' => Hash::make($validated['password']),
        'profile_photo_path' => $photo,
    ]);

    $this->syncOrganisationAssignments($employee);

    return redirect()->route('admin.employees.index')
        ->with('success', 'Employee account created successfully.');
}

public function edit(User $employee)
{
    $colleges = $this->organisations();
    $positions = Position::active()->orderBy('category')->orderBy('sort_order')->orderBy('name')->get();

    return view('admin.employees.edit', compact('employee', 'colleges', 'positions'));
}

    public function update(Request $request, User $employee)
    {
        $validated = $request->validate([
            'employee_number' => ['required', 'string', Rule::unique('users', 'employee_number')->ignore($employee->id)],
            'name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($employee->id)],
            'position' => ['nullable', 'string', 'max:255'],
            'college_id' => ['nullable', 'exists:colleges,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'role' => ['nullable', Rule::in(['employee', 'dean', 'campus_director'])],
            // Printed in the header of the leave ledger card and the service record.
            'first_day_of_service' => ['nullable', 'date'],
            'date_hired' => ['nullable', 'date'],
            'contact_number' => ['nullable', 'string', 'max:20'],
        'password' => ['nullable', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        'photo' => ['nullable', 'image', 'max:2048'],
        'confirm_replace_dean' => ['nullable', 'boolean'],
        'confirm_replace_program_head' => ['nullable', 'boolean'],
        ]);

        foreach (['college_id', 'department_id', 'role', 'contact_number', 'position', 'first_day_of_service', 'date_hired', 'first_name', 'middle_name', 'last_name'] as $field) {
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

        $this->assertProgramHeadHasDepartment(
            $validated['position'] ?? $employee->position,
            $validated['department_id'] ?? $employee->department_id,
        );
        $this->assertCollegeDeanHasCollege(
            $validated['position'] ?? $employee->position,
            $validated['college_id'] ?? $employee->college_id,
        );

        $this->assertAssignmentCanBeReplaced($validated, $employee);
        $this->normaliseStructuredName($validated, false, $employee);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        if ($request->hasFile('photo')) {
            $newPhoto = $request->file('photo')->store('profile-photos', 'public');
            if ($employee->profile_photo_path) {
                Storage::disk('public')->delete($employee->profile_photo_path);
            }
            $validated['profile_photo_path'] = $newPhoto;
        }
        unset($validated['photo']);

        $employee->update(collect($validated)->except([
            'confirm_replace_dean', 'confirm_replace_program_head',
        ])->toArray());
        $this->syncOrganisationAssignments($employee->fresh());

        return redirect()->route('admin.employees.show', $employee)
            ->with('success', 'Employee account updated successfully.');
    }

    /** Make the account's official position the source of organisational assignments. */
    private function syncOrganisationAssignments(User $employee): void
    {
        $isProgramHead = $employee->position === 'Program Chair / Program Head';

        // A person no longer holding the position, or moved to another
        // department, must not remain shown as head of their old programme.
        Department::where('head_id', $employee->id)
            ->when($isProgramHead && $employee->department_id,
                fn ($query) => $query->whereKeyNot($employee->department_id))
            ->update(['head_id' => null]);

        if ($isProgramHead && $employee->department_id) {
            Department::whereKey($employee->department_id)->update(['head_id' => $employee->id]);
        }

        $isCollegeDean = $employee->position === 'College Dean';
        \App\Models\College::where('dean_id', $employee->id)
            ->when($isCollegeDean && $employee->college_id,
                fn ($query) => $query->whereKeyNot($employee->college_id))
            ->update(['dean_id' => null]);

        if (! $isCollegeDean) {
            if ($employee->role === 'dean') {
                $employee->update(['role' => 'employee']);
            }
            return;
        }

        $previousDeanId = \App\Models\College::whereKey($employee->college_id)->value('dean_id');
        if ($previousDeanId && $previousDeanId !== $employee->id) {
            User::whereKey($previousDeanId)->where('role', 'dean')->update(['role' => 'employee']);
        }
        \App\Models\College::whereKey($employee->college_id)->update(['dean_id' => $employee->id]);
        if ($employee->role !== 'dean') {
            $employee->update(['role' => 'dean']);
        }
    }

    private function assertAssignmentCanBeReplaced(array $data, ?User $employee = null): void
    {
        $position = $data['position'] ?? $employee?->position;
        $collegeId = $data['college_id'] ?? $employee?->college_id;
        $departmentId = $data['department_id'] ?? $employee?->department_id;

        if ($position === 'College Dean' && $collegeId) {
            $current = \App\Models\College::find($collegeId)?->dean_id;
            if ($current && $current !== $employee?->id && empty($data['confirm_replace_dean'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'position' => 'This college already has a Dean. Confirm the replacement to continue.',
                ]);
            }
        }

        if ($position === 'Program Chair / Program Head' && $departmentId) {
            $current = Department::find($departmentId)?->head_id;
            if ($current && $current !== $employee?->id && empty($data['confirm_replace_program_head'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'position' => 'This department already has a Programme Head. Confirm the replacement to continue.',
                ]);
            }
        }
    }

    private function assertProgramHeadHasDepartment(?string $position, ?int $departmentId): void
    {
        if ($position === 'Program Chair / Program Head' && ! $departmentId) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'department_id' => 'Select the department this Programme Head will lead.',
            ]);
        }
    }

    private function assertCollegeDeanHasCollege(?string $position, ?int $collegeId): void
    {
        if ($position === 'College Dean' && ! $collegeId) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'college_id' => 'Select the college this Dean will lead.',
            ]);
        }
    }

    /**
     * Keep the display/search name for existing screens, while preserving the
     * exact individual fields the ledger needs. Older API callers can still
     * submit a single name; the first new modal submission cannot.
     */
    private function normaliseStructuredName(array &$data, bool $creating, ?User $employee = null): void
    {
        $hasStructuredInput = array_key_exists('first_name', $data)
            || array_key_exists('last_name', $data)
            || array_key_exists('middle_name', $data);

        if ($hasStructuredInput) {
            $first = trim((string) ($data['first_name'] ?? ''));
            $last = trim((string) ($data['last_name'] ?? ''));

            if ($first === '' || $last === '') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'first_name' => 'First name and last name are required when entering a structured name.',
                    'last_name' => 'First name and last name are required when entering a structured name.',
                ]);
            }

            $middle = trim((string) ($data['middle_name'] ?? ''));
            $data['first_name'] = $first;
            $data['middle_name'] = $middle ?: null;
            $data['last_name'] = $last;
            $data['name'] = collect([$first, $middle, $last])->filter()->implode(' ');

            return;
        }

        if (trim((string) ($data['name'] ?? '')) === '') {
            if ($creating) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'name' => 'Enter the employee’s first and last name.',
                ]);
            }

            $data['name'] = $employee?->name;
        }
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

public function show(Request $request, User $employee)
{
    // The list is scoped with visibleTo(); this was not, so a Dean could reach
    // any employee in the campus by typing the id into the address bar — the
    // exact thing the query-level boundary is supposed to prevent.
    $viewer = $request->user();

    abort_unless(
        $viewer->isAdmin()
            || $viewer->isCampusDirector()
            || ($viewer->isDean() && $employee->college_id === $viewer->college_id),
        403,
        'This employee is not in your college.',
    );

    $colleges = $this->organisations();
    $positions = Position::active()->orderBy('category')->orderBy('sort_order')->orderBy('name')->get();

    // The two things HR checks on a record before doing anything else: what
    // this person has left to spend, and whether their sheet is in order.
    $year = now()->year;

    return view('admin.employees.show', [
        'employee' => $employee,
        'colleges' => $colleges,
        'positions' => $positions,
        'balance' => $employee->leaveBalance,
        'pds' => $employee->pdsSubmissions()->where('applicable_year', $year)->first(),
        'pdsYear' => $year,
        'leaveInFlight' => $employee->leaveApplications()
            ->whereIn('status', ['submitted', 'dean_approved', 'hr_approved'])
            ->count(),
    ]);
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

    private function organisations()
    {
        return \App\Models\College::active()
            ->with(['dean', 'activeDepartments.head'])
            ->orderBy('name')
            ->get();
    }
}
