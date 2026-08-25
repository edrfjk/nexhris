<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\College;
use App\Models\Department;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Departments, programmes and offices under a College.
 *
 * HR only, for the same reason colleges are: which department someone belongs
 * to feeds the org chart and reporting, and a Dean editing it would be editing
 * their own reporting lines.
 */
class DepartmentController extends Controller
{
    public function __construct(private ActivityLogger $log)
    {
    }

    public function store(Request $request, College $college)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $this->validated($request, $college);

        $department = $college->departments()->create($data);

        $this->log->log(
            'department.created',
            "Added {$department->name} under {$college->code}.",
            $department,
        );

        return back()->with('success', "\"{$department->name}\" was added to {$college->code}.");
    }

    public function update(Request $request, Department $department)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $before = $department->only(['code', 'name', 'head_id', 'is_active']);

        $department->update($this->validated($request, $department->college, $department));

        $this->log->log(
            'department.updated',
            "Updated {$department->name}.",
            $department,
            ['before' => $before, 'after' => $department->fresh()->only(['code', 'name', 'head_id', 'is_active'])],
        );

        return back()->with('success', "\"{$department->name}\" was updated.");
    }

    /**
     * Deleting is only allowed while a department is empty; otherwise it is
     * deactivated, which hides it from pickers without detaching anybody.
     */
    public function destroy(Request $request, Department $department)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $name = $department->name;

        if (! $department->isDeletable()) {
            $department->update(['is_active' => false]);

            $this->log->log('department.deactivated',
                "Deactivated {$name} (still has employees).", $department);

            return back()->with('success',
                "\"{$name}\" still has employees, so it was deactivated rather than deleted.");
        }

        $this->log->log('department.deleted', "Deleted empty department {$name}.", $department);
        $department->delete();

        return back()->with('success', "\"{$name}\" was deleted.");
    }

    private function validated(Request $request, College $college, ?Department $department = null): array
    {
        return $request->validate([
            'code' => [
                'required', 'string', 'max:30',
                // Unique within the college, not across the campus: two
                // colleges may both run a department called "General Education".
                Rule::unique('departments', 'code')
                    ->where('college_id', $college->id)
                    ->ignore($department?->id),
            ],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'head_id' => ['nullable', 'exists:users,id'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'code.unique' => 'That code is already used by another department in this college.',
        ]);
    }
}
