<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PdsSubmission;
use App\Models\PdsTemplate;
use App\Models\User;
use App\Services\PdsSubmissionService;
use App\Services\XlsxToPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PdsReviewController extends Controller
{
    public function __construct(private PdsSubmissionService $pds)
    {
    }

    public function index(Request $request)
    {
        $year = $request->input('year', now()->year);
        $years = collect(range(now()->year, now()->year - 2))->values();

        $employees = User::whereIn('role', ['employee', 'dean', 'campus_director'])
            ->with([
                'pdsSubmissions' => fn ($q) => $q->where('applicable_year', $year),
                'college',
                'departmentRecord',
            ])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('employee_number', 'like', "%{$search}%");
                });
            })
            // Chasing compliance is done college by college, so both filters
            // are scoped server-side rather than hidden in the browser.
            ->when($request->college, fn ($q, $id) => $q->where('college_id', $id))
            ->when($request->department, fn ($q, $id) => $q->where('department_id', $id))
            ->when($request->status, function ($query, $status) use ($year) {
                if ($status === 'not_started') {
                    $query->where(function ($q) use ($year) {
                        $q->whereDoesntHave('pdsSubmissions', fn ($sub) => $sub->where('applicable_year', $year))
                          ->orWhereHas('pdsSubmissions', fn ($sub) => $sub->where('applicable_year', $year)->where('status', 'not_started'));
                    });
                } else {
                    $query->whereHas('pdsSubmissions', fn ($sub) => $sub->where('applicable_year', $year)->where('status', $status));
                }
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        // The stat cards count the same population the table is filtered to,
        // so "12 approved" can never sit above a list of three.
        $scope = fn () => User::whereIn('role', ['employee', 'dean', 'campus_director'])
            ->when($request->college, fn ($q, $id) => $q->where('college_id', $id))
            ->when($request->department, fn ($q, $id) => $q->where('department_id', $id));

        $totalEmployees = $scope()->count();

        $counts = PdsSubmission::where('applicable_year', $year)
            ->whereIn('user_id', $scope()->select('users.id'))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $templates = PdsTemplate::with('uploader')->orderByDesc('version')->get();
        $activeTemplate = $templates->firstWhere('is_active', true);
        $colleges = \App\Models\College::active()->with('activeDepartments')->orderBy('name')->get();

        return view('admin.pds.index', compact(
            'employees', 'year', 'years', 'counts', 'totalEmployees',
            'templates', 'activeTemplate', 'colleges'
        ));
    }

    public function show(User $employee)
    {
        $submission = PdsSubmission::with(['revisions.reviewer', 'template', 'reviewer'])
            ->where('user_id', $employee->id)
            ->where('applicable_year', request('year', now()->year))
            ->first();

        return view('admin.pds.show', compact('employee', 'submission'));
    }

    public function approve(Request $request, User $employee)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Only HR reviews the PDS.');

        $submission = $this->submissionFor($employee, $request);

        abort_unless($submission->isSubmitted(), 422,
            'Only a submitted PDS can be approved.');

        $this->pds->approve($submission, $request->user());

        return redirect()->route('admin.pds.index')
            ->with('success', "{$employee->name}'s PDS has been approved.");
    }

    public function returnForRevision(Request $request, User $employee)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Only HR reviews the PDS.');

        $data = $request->validate([
            'return_remarks' => ['required', 'string', 'max:1000'],
        ], [
            'return_remarks.required' => 'Tell the employee what needs correcting.',
        ]);

        $submission = $this->submissionFor($employee, $request);

        abort_unless($submission->isSubmitted(), 422,
            'Only a submitted PDS can be returned.');

        $this->pds->returnForRevision($submission, $request->user(), $data['return_remarks']);

        return redirect()->route('admin.pds.index')
            ->with('success', "{$employee->name}'s PDS was returned for correction.");
    }

    /**
     * Previews the submitted PDS. The stored conversion is served when it
     * exists; otherwise the workbook is converted on demand, so a failure at
     * upload time does not block review.
     */
    public function download(Request $request, User $employee, XlsxToPdfService $converter)
    {
        abort_unless($request->user()->isReviewer(), 403);

        $submission = PdsSubmission::where('user_id', $employee->id)
            ->where('applicable_year', $request->input('year', now()->year))
            ->first();

        if (! $submission || ! $submission->workbookExists()) {
            return back()->with('error', "{$employee->name} has not uploaded a PDS yet.");
        }

        $filename = 'PDS_' . preg_replace('/[^A-Za-z0-9_]/', '_', $employee->name)
            . '_' . $submission->applicable_year . '.pdf';

        if ($submission->pdfExists()) {
            return response()->file($submission->pdfPath(), [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
            ]);
        }

        try {
            return $converter->stream($submission->workbookPath(), $filename);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /** Downloads the original workbook, for when the PDF is not enough. */
    public function downloadWorkbook(Request $request, User $employee)
    {
        abort_unless($request->user()->isReviewer(), 403);

        $submission = PdsSubmission::where('user_id', $employee->id)
            ->where('applicable_year', $request->input('year', now()->year))
            ->first();

        abort_unless($submission && $submission->workbookExists(), 404,
            "{$employee->name} has not uploaded a PDS yet.");

        return Storage::disk('public')->download(
            $submission->file_path,
            $submission->file_original_name ?: 'PDS.xlsx'
        );
    }

    private function submissionFor(User $employee, Request $request): PdsSubmission
    {
        return PdsSubmission::where('user_id', $employee->id)
            ->where('applicable_year', $request->input('year', now()->year))
            ->firstOrFail();
    }
}
