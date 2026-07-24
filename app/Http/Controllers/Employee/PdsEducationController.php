<?php
namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\PdsEducationalBackground;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PdsEducationController extends Controller
{
    public function store(Request $request)
    {
        $data = $this->validated($request);
        Auth::user()->pdsEducationalBackgrounds()->create($data);

        return back()->with('success', 'Education record added.');
    }

    public function update(Request $request, PdsEducationalBackground $education)
    {
        abort_unless($education->user_id === Auth::id(), 403);

        $education->update($this->validated($request));

        return back()->with('success', 'Education record updated.');
    }

    public function destroy(PdsEducationalBackground $education)
    {
        abort_unless($education->user_id === Auth::id(), 403);

        $education->delete();

        return back()->with('success', 'Education record removed.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'level' => ['required', 'in:Elementary,Secondary,Vocational/Trade Course,College,Graduate Studies'],
            'school_name' => ['required', 'string', 'max:255'],
            'degree_course' => ['nullable', 'string', 'max:255'],
            'period_from' => ['nullable', 'date'],
            'period_to' => ['nullable', 'date'],
            'highest_level_units' => ['nullable', 'string', 'max:255'],
            'year_graduated' => ['nullable', 'string', 'max:10'],
            'scholarship_honors' => ['nullable', 'string', 'max:255'],
        ]);
    }
}