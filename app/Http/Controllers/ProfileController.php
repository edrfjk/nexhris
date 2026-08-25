<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * A person's own account.
 *
 * Only the fields that belong to the individual are editable here. Employee
 * number, position, role, college and department stay with HR: the college
 * decides which Dean signs a leave form, so letting people edit their own
 * would let them choose their approver.
 */
class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile.edit', [
            'user' => $request->user()->load(['college', 'departmentRecord']),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'contact_number' => ['nullable', 'string', 'max:20'],
        ], [
            'email.unique' => 'That email address is already used by another account.',
        ]);

        $user->update($data);

        return back()->with('success', 'Your details have been updated.');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            // Their current password, so a walk-up at an unlocked screen
            // cannot lock the real owner out of their own account.
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.current_password' => 'That is not your current password.',
            'password.confirmed' => 'The two new passwords do not match.',
        ]);

        $request->user()->update(['password' => Hash::make($data['password'])]);

        return back()->with('success', 'Your password has been changed.');
    }

    public function updatePhoto(Request $request)
    {
        $request->validate([
            'photo' => ['required', 'image', 'max:2048'],
        ], [
            'photo.max' => 'The photo must be 2 MB or smaller.',
        ]);

        $user = $request->user();

        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        $user->update([
            'profile_photo_path' => $request->file('photo')->store('profile-photos', 'public'),
        ]);

        return back()->with('success', 'Your photo has been updated.');
    }
}
