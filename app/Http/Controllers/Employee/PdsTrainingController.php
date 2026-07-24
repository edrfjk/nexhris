<?php
namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\PdsTraining;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PdsTrainingController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'date_from' => ['required', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'number_of_hours' => ['nullable', 'numeric'],
            'type' => ['nullable', 'string', 'max:100'],
            'conducted_sponsored_by' => ['nullable', 'string', 'max:255'],
        ]);

        Auth::user()->pdsTrainings()->create($data);

        return back()->with('success', 'Training record added.');
    }

    public function destroy(PdsTraining $training)
    {
        abort_unless($training->user_id === Auth::id(), 403);
        $training->delete();

        return back()->with('success', 'Training record removed.');
    }
}