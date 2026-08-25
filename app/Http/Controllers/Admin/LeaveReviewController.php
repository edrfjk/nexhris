<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Employee\LeaveApplicationController;
use App\Models\LeaveApplication;
use App\Services\LeaveLedgerService;
use App\Services\LeaveWorkflowService;
use App\Services\XlsxToPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The reviewer-facing half of the leave workflow. One controller serves all
 * three roles — the stage a reviewer acts on comes from their role, so the
 * Dean, HR Administrator and Campus Director share these screens and each
 * sees only the forms that are actually waiting on them.
 */
class LeaveReviewController extends Controller
{
    public function __construct(private LeaveWorkflowService $workflow)
    {
    }

    public function index(Request $request)
    {
        $reviewer = auth()->user();

        $applications = $this->workflow->queueFor($reviewer)
            // Every row prints the applicant's name and affiliation, so load
            // them once instead of once per row.
            ->with(['user.college', 'user.departmentRecord'])
            ->when($request->type, fn ($q, $type) => $q->where('leave_type', $type))
            ->when($request->search, fn ($q, $search) => $q->whereHas('user',
                fn ($u) => $u->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%")))
            ->when($request->college, fn ($q, $id) => $q->whereHas('user',
                fn ($u) => $u->where('college_id', $id)))
            ->when($request->department, fn ($q, $id) => $q->whereHas('user',
                fn ($u) => $u->where('department_id', $id)))
            // Oldest first: an approval queue is worked from the form that has
            // been waiting longest, not the one that just arrived.
            ->when($request->sort === 'newest', fn ($q) => $q->latest(),
                fn ($q) => $q->oldest())
            ->paginate(15)
            ->withQueryString();

        // What this reviewer has already handled, so the page shows progress
        // rather than just emptying out as they work through the queue.
        $recent = LeaveApplication::with('user')
            ->whereHas('approvals', fn ($q) => $q->where('user_id', $reviewer->id))
            ->latest('updated_at')
            ->take(5)
            ->get();

        $stage = $reviewer->approvalStage();
        $stageLabel = $stage ? \App\Services\LeaveChain::LABELS[$stage] : '';

        return view('admin.leave.review.index', [
            'applications' => $applications,
            'recent' => $recent,
            'stage' => $stage,
            'stageLabel' => $stageLabel,
            // A Dean is already pinned to one college, so the college filter
            // would be a single-option box for them.
            'colleges' => $reviewer->isDean()
                ? collect()
                : \App\Models\College::active()->with('activeDepartments')->orderBy('name')->get(),
        ]);
    }

    public function show(LeaveApplication $application)
    {
        $reviewer = auth()->user();

        // A reviewer may open any form they have touched or are about to, but
        // a Dean still never sees another program's employees.
        if ($reviewer->isDean()) {
            abort_unless($this->workflow->deanCoversEmployee($reviewer, $application->user), 403,
                'This employee is not registered under your program.');
        }

        $application->load(['user.leaveBalance', 'approvals.approver', 'dean', 'hrReviewer', 'director']);

        return view('admin.leave.review.show', [
            'application' => $application,
            'canReview' => $this->workflow->canReview($reviewer, $application),
        ]);
    }

    public function approve(Request $request, LeaveApplication $application)
    {
        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:500']]);

        $message = $this->workflow->approve(auth()->user(), $application, $data['remarks'] ?? null);

        return redirect()
            ->route('admin.leave.review.index')
            ->with('success', $message);
    }

    public function returnForRevision(Request $request, LeaveApplication $application)
    {
        $data = $request->validate([
            'remarks' => ['required', 'string', 'max:500'],
        ], [
            'remarks.required' => 'Tell the employee what needs to be corrected.',
        ]);

        $message = $this->workflow->returnForRevision(auth()->user(), $application, $data['remarks']);

        return redirect()
            ->route('admin.leave.review.index')
            ->with('success', $message);
    }

    /** Streams the employee's uploaded form inline for review. */
    public function viewForm(LeaveApplication $application)
    {
        $reviewer = auth()->user();
        abort_unless($reviewer->isReviewer(), 403);

        if ($reviewer->isDean()) {
            abort_unless($this->workflow->deanCoversEmployee($reviewer, $application->user), 403);
        }

        abort_unless(
            $application->file_path && Storage::disk('public')->exists($application->file_path),
            404,
            'The uploaded form is no longer available.'
        );

        return Storage::disk('public')->response(
            $application->file_path,
            $application->file_original_name ?: basename($application->file_path)
        );
    }

    /**
     * The same uploaded form, converted so it can be read in the browser.
     *
     * Employees fill the campus form in Excel and upload the workbook. No
     * browser previews an .xlsx, so without this every reviewer in the chain
     * had to download the file and open it in Excel before they could sign.
     */
    public function viewFormAsPdf(LeaveApplication $application, XlsxToPdfService $converter)
    {
        $reviewer = auth()->user();
        abort_unless($reviewer->isReviewer(), 403);

        if ($reviewer->isDean()) {
            abort_unless($this->workflow->deanCoversEmployee($reviewer, $application->user), 403);
        }

        abort_unless(
            $application->file_path && Storage::disk('public')->exists($application->file_path),
            404,
            'The uploaded form is no longer available.'
        );

        return $converter->stream(
            Storage::disk('public')->path($application->file_path),
            $application->formPdfName(),
            cacheKey: 'leave-form:' . $application->id,
        );
    }

    /**
     * The final HR step: once the Campus Director has approved, HR records the
     * actual days, dates and credits against the employee's ledger card.
     */
    public function postToLedger(Request $request, LeaveApplication $application, LeaveLedgerService $ledger)
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only HR can post leave to the ledger.');
        abort_unless($application->isFullyApproved(), 422,
            'This form must be approved by the Campus Director before it can be posted.');
        abort_if($application->ledger_posted, 422, 'This leave has already been posted to the ledger.');

        $data = $request->validate([
            'period_from' => ['required', 'date'],
            'period_to' => ['required', 'date', 'after_or_equal:period_from'],
            'days' => ['required', 'numeric', 'min:0'],
            'vl_used' => ['nullable', 'numeric', 'min:0'],
            'vl_used_wop' => ['nullable', 'numeric', 'min:0'],
            'sl_used' => ['nullable', 'numeric', 'min:0'],
            'sl_used_wop' => ['nullable', 'numeric', 'min:0'],
            'service_used' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['required', 'string', 'max:255'],
            // Which card this leave is written on. The campus keeps two, and
            // the choice belongs to HR once the form is fully approved.
            'ledger' => ['required', \Illuminate\Validation\Rule::in([
                \App\Models\LeaveLedgerEntry::LEAVE,
                \App\Models\LeaveLedgerEntry::SERVICE,
            ])],
        ]);

        try {
            $entry = $ledger->postEntry(
                employee: $application->user,
                periodFrom: $data['period_from'],
                periodTo: $data['period_to'],
                type: 'leave_deduction',
                remarks: $data['remarks'],
                vlUsed: (float) ($data['vl_used'] ?? 0),
                vlUsedWop: (float) ($data['vl_used_wop'] ?? 0),
                slUsed: (float) ($data['sl_used'] ?? 0),
                slUsedWop: (float) ($data['sl_used_wop'] ?? 0),
                serviceUsed: (float) ($data['service_used'] ?? 0),
                leaveApplicationId: $application->id,
                ledger: $data['ledger'],
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        $application->update([
            'status' => 'completed',
            'ledger_posted' => true,
            'leave_ledger_entry_id' => $entry->id,
            'days' => $data['days'],
            'date_from' => $data['period_from'],
            'date_to' => $data['period_to'],
        ]);

        return back()->with('success',
            'Leave posted to ' . $application->user->name . "'s ledger card.");
    }

    /** Reviewers print the same approval sheet the employee gets. */
    public function printApproved(LeaveApplication $application)
    {
        abort_unless(auth()->user()->isReviewer(), 403);
        abort_unless($application->isFullyApproved(), 403,
            'This form is not fully approved yet.');

        return LeaveApplicationController::renderApprovalSheet($application);
    }
}
