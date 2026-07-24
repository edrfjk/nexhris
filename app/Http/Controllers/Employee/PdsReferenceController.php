<?php
namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\PdsReference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PdsReferenceController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'contact_no_email' => ['nullable', 'string', 'max:255'],
        ]);

        Auth::user()->pdsReferences()->create($data);

        return back()->with('success', 'Reference added.');
    }

    public function destroy(PdsReference $reference)
    {
        abort_unless($reference->user_id === Auth::id(), 403);
        $reference->delete();

        return back()->with('success', 'Reference removed.');
    }
}