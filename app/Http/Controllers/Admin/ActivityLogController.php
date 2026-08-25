<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Read-only view of the audit trail. HR only — the log names who did what, so
 * it is not something a Dean or the Campus Director should browse.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = ActivityLog::with('user')
            ->when($request->action, fn ($q, $action) => $q->where('action', $action))
            ->when($request->user_id, fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->search, function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('description', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->from, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($request->to, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.activity-logs.index', [
            'logs' => $logs,
            'actions' => ActivityLog::query()
                ->select('action')
                ->distinct()
                ->orderBy('action')
                ->pluck('action'),
            'people' => User::orderBy('name')->get(['id', 'name']),
            'todayCount' => ActivityLog::whereDate('created_at', today())->count(),
            'failedToday' => ActivityLog::whereDate('created_at', today())
                ->whereIn('action', ['auth.login_failed', 'auth.2fa_failed', 'auth.locked'])
                ->count(),
        ]);
    }
}
