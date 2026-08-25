<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\College;
use App\Models\HrPolicy;
use App\Models\LeaveApplication;
use App\Models\PdsSubmission;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Figures for the role dashboards.
 *
 * Every query here is scoped by the viewer: a Dean sees only their own
 * college, an employee only their own record, HR and the Campus Director see
 * the whole campus. The scoping lives in this service so a new widget cannot
 * quietly forget it.
 */
class DashboardService
{
    public function __construct(private LeaveChain $chain)
    {
    }

    // ==================================================================
    // Shared helpers
    // ==================================================================

    /** Staff accounts this viewer may count. */
    private function staffQuery(User $viewer)
    {
        return User::whereIn('role', ['employee', 'dean', 'campus_director'])
            ->visibleTo($viewer);
    }

    /** Leave applications this viewer may count. */
    private function leaveQuery(User $viewer)
    {
        $query = LeaveApplication::query();

        if ($viewer->isDean()) {
            $query->whereHas('user', fn ($q) => $q->where('college_id', $viewer->college_id ?? 0));
        }

        return $query;
    }

    private const IN_FLIGHT = ['submitted', 'dean_approved', 'hr_approved'];

    // ==================================================================
    // HR Administrator
    // ==================================================================

    public function forHr(User $viewer): array
    {
        $totalStaff = $this->staffQuery($viewer)->count();

        return [
            'headline' => [
                'staff' => $totalStaff,
                'active' => $this->staffQuery($viewer)->where('status', 'active')->count(),
                'inFlight' => LeaveApplication::whereIn('status', self::IN_FLIGHT)->count(),
                'awaitingLedger' => LeaveApplication::where('status', 'cd_approved')
                    ->where('ledger_posted', false)->count(),
            ],

            // Where forms are piling up, so HR can see the bottleneck.
            'bottleneck' => collect(LeaveChain::STAGES)->map(fn ($stage) => [
                'stage' => $stage,
                'label' => LeaveChain::LABELS[$stage],
                'count' => LeaveApplication::where('status', LeaveChain::PENDING_STATUS[$stage])->count(),
                'oldest' => LeaveApplication::where('status', LeaveChain::PENDING_STATUS[$stage])
                    ->min('updated_at'),
            ])->values()->all(),

            'leaveTypes' => $this->leaveTypeSplit(LeaveApplication::query()),
            'compliance' => $this->compliance($viewer, $totalStaff),
            'byCollege' => $this->collegeBreakdown(),
            'onboarding' => $this->onboarding($viewer),
            'policyTracker' => $this->policyTracker($totalStaff),
            'trend' => $this->monthlyTrend(LeaveApplication::query()),

            // Someone with no college has no Dean, so their leave form has
            // nowhere to route. It fails silently, which is why HR is told.
            'unrouted' => $this->staffQuery($viewer)
                ->whereNull('college_id')
                ->orderBy('name')
                ->get(['id', 'name', 'employee_number', 'role']),

            'recentActivity' => ActivityLog::with('user')->latest()->take(12)->get(),

            'ledgerQueue' => LeaveApplication::with('user')
                ->where('status', 'cd_approved')
                ->where('ledger_posted', false)
                ->latest('reviewed_at')
                ->take(6)
                ->get(),
        ];
    }

    // ==================================================================
    // Dean
    // ==================================================================

    public function forDean(User $viewer): array
    {
        $college = $viewer->college;
        $staff = $this->staffQuery($viewer);

        return [
            'college' => $college,
            'pending' => $this->leaveQuery($viewer)
                ->where('status', LeaveChain::PENDING_STATUS['dean'])
                ->where('user_id', '!=', $viewer->id)
                ->count(),
            'headcount' => (clone $staff)->count(),
            'activeHeadcount' => (clone $staff)->where('status', 'active')->count(),
            'onLeaveToday' => $this->onLeaveToday($viewer),
            'upcoming' => $this->upcomingLeave($viewer),
            'recentDecisions' => $this->recentDecisions($viewer),
            'leaveTypes' => $this->leaveTypeSplit($this->leaveQuery($viewer)),
        ];
    }

    // ==================================================================
    // Campus Director
    // ==================================================================

    public function forDirector(User $viewer): array
    {
        return [
            'pending' => LeaveApplication::where('status', LeaveChain::PENDING_STATUS['campus_director'])
                ->where('user_id', '!=', $viewer->id)
                ->count(),
            'inFlight' => LeaveApplication::whereIn('status', self::IN_FLIGHT)->count(),
            'byCollege' => $this->collegeBreakdown(),
            'trend' => $this->monthlyTrend(LeaveApplication::query()),
            'recentDecisions' => $this->recentDecisions($viewer),
            'onLeaveToday' => $this->onLeaveToday($viewer),

            // The form that has waited longest on this desk. A count alone
            // does not say whether the queue is fresh or three weeks stale.
            'oldestWaiting' => LeaveApplication::with('user')
                ->where('status', LeaveChain::PENDING_STATUS['campus_director'])
                ->where('user_id', '!=', $viewer->id)
                ->orderBy('hr_reviewed_at')
                ->first(),

            // The Campus Director files leave too, and their own form is
            // final at HR — so it never appears in the queue above.
            'myApplication' => LeaveApplication::where('user_id', $viewer->id)
                ->whereIn('status', array_merge(self::IN_FLIGHT, ['cd_approved']))
                ->latest()
                ->first(),
        ];
    }

    // ==================================================================
    // Employee
    // ==================================================================

    public function forEmployee(User $viewer): array
    {
        $year = now()->year;

        $policiesTotal = HrPolicy::where('is_published', true)->count();
        $acknowledged = DB::table('hr_policy_views')
            ->where('user_id', $viewer->id)
            ->whereNotNull('acknowledged_at')
            ->distinct()
            ->count('hr_policy_id');

        return [
            'balance' => $viewer->leaveBalance,
            'pds' => PdsSubmission::where('user_id', $viewer->id)
                ->where('applicable_year', $year)
                ->first(),
            // The one form worth showing a stepper for.
            'activeApplication' => LeaveApplication::where('user_id', $viewer->id)
                ->whereIn('status', array_merge(self::IN_FLIGHT, ['cd_approved']))
                ->latest()
                ->first(),
            'returned' => LeaveApplication::where('user_id', $viewer->id)
                ->whereIn('status', ['dean_returned', 'hr_returned', 'cd_returned'])
                ->count(),
            'usedThisYear' => LeaveApplication::where('user_id', $viewer->id)
                ->whereIn('status', ['cd_approved', 'completed'])
                ->whereYear('date_from', $year)
                ->sum('days'),
            'announcements' => Announcement::visibleTo($viewer)->take(3)->get(),
            'policiesTotal' => $policiesTotal,
            'policiesUnread' => max(0, $policiesTotal - $acknowledged),
        ];
    }

    // ==================================================================
    // Building blocks
    // ==================================================================

    /** Vacation vs sick split, for the pie chart. */
    private function leaveTypeSplit($query): array
    {
        $counts = (clone $query)
            ->whereIn('status', ['cd_approved', 'completed'])
            ->selectRaw('leave_type, count(*) as total, sum(days) as days')
            ->groupBy('leave_type')
            ->get()
            ->keyBy('leave_type');

        return [
            'labels' => ['Vacation Leave', 'Sick Leave'],
            'counts' => [
                (int) ($counts['VL']->total ?? 0),
                (int) ($counts['SL']->total ?? 0),
            ],
            'days' => [
                round((float) ($counts['VL']->days ?? 0), 2),
                round((float) ($counts['SL']->days ?? 0), 2),
            ],
        ];
    }

    /** Leave volume over the last six months, for the trend line. */
    private function monthlyTrend($query): array
    {
        $months = collect(range(5, 0))->map(fn ($back) => now()->copy()->subMonths($back)->startOfMonth());

        $rows = (clone $query)
            ->whereIn('status', ['cd_approved', 'completed'])
            ->where('date_from', '>=', $months->first())
            ->get(['date_from', 'days']);

        return [
            'labels' => $months->map(fn (Carbon $m) => $m->format('M Y'))->all(),
            'counts' => $months->map(fn (Carbon $m) => $rows->filter(
                fn ($r) => $r->date_from && $r->date_from->isSameMonth($m)
            )->count())->all(),
            'days' => $months->map(fn (Carbon $m) => round($rows->filter(
                fn ($r) => $r->date_from && $r->date_from->isSameMonth($m)
            )->sum('days'), 2))->all(),
        ];
    }

    /** PDS completion across the campus. */
    private function compliance(User $viewer, int $totalStaff): array
    {
        $year = now()->year;

        $approved = PdsSubmission::where('applicable_year', $year)->where('status', 'approved')->count();
        $submitted = PdsSubmission::where('applicable_year', $year)
            ->whereIn('status', ['submitted', 'approved'])->count();

        return [
            'total' => $totalStaff,
            'approved' => $approved,
            'submitted' => $submitted,
            'outstanding' => max(0, $totalStaff - $submitted),
            'percent' => $totalStaff > 0 ? (int) round($submitted / $totalStaff * 100) : 0,
        ];
    }

    /** Per-college leave volume, pending count and PDS compliance. */
    private function collegeBreakdown(): array
    {
        $year = now()->year;

        return College::active()
            ->withCount('employees')
            ->orderBy('name')
            ->get()
            ->map(function (College $college) use ($year) {
                $staffIds = User::where('college_id', $college->id)->pluck('id');

                $pending = LeaveApplication::whereIn('user_id', $staffIds)
                    ->whereIn('status', self::IN_FLIGHT)->count();

                $submitted = PdsSubmission::whereIn('user_id', $staffIds)
                    ->where('applicable_year', $year)
                    ->whereIn('status', ['submitted', 'approved'])
                    ->count();

                $headcount = $staffIds->count();

                return [
                    'code' => $college->code,
                    'name' => $college->name,
                    'headcount' => $headcount,
                    'pending' => $pending,
                    'leaveDays' => round((float) LeaveApplication::whereIn('user_id', $staffIds)
                        ->whereIn('status', ['cd_approved', 'completed'])
                        ->whereYear('date_from', $year)
                        ->sum('days'), 2),
                    'compliance' => $headcount > 0 ? (int) round($submitted / $headcount * 100) : 0,
                ];
            })
            ->values()
            ->all();
    }

    /** Accounts created recently whose PDS is still outstanding. */
    private function onboarding(User $viewer): array
    {
        $year = now()->year;

        return $this->staffQuery($viewer)
            ->where('created_at', '>=', now()->subDays(90))
            ->whereDoesntHave('pdsSubmissions', fn ($q) => $q
                ->where('applicable_year', $year)
                ->whereIn('status', ['submitted', 'approved']))
            ->latest()
            ->take(6)
            ->get(['id', 'name', 'created_at', 'college_id'])
            ->all();
    }

    /** How much of the campus has acknowledged the newest policy. */
    private function policyTracker(int $totalStaff): ?array
    {
        $policy = HrPolicy::where('is_published', true)->latest()->first();

        if (! $policy) {
            return null;
        }

        $read = DB::table('hr_policy_views')
            ->where('hr_policy_id', $policy->id)
            ->whereNotNull('acknowledged_at')
            ->distinct()
            ->count('user_id');

        return [
            'policy' => $policy,
            'read' => $read,
            'total' => $totalStaff,
            'percent' => $totalStaff > 0 ? (int) round($read / $totalStaff * 100) : 0,
        ];
    }

    private function onLeaveToday(User $viewer)
    {
        return $this->leaveQuery($viewer)
            ->with('user')
            ->whereIn('status', ['cd_approved', 'completed'])
            ->whereDate('date_from', '<=', today())
            ->whereDate('date_to', '>=', today())
            ->get();
    }

    private function upcomingLeave(User $viewer)
    {
        return $this->leaveQuery($viewer)
            ->with('user')
            ->whereIn('status', ['cd_approved', 'completed'])
            ->whereDate('date_from', '>=', today())
            ->orderBy('date_from')
            ->take(6)
            ->get();
    }

    /** The last few decisions this reviewer personally made. */
    private function recentDecisions(User $viewer)
    {
        return LeaveApplication::with('user')
            ->whereHas('approvals', fn ($q) => $q->where('user_id', $viewer->id))
            ->latest('updated_at')
            ->take(5)
            ->get();
    }
}
