<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;

/**
 * Routes each privileged role to its own dashboard. They share a URL because
 * the sidebar and the post-login redirect both point here, but the figures and
 * the view differ per role.
 */
class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboard)
    {
    }

    public function index(Request $request)
    {
        $viewer = $request->user();

        if ($viewer->isDean()) {
            return view('admin.dashboards.dean', [
                'data' => $this->dashboard->forDean($viewer),
            ]);
        }

        if ($viewer->isCampusDirector()) {
            return view('admin.dashboards.director', [
                'data' => $this->dashboard->forDirector($viewer),
            ]);
        }

        abort_unless($viewer->isAdmin(), 403);

        return view('admin.dashboards.hr', [
            'data' => $this->dashboard->forHr($viewer),
            'year' => now()->year,
        ]);
    }
}
