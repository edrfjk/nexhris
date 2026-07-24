<?php
namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\PdsCivilServiceEligibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PdsEligibilityController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'eligibility_name' => ['required', 'string', 'max:255'],
            'rating' => ['nullable', 'string', 'max:20'],
            'exam_date' => ['nullable', 'date'],
            'exam_place' => ['nullable', 'string', 'max:255'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'license_valid_until' => ['nullable', 'date'],
        ]);

        Auth::user()->pdsCivilServiceEligibilities()->create($data);

        return back()->with('success', 'Eligibility added.');
    }

    public function destroy(PdsCivilServiceEligibility $eligibility)
    {
        abort_unless($eligibility->user_id === Auth::id(), 403);
        $eligibility->delete();

        return back()->with('success', 'Eligibility removed.');
    }
}