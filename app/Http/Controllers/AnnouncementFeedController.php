<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Notifications\AnnouncementPosted;
use Illuminate\Http\Request;

/** The read-only feed every role sees. */
class AnnouncementFeedController extends Controller
{
    public function index(Request $request)
    {
        // Opening the feed means the user has reached the announcements.
        // Clear only announcement notifications; leave, policy and other
        // unread alerts must keep their own badges.
        $request->user()->unreadNotifications()
            ->where('type', AnnouncementPosted::class)
            ->update(['read_at' => now()]);

        return view('announcements.index', [
            'announcements' => Announcement::visibleTo($request->user())
                ->with('author', 'college')
                ->paginate(10),
        ]);
    }
}
