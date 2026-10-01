<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\HrPolicy;
use App\Models\HrPolicyView;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PolicyController extends Controller
{
    public function index(Request $request)
    {
        $baseQuery = HrPolicy::inForce()
            ->when($request->search, fn ($q, $search) => $q->where('title', 'like', "%{$search}%"))
            ->when($request->category, fn ($q, $category) => $q->where('category', $category));

        $tab = $request->input('tab', 'all');

        $myViewedIds = HrPolicyView::where('user_id', Auth::id())
            ->whereNotNull('acknowledged_at')
            ->pluck('hr_policy_id');

        if ($tab === 'for_you') {
            $baseQuery->where('requires_acknowledgment', true)
                ->whereNotIn('id', $myViewedIds);
        }

        $policies = $baseQuery->orderByDesc('is_pinned')->latest('published_at')->paginate(9)->withQueryString();

        $featured = HrPolicy::inForce()->where('is_pinned', true)
            ->latest('published_at')->first();

        $categories = HrPolicy::inForce()->whereNotNull('category')->distinct()->pluck('category');

        // The same set the "For you" tab lists, so the number matches it.
        $forYouCount = HrPolicy::inForce()
            ->where('requires_acknowledgment', true)
            ->whereNotIn('id', $myViewedIds)
            ->count();

        $myViews = HrPolicyView::where('user_id', Auth::id())
            ->whereIn('hr_policy_id', $policies->pluck('id'))
            ->get()
            ->keyBy('hr_policy_id');

        return view('employee.policies.index', compact(
            'policies', 'categories', 'myViews', 'featured', 'tab', 'forYouCount'
        ));
    }

    public function show(HrPolicy $policy)
    {
        // A policy that has not taken effect is not published to staff yet.
        // An expired one stays readable as history; it is only no longer listed.
        abort_unless($policy->isReadableByStaff(), 404);

        $this->recordView($policy);

        $myView = HrPolicyView::where('hr_policy_id', $policy->id)->where('user_id', Auth::id())->first();

        $related = HrPolicy::inForce()
            ->where('id', '!=', $policy->id)
            ->when($policy->category, fn ($q) => $q->where('category', $policy->category))
            ->latest('published_at')
            ->take(3)
            ->get();

        return view('employee.policies.show', compact('policy', 'myView', 'related'));
    }

    public function acknowledge(HrPolicy $policy)
    {
        // Only a policy in force can be acknowledged: not a draft, not one
        // still to come, and not one that has lapsed.
        abort_unless(
            $policy->requires_acknowledgment && HrPolicy::inForce()->whereKey($policy->id)->exists(),
            404,
        );

        $view = $this->recordView($policy);

        $view->update(['acknowledged_at' => now()]);

        return back()->with('success', 'Thank you — your acknowledgment has been recorded.');
    }

    /** Create the one policy-view row safely when two tabs load together. */
    private function recordView(HrPolicy $policy): HrPolicyView
    {
        return DB::transaction(function () use ($policy) {
            $userId = Auth::id();
            User::whereKey($userId)->lockForUpdate()->firstOrFail();

            return HrPolicyView::firstOrCreate(
                ['hr_policy_id' => $policy->id, 'user_id' => $userId],
                ['viewed_at' => now()],
            );
        });
    }
}
