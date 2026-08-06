<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MyIdController extends Controller
{
    public function show()
    {
        $employee = Auth::user();
        return view('employee.id-card', compact('employee'));
    }

    public function updatePhoto(Request $request)
    {
        $request->validate([
            'photo' => ['required', 'image', 'max:2048'],
        ]);

        $employee = Auth::user();

        if ($employee->profile_photo_path) {
            Storage::disk('public')->delete($employee->profile_photo_path);
        }

        $path = $request->file('photo')->store('profile-photos', 'public');
        $employee->update(['profile_photo_path' => $path]);

        return back()->with('success', 'Your photo has been updated.');
    }
}