<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\College;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Colleges and administrative offices. HR only — the college a person belongs
 * to determines which Dean signs their leave, so this is not something a Dean
 * should be able to edit.
 */
class CollegeController extends Controller
{
    public function __construct(private ActivityLogger $log)
    {
    }

    public function index(Request $request)
    {
        $colleges = College::query()
            ->with(['dean', 'departments' => fn ($q) => $q->withCount('employees')])
            ->withCount(['employees', 'staff', 'departments'])
            ->when($request->search, fn ($q, $s) => $q->where(function ($inner) use ($s) {
                $inner->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%");
            }))
            ->when($request->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->get();

        return view('admin.colleges.index', [
            'colleges' => $colleges,
            // Only Deans can be assigned, and only one college each.
            'availableDeans' => User::where('role', 'dean')->orderBy('name')->get(),
            'unassigned' => User::whereNull('college_id')
                ->whereIn('role', ['employee', 'dean', 'campus_director'])
                ->count(),
            // Someone in a college but not attached to any department under it.
            'withoutDepartment' => User::whereNotNull('college_id')
                ->whereNull('department_id')
                ->whereIn('role', ['employee', 'dean', 'campus_director'])
                ->count(),
            'totalDepartments' => \App\Models\Department::count(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $college = College::create($data);
        $this->syncDean($college, $data['dean_id'] ?? null);

        $this->log->log('college.created', "Created college {$college->name}.", $college);

        return back()->with('success', "\"{$college->name}\" has been added.");
    }

    public function update(Request $request, College $college)
    {
        $data = $this->validated($request, $college);

        $before = $college->only(['code', 'name', 'short_name', 'dean_id', 'is_active']);

        $college->update($data);
        $this->syncDean($college, $data['dean_id'] ?? null);

        $this->log->log('college.updated', "Updated college {$college->name}.", $college, [
            'before' => $before,
            'after' => $college->fresh()->only(['code', 'name', 'short_name', 'dean_id', 'is_active']),
        ]);

        return back()->with('success', "\"{$college->name}\" has been updated.");
    }

    /**
     * Deleting is only allowed while a college is empty. Otherwise it is
     * deactivated, which hides it from pickers without detaching anybody.
     */
    public function destroy(College $college)
    {
        if (! $college->isDeletable()) {
            $college->update(['is_active' => false]);

            $this->log->log('college.deactivated',
                "Deactivated college {$college->name} (still has employees).", $college);

            return back()->with('success',
                "\"{$college->name}\" still has employees, so it was deactivated rather than deleted.");
        }

        $name = $college->name;
        $this->log->log('college.deleted', "Deleted empty college {$name}.", $college);
        $college->delete();

        return back()->with('success', "\"{$name}\" has been deleted.");
    }

    private function validated(Request $request, ?College $college = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20',
                Rule::unique('colleges', 'code')->ignore($college?->id)],
            'name' => ['required', 'string', 'max:150'],
            'short_name' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:500'],
            'dean_id' => ['nullable', 'exists:users,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * A Dean belongs to the college they sign for, and to only one. Assigning
     * them here moves them off any previous college in the same transaction.
     */
    private function syncDean(College $college, ?int $deanId): void
    {
        DB::transaction(function () use ($college, $deanId) {
            College::where('dean_id', $deanId)
                ->whereKeyNot($college->id)
                ->update(['dean_id' => null]);

            $college->update(['dean_id' => $deanId]);

            if ($deanId) {
                User::whereKey($deanId)->update([
                    'college_id' => $college->id,
                    'department' => $college->code,
                ]);
            }
        });
    }
}
