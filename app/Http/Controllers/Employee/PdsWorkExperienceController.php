<?php
namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\PdsWorkExperience;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PdsWorkExperienceController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'position_title' => ['required', 'string', 'max:255'],
            'department_agency_office_company' => ['required', 'string', 'max:255'],
            'monthly_salary' => ['nullable', 'numeric'],
            'salary_grade' => ['nullable', 'string', 'max:50'],
            'status_of_appointment' => ['nullable', 'string', 'max:100'],
            'is_government_service' => ['nullable', 'boolean'],
        ]);

        $data['is_government_service'] = $request->boolean('is_government_service');

        Auth::user()->pdsWorkExperiences()->create($data);

        return back()->with('success', 'Work experience added.');
    }

    public function destroy(PdsWorkExperience $work)
    {
        abort_unless($work->user_id === Auth::id(), 403);
        $work->delete();

        return back()->with('success', 'Work experience removed.');
    }
}