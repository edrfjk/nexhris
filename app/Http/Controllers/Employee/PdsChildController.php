<?php
namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\PdsChild;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PdsChildController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date'],
        ]);

        Auth::user()->pdsChildren()->create($data);

        return back()->with('success', 'Child record added.');
    }

    public function destroy(PdsChild $child)
    {
        abort_unless($child->user_id === Auth::id(), 403);
        $child->delete();

        return back()->with('success', 'Child record removed.');
    }
}