<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Position;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PositionController extends Controller
{
    public function __construct(private ActivityLogger $log)
    {
    }

    public function store(Request $request)
    {
        $position = Position::create($this->validated($request));
        $this->log->log('position.created', "Added position {$position->name}.", $position);

        return back()->with('success', "\"{$position->name}\" was added to the position list.");
    }

    public function update(Request $request, Position $position)
    {
        $before = $position->only(['name', 'category', 'is_active']);
        $position->update($this->validated($request, $position));
        $this->log->log('position.updated', "Updated position {$position->name}.", $position, [
            'before' => $before,
            'after' => $position->fresh()->only(['name', 'category', 'is_active']),
        ]);

        return back()->with('success', "\"{$position->name}\" was updated.");
    }

    public function destroy(Position $position)
    {
        $name = $position->name;
        $position->delete();
        $this->log->log('position.deleted', "Deleted position {$name}.", $position);

        return back()->with('success', "\"{$name}\" was removed from the position list. Existing employee records were not changed.");
    }

    private function validated(Request $request, ?Position $position = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('positions', 'name')->ignore($position?->id)],
            'category' => ['nullable', 'string', 'max:100'],
            'new_category' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        // A new category is explicit and wins over the selected one. This
        // avoids repeatedly typing an existing category but still lets HR add
        // a new group at the exact moment it is needed.
        $data['category'] = trim((string) (($data['new_category'] ?? null) ?: ($data['category'] ?? null))) ?: null;
        unset($data['new_category']);

        return $data;
    }
}
