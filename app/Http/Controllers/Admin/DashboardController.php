<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HrPolicy;
use App\Models\LeaveApplication;
use App\Models\PdsSubmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $totalEmployees = User::where('role', 'employee')->count();
        $activeEmployees = User::where('role', 'employee')->where('status', 'active')->count();

        $pendingLeave = LeaveApplication::where('status', 'pending')->count();
        $approvedThisMonth = LeaveApplication::where('status', 'approved')
            ->whereMonth('reviewed_at', now()->month)
            ->whereYear('reviewed_at', now()->year)
            ->count();

        $year = now()->year;
        $pdsCounts = PdsSubmission::where('applicable_year', $year)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $pdsSubmittedCount = $pdsCounts->except('not_started')->sum();
        $pdsNotStartedCount = $totalEmployees - $pdsSubmittedCount;

        $publishedPolicies = HrPolicy::where('is_published', true)->count();

        $recentApplications = LeaveApplication::with('user')
            ->latest()
            ->take(5)
            ->get();

        $recentPdsActivity = PdsSubmission::with('user')
            ->whereIn('status', ['submitted', 'approved', 'returned'])
            ->latest('updated_at')
            ->take(5)
            ->get();

        return view('admin.dashboard', compact(
            'totalEmployees',
            'activeEmployees',
            'pendingLeave',
            'approvedThisMonth',
            'pdsCounts',
            'pdsSubmittedCount',
            'pdsNotStartedCount',
            'publishedPolicies',
            'recentApplications',
            'recentPdsActivity',
            'year',
        ));
    }
}