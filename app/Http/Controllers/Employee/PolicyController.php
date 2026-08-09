<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\HrPolicy;
use App\Models\HrPolicyView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PolicyController extends Controller
{
    public function index(Request $request)
    {
        $baseQuery = HrPolicy::where('is_published', true)
            ->where(function ($q) {
                $q->whereNull('effective_date')->orWhere('effective_date', '<=', now());
            })
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

        $featured = HrPolicy::where('is_published', true)->where('is_pinned', true)
            ->where(function ($q) {
                $q->whereNull('effective_date')->orWhere('effective_date', '<=', now());
            })
            ->latest('published_at')->first();

        $categories = HrPolicy::where('is_published', true)->whereNotNull('category')->distinct()->pluck('category');

        $forYouCount = HrPolicy::where('is_published', true)
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
        abort_unless($policy->is_published, 404);

        HrPolicyView::firstOrCreate(
            ['hr_policy_id' => $policy->id, 'user_id' => Auth::id()],
            ['viewed_at' => now()]
        );

        $myView = HrPolicyView::where('hr_policy_id', $policy->id)->where('user_id', Auth::id())->first();

        $related = HrPolicy::where('is_published', true)
            ->where('id', '!=', $policy->id)
            ->when($policy->category, fn ($q) => $q->where('category', $policy->category))
            ->latest('published_at')
            ->take(3)
            ->get();

        return view('employee.policies.show', compact('policy', 'myView', 'related'));
    }

    public function acknowledge(HrPolicy $policy)
    {
        abort_unless($policy->requires_acknowledgment, 404);

        $view = HrPolicyView::firstOrCreate(
            ['hr_policy_id' => $policy->id, 'user_id' => Auth::id()],
            ['viewed_at' => now()]
        );

        $view->update(['acknowledged_at' => now()]);

        return back()->with('success', 'Thank you — your acknowledgment has been recorded.');
    }
}