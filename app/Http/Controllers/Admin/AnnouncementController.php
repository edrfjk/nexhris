<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\College;
use App\Models\User;
use App\Notifications\AnnouncementPosted;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

/** HR posts announcements; everyone reads them. */
class AnnouncementController extends Controller
{
    public function __construct(private ActivityLogger $log)
    {
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        return view('admin.announcements.index', [
            'announcements' => Announcement::with('author', 'college')
                ->orderByDesc('is_pinned')
                ->orderByDesc('created_at')
                ->paginate(15),
            'colleges' => College::active()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $this->validated($request);

        $announcement = Announcement::create($data + [
            'posted_by' => $request->user()->id,
            'published_at' => now(),
        ]);

        $this->log->log('announcement.posted',
            "Posted announcement \"{$announcement->title}\".", $announcement);

        // Everyone the notice is aimed at hears about it, in-app and by email.
        if ($announcement->is_published && $request->boolean('notify', true)) {
            $recipients = User::where('status', 'active')
                ->when($announcement->college_id,
                    fn ($q) => $q->where('college_id', $announcement->college_id))
                ->get();

            Notification::send($recipients, new AnnouncementPosted($announcement));
        }

        return back()->with('success', "\"{$announcement->title}\" has been posted.");
    }

    public function update(Request $request, Announcement $announcement)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $announcement->update($this->validated($request));

        $this->log->log('announcement.updated',
            "Updated announcement \"{$announcement->title}\".", $announcement);

        return back()->with('success', 'Announcement updated.');
    }

    public function destroy(Request $request, Announcement $announcement)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $title = $announcement->title;

        $this->log->log('announcement.deleted', "Deleted announcement \"{$title}\".", $announcement);
        $announcement->delete();

        return back()->with('success', "\"{$title}\" was deleted.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:8000'],
            'category' => ['nullable', 'string', 'max:60'],
            'college_id' => ['nullable', 'exists:colleges,id'],
            'is_pinned' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
        ]);
    }
}
