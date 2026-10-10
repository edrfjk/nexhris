<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The holiday calendar, kept by HR from official proclamations. Days marked
 * "excluded from leave" are not charged on vacation or sick leave filed after
 * the change; forms already filed keep the days they were filed with.
 */
class HolidayController extends Controller
{
    public function __construct(private ActivityLogger $log)
    {
    }

    public function index(Request $request)
    {
        $year = (int) $request->input('year', now()->year);
        $year = max(2000, min(2100, $year));

        $holidays = Holiday::whereYear('date', $year)->orderBy('date')->orderBy('name')->get();

        $years = Holiday::pluck('date')->map(fn ($d) => $d->year)
            ->push(now()->year, now()->year + 1, $year)
            ->unique()->sort()->values();

        return view('admin.holidays.index', [
            'holidays' => $holidays,
            'year' => $year,
            'years' => $years,
        ]);
    }

    public function store(Request $request)
    {
        $holiday = Holiday::create($this->validated($request));

        $this->log->log('holiday.created', "Added holiday {$holiday->name} on {$holiday->date->format('M j, Y')}.", $holiday);

        return redirect()->route('admin.holidays.index', ['year' => $holiday->date->year])
            ->with('success', "{$holiday->name} added to the {$holiday->date->year} calendar.");
    }

    public function update(Request $request, Holiday $holiday)
    {
        $holiday->update($this->validated($request, $holiday));

        $this->log->log('holiday.updated', "Updated holiday {$holiday->name} on {$holiday->date->format('M j, Y')}.", $holiday);

        return redirect()->route('admin.holidays.index', ['year' => $holiday->date->year])
            ->with('success', "{$holiday->name} updated. Leave already filed keeps its day count.");
    }

    public function destroy(Holiday $holiday)
    {
        $year = $holiday->date->year;
        $name = $holiday->name;

        $this->log->log('holiday.deleted', "Removed holiday {$name} on {$holiday->date->format('M j, Y')}.", $holiday);
        $holiday->delete();

        return redirect()->route('admin.holidays.index', ['year' => $year])
            ->with('success', "{$name} removed from the calendar.");
    }

    private function validated(Request $request, ?Holiday $holiday = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150',
                Rule::unique('holidays')->where(fn ($q) => $q->whereDate('date', $request->input('date')))->ignore($holiday?->id)],
            'date' => ['required', 'date', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'category' => ['required', Rule::in(array_keys(Holiday::CATEGORIES))],
            'scope' => ['required', Rule::in(array_keys(Holiday::SCOPES))],
            'locality' => ['nullable', 'required_if:scope,local', 'string', 'max:150'],
            'excludes_from_leave' => ['nullable', 'boolean'],
            'source' => ['nullable', 'string', 'max:255'],
        ], [
            'name.unique' => 'That holiday is already on the calendar for this date.',
            'locality.required_if' => 'Name the place a local holiday applies to.',
        ]);

        // An unticked box is not sent at all.
        $data['excludes_from_leave'] = $request->boolean('excludes_from_leave');

        if ($data['scope'] === 'national') {
            $data['locality'] = null;
        }

        return $data;
    }

}
