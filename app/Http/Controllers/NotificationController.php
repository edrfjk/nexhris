<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * In-app notification bell. Every role has one, so this sits outside the
 * admin namespace.
 */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return view('notifications.index', [
            'notifications' => $request->user()->notifications()->paginate(25),
            'unreadCount' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /** Marks one read, then follows it to wherever it points. */
    public function read(Request $request, string $id)
    {
        $note = $request->user()->notifications()->findOrFail($id);
        $note->markAsRead();

        return redirect($note->data['url'] ?? route('notifications.index'));
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'All notifications marked as read.');
    }
}
