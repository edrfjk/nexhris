<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\LeaveApplication;
use App\Models\LeaveFormTemplate;
use App\Services\LeaveWorkflowService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\XlsxToPdfService;
use Illuminate\Support\Facades\Storage;

class LeaveApplicationController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $applications = $user->leaveApplications()
            ->with('approvals.approver')
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->type, fn ($q, $type) => $q->where('leave_type', $type))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $balance = $user->leaveBalance;
        $template = LeaveFormTemplate::active();

        $inReview = $user->leaveApplications()
            ->whereIn('status', ['submitted', 'dean_approved', 'hr_approved'])
            ->count();

        $needsAttention = $user->leaveApplications()
            ->whereIn('status', ['dean_returned', 'hr_returned', 'cd_returned'])
            ->count();

        $readyToPrint = $user->leaveApplications()
            ->where('status', 'cd_approved')
            ->count();

        $approvedThisYear = $user->leaveApplications()
            ->whereIn('status', ['cd_approved', 'completed'])
            ->whereYear('date_from', now()->year)
            ->sum('days');

        $ledger = $user->leaveLedgerEntries()->latest('period_from')->take(6)->get()->reverse();

        return view('employee.leave.index', compact(
            'applications', 'balance', 'template', 'inReview', 'needsAttention',
            'readyToPrint', 'approvedThisYear', 'ledger'
        ));
    }

    /**
     * Downloads the blank leave form HR published. Falls back to the bundled
     * copy so employees are never blocked when HR has not uploaded one yet.
     */
    public function downloadTemplate()
    {
        $template = LeaveFormTemplate::active();

        if ($template && Storage::disk('public')->exists($template->file_path)) {
            return Storage::disk('public')->download(
                $template->file_path,
                $template->original_filename
            );
        }

        $fallback = resource_path('templates/leave-form-template.xlsx');
        abort_unless(is_file($fallback), 404, 'No leave form template has been published yet.');

        return response()->download($fallback, 'Official-Leave-Form.xlsx');
    }

    /**
     * Uploads a filled-in leave form. This is the only way a form enters the
     * approval chain — it goes straight to the employee's Dean.
     */
    public function store(Request $request, LeaveWorkflowService $workflow)
    {
        $data = $request->validate([
            'leave_type' => ['required', 'in:VL,SL'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'reason' => ['nullable', 'string', 'max:500'],
            'leave_form' => ['required', 'file', 'mimes:pdf,xlsx,xls,doc,docx', 'max:10240'],
        ], [
            'leave_form.required' => 'Attach the filled-in leave form before submitting.',
        ]);

        $file = $request->file('leave_form');

        $days = \Carbon\Carbon::parse($data['date_from'])
            ->diffInWeekdays(\Carbon\Carbon::parse($data['date_to'])->addDay());

        // Warn when the ledger cannot cover the request. This is advisory, not
        // a block: HR may still approve leave without pay, and the ledger is
        // reconciled by hand after the Campus Director signs off.
        $balance = Auth::user()->leaveBalance;
        $available = (float) ($data['leave_type'] === 'VL'
            ? ($balance->vl_balance ?? 0)
            : ($balance->sl_balance ?? 0));

        $shortfall = $days > $available ? round($days - $available, 2) : 0;

        // Stamp the template version in force at submission, so the form can
        // still be read against the exact blank the employee downloaded.
        $template = LeaveFormTemplate::active();

        $application = LeaveApplication::create([
            'user_id' => Auth::id(),
            'leave_form_template_id' => $template?->id,
            'leave_type' => $data['leave_type'],
            'date_from' => $data['date_from'],
            'date_to' => $data['date_to'],
            'reason' => $data['reason'] ?? null,
            'days' => $days,
            'status' => 'submitted',
            'file_path' => $file->store('leave-applications', 'public'),
            'file_original_name' => $file->getClientOriginalName(),
            'uploaded_at' => now(),
        ]);

        // The chain decides where this starts, based on who is applying.
        $workflow->submit($application);

        $nextStage = $application->fresh()->currentStage();
        $nextLabel = $nextStage ? \App\Services\LeaveChain::LABELS[$nextStage] : 'review';

        $message = "Leave form submitted ({$days} working day(s)). It is now with the {$nextLabel}.";

        if ($shortfall > 0) {
            return back()
                ->with('success', $message)
                ->with('warning', sprintf(
                    'Heads up: you requested %s day(s) but only have %s %s day(s) available — a shortfall of %s. '
                    . 'HR will confirm whether the excess is charged without pay.',
                    rtrim(rtrim(number_format($days, 2), '0'), '.'),
                    rtrim(rtrim(number_format($available, 2), '0'), '.'),
                    $data['leave_type'],
                    rtrim(rtrim(number_format($shortfall, 2), '0'), '.'),
                ));
        }

        return back()->with('success', $message);
    }

    /**
     * Replaces the file on a returned form and restarts the chain.
     */
    public function resubmit(Request $request, LeaveApplication $application, LeaveWorkflowService $workflow)
    {
        abort_unless($application->user_id === Auth::id(), 403);
        abort_unless($application->isReturned(), 422,
            'Only a returned form can be re-submitted.');

        $request->validate([
            'leave_form' => ['required', 'file', 'mimes:pdf,xlsx,xls,doc,docx', 'max:10240'],
        ]);

        $file = $request->file('leave_form');

        if ($application->file_path && Storage::disk('public')->exists($application->file_path)) {
            Storage::disk('public')->delete($application->file_path);
        }

        $application->update([
            'file_path' => $file->store('leave-applications', 'public'),
            'file_original_name' => $file->getClientOriginalName(),
            'uploaded_at' => now(),
            // A corrected form may have been filled on a newer blank.
            'leave_form_template_id' => LeaveFormTemplate::active()?->id
                ?? $application->leave_form_template_id,
        ]);

        $workflow->resetForResubmission($application);

        return back()->with('success', 'Corrected form uploaded. It is back with your Dean for review.');
    }

    /**
     * The printable approval sheet. Only unlocked once the Campus Director has
     * signed off — the whole point of the online chain is that nobody prints a
     * form that was going to be rejected anyway.
     */
    public function printApproved(LeaveApplication $application)
    {
        abort_unless($application->user_id === Auth::id(), 403);
        abort_unless($application->isFullyApproved(), 403,
            'This form is not fully approved yet, so it cannot be printed.');

        return $this->renderApprovalSheet($application);
    }

    public function exportLedgerPdf()
    {
        $employee = Auth::user();

        return $this->renderLedgerCard($employee, 'My_Leave_Ledger_' . now()->format('Ymd') . '.pdf');
    }

    /**
     * The employee's own uploaded form, converted.
     *
     * Whoever filled the workbook should be able to see the PDF the reviewers
     * will be reading, and keep a copy of it.
     */
    public function exportFormPdf(LeaveApplication $application, XlsxToPdfService $converter)
    {
        abort_unless($application->user_id === Auth::id(), 403,
            'This is not your leave form.');

        abort_unless(
            $application->file_path && Storage::disk('public')->exists($application->file_path),
            404,
            'You have not uploaded a form for this application.'
        );

        return $converter->stream(
            Storage::disk('public')->path($application->file_path),
            $application->formPdfName(),
            cacheKey: 'leave-form:' . $application->id,
        );
    }


    // ------------------------------------------------------------------
    // Shared renderers — the admin side prints the identical documents.
    // ------------------------------------------------------------------

    public static function renderLedgerCard($employee, string $filename)
    {
        $ledger = $employee->leaveLedgerEntries()->orderBy('period_from')->get();

        $pdf = Pdf::loadView('pdf.leave-ledger-card', [
            'employee' => $employee,
            'ledger' => $ledger,
            'balance' => $employee->leaveBalance,
            'serviceRows' => $ledger->filter->touchesServiceCredits()->values(),
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream($filename);
    }

    public static function renderApprovalSheet(LeaveApplication $application)
    {
        $application->loadMissing(['user', 'dean', 'hrReviewer', 'director', 'approvals.approver']);

        $pdf = Pdf::loadView('pdf.leave-approval-sheet', [
            'application' => $application,
            'employee' => $application->user,
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');

        $name = preg_replace('/[^A-Za-z0-9_]/', '_', $application->user->name ?? 'Employee');

        return $pdf->stream("Leave_Form_{$name}_{$application->id}.pdf");
    }
}
