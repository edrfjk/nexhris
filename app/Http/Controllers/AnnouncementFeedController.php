<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\Request;

/** The read-only feed every role sees. */
class AnnouncementFeedController extends Controller
{
    public function index(Request $request)
    {
        return view('announcements.index', [
            'announcements' => Announcement::visibleTo($request->user())
                ->with('author', 'college')
                ->paginate(10),
        ]);
    }
}
