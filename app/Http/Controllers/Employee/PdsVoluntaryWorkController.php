<?php
namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\PdsVoluntaryWork;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PdsVoluntaryWorkController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'organization_name_address' => ['required', 'string', 'max:255'],
            'date_from' => ['required', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'number_of_hours' => ['nullable', 'numeric'],
            'position_nature_of_work' => ['nullable', 'string', 'max:255'],
        ]);

        Auth::user()->pdsVoluntaryWorks()->create($data);

        return back()->with('success', 'Voluntary work added.');
    }

    public function destroy(PdsVoluntaryWork $voluntary)
    {
        abort_unless($voluntary->user_id === Auth::id(), 403);
        $voluntary->delete();

        return back()->with('success', 'Voluntary work removed.');
    }
}