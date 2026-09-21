<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Employee\LeaveApplicationController;
use App\Models\LeaveApplication;
use App\Models\LeaveLedgerEntry;
use App\Models\LeaveFormTemplate;
use App\Models\User;
use App\Services\LeaveLedgerService;
use App\Services\LeaveWorkflowService;
use App\Support\DocumentName;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class LeaveLedgerController extends Controller
{
    public function index(Request $request, LeaveWorkflowService $workflow)
    {
        $reviewer = auth()->user();

        // Deans and the Campus Director accrue and spend leave like anyone
        // else, so their ledger cards belong in this list too.
        $employees = User::whereIn('role', ['employee', 'dean', 'campus_director'])
            ->with(['leaveBalance', 'college', 'departmentRecord'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('employee_number', 'like', "%{$search}%");
                });
            })
            ->when($request->college, fn ($q, $college) => $q->where('college_id', $college))
            ->when($request->department, fn ($q, $id) => $q->where('department_id', $id))
            // A Dean manages only their own program's employees.
            ->visibleTo($reviewer)
            ->when($request->sort === 'low_balance', function ($q) {
                $q->leftJoin('leave_balances', 'leave_balances.user_id', '=', 'users.id')
                    ->orderByRaw('COALESCE(leave_balances.vl_balance, 0) + COALESCE(leave_balances.sl_balance, 0) ASC')
                    ->select('users.*');
            }, fn ($q) => $q->orderBy('name'))
            ->paginate(15)
            ->withQueryString();

        return view('admin.leave.index', [
            'employees' => $employees,
            'colleges' => \App\Models\College::active()->with('activeDepartments')->orderBy('name')->get(),
            'pendingCount' => $workflow->queueFor($reviewer)->count(),
            'awaitingPosting' => $reviewer->isAdmin()
                ? LeaveApplication::where('status', 'cd_approved')->where('ledger_posted', false)->count()
                : 0,
            'activeTemplate' => LeaveFormTemplate::active(),
        ]);
    }

    public function show(User $employee, LeaveWorkflowService $workflow)
    {
        $reviewer = auth()->user();

        if ($reviewer->isDean()) {
            abort_unless($workflow->deanCoversEmployee($reviewer, $employee), 403,
                'This employee is not registered under your program.');
        }

        $entries = $employee->leaveLedgerEntries()->orderBy('period_from')->orderBy('id');

        return view('admin.leave.ledger', [
            'employee' => $employee,
            // Each card has its own paginator. A lengthy service-credit
            // history must not make the ordinary leave card slow to open.
            'leaveLines' => (clone $entries)->where('ledger', LeaveLedgerEntry::LEAVE)
                ->paginate(20, ['*'], 'leave_ledger_page')->withQueryString(),
            'serviceLines' => (clone $entries)->where('ledger', LeaveLedgerEntry::SERVICE)
                ->paginate(20, ['*'], 'service_ledger_page')->withQueryString(),
            'leaveLineCount' => (clone $entries)->where('ledger', LeaveLedgerEntry::LEAVE)->count(),
            'earnedPeriods' => $employee->leaveLedgerEntries()->where('type', 'earned')
                ->get(['ledger', 'period_from', 'period_to']),
            'balance' => $employee->leaveBalance,
            'applications' => $employee->leaveApplications()->latest()->paginate(10, ['*'], 'apps_page'),
        ]);
    }

    public function storeEarned(Request $request, User $employee, LeaveLedgerService $service)
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only HR can post leave credits.');

        $data = $request->validate([
            'period_from' => ['required', 'date'],
            'period_to' => ['required', 'date', 'after_or_equal:period_from'],
            'vl_earned' => ['nullable', 'numeric', 'min:0'],
            'sl_earned' => ['nullable', 'numeric', 'min:0'],
            'service_earned' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:255'],
            'allow_duplicate' => ['nullable', 'boolean'],
            // Which of the two cards this line is written on. Without it a
            // service credit posted here landed on the leave card, where
            // service credits are not counted, and silently did nothing.
            'ledger' => ['nullable', \Illuminate\Validation\Rule::in([
                LeaveLedgerEntry::LEAVE, LeaveLedgerEntry::SERVICE,
            ])],
        ]);

        $card = $data['ledger'] ?? LeaveLedgerEntry::LEAVE;
        $allowDuplicate = $request->boolean('allow_duplicate');

        // Give HR a useful application message instead of allowing the
        // database's unique-index error to escape when this period was
        // already credited for this employee and card.
        $alreadyPosted = $employee->leaveLedgerEntries()
            ->where('type', 'earned')
            ->where('ledger', $card)
            ->whereDate('period_from', $data['period_from'])
            ->whereDate('period_to', $data['period_to'])
            ->exists();

        if ($alreadyPosted && ! $allowDuplicate) {
            return back()->with('error', sprintf(
                'Credits for %s to %s are already recorded on the %s card. Nothing was added.',
                $data['period_from'],
                $data['period_to'],
                $card === LeaveLedgerEntry::SERVICE ? 'service credit' : 'leave',
            ))->withInput();
        }

        try {
            $service->postEntry(
                employee: $employee,
                periodFrom: $data['period_from'],
                periodTo: $data['period_to'],
                type: 'earned',
                remarks: ($data['remarks'] ?? null) ?: "Earned {$data['period_from']} – {$data['period_to']}",
                vlEarned: (float) ($data['vl_earned'] ?? 0),
                slEarned: (float) ($data['sl_earned'] ?? 0),
                serviceEarned: (float) ($data['service_earned'] ?? 0),
                ledger: $card,
            );
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // The pre-check handles the normal case. This catch handles two
            // HR requests arriving at the same time; the database constraint
            // remains the final protection against double crediting.
            return back()->with('error', sprintf(
                'Credits for %s to %s were already recorded. Nothing was added.',
                $data['period_from'],
                $data['period_to'],
            ))->withInput();
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $card === LeaveLedgerEntry::SERVICE
            ? 'Service credits posted to the service credit card.'
            : 'Leave credits posted to the leave ledger card.');
    }

    /**
     * A manual line for anything the earned/leave flows do not cover — an
     * opening balance, a correction, or days charged without pay.
     */
    public function storeAdjustment(Request $request, User $employee, LeaveLedgerService $service)
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only HR can post adjustments.');

        $data = $request->validate([
            'date' => ['required', 'date'],
            'vl_adjustment' => ['nullable', 'numeric'],
            'sl_adjustment' => ['nullable', 'numeric'],
            'service_adjustment' => ['nullable', 'numeric'],
            'vl_used_wop' => ['nullable', 'numeric', 'min:0'],
            'sl_used_wop' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['required', 'string', 'max:255'],
        ]);

        $vl = (float) ($data['vl_adjustment'] ?? 0);
        $sl = (float) ($data['sl_adjustment'] ?? 0);
        $service_ = (float) ($data['service_adjustment'] ?? 0);

        if ($vl == 0.0 && $sl == 0.0 && $service_ == 0.0
            && (float) ($data['vl_used_wop'] ?? 0) == 0.0
            && (float) ($data['sl_used_wop'] ?? 0) == 0.0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'adjustment' => 'Enter at least one adjustment or without-pay value.',
            ]);
        }

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($data, $employee, $service, $vl, $sl, $service_) {
                $hasLeaveAdjustment = $vl != 0.0 || $sl != 0.0
                    || (float) ($data['vl_used_wop'] ?? 0) != 0.0
                    || (float) ($data['sl_used_wop'] ?? 0) != 0.0;

                if ($hasLeaveAdjustment) {
                    $service->postEntry(
                        employee: $employee,
                        periodFrom: $data['date'],
                        periodTo: $data['date'],
                        type: 'adjustment',
                        remarks: $data['remarks'],
                        vlEarned: max(0, $vl),
                        vlUsed: abs(min(0, $vl)),
                        vlUsedWop: (float) ($data['vl_used_wop'] ?? 0),
                        slEarned: max(0, $sl),
                        slUsed: abs(min(0, $sl)),
                        slUsedWop: (float) ($data['sl_used_wop'] ?? 0),
                        ledger: LeaveLedgerEntry::LEAVE,
                    );
                }

                if ($service_ != 0.0) {
                    $service->postEntry(
                        employee: $employee,
                        periodFrom: $data['date'],
                        periodTo: $data['date'],
                        type: 'adjustment',
                        remarks: $data['remarks'],
                        serviceEarned: max(0, $service_),
                        serviceUsed: abs(min(0, $service_)),
                        ledger: LeaveLedgerEntry::SERVICE,
                    );
                }
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Adjustment posted.');
    }

    /**
     * Corrects a line on the card.
     *
     * This replaces the old cell editor. That edited a copy of the workbook,
     * which is not where the printed card comes from, so a correction made
     * there never reached the card.
     */
    public function updateEntry(Request $request, LeaveLedgerEntry $entry, LeaveLedgerService $service)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Only HR can correct a ledger card.');

        $data = $request->validate([
            'period_from' => ['required', 'date'],
            'period_to' => ['required', 'date', 'after_or_equal:period_from'],
            'remarks' => ['nullable', 'string', 'max:255'],
            'vl_earned' => ['nullable', 'numeric', 'min:0'],
            'vl_used' => ['nullable', 'numeric', 'min:0'],
            'vl_used_wop' => ['nullable', 'numeric', 'min:0'],
            'sl_earned' => ['nullable', 'numeric', 'min:0'],
            'sl_used' => ['nullable', 'numeric', 'min:0'],
            'sl_used_wop' => ['nullable', 'numeric', 'min:0'],
            'service_earned' => ['nullable', 'numeric', 'min:0'],
            'service_used' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $service->updateEntry($entry, $data);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return back()->with('success', 'The ledger line has been corrected and the card recalculated.');
    }

    /** Strikes a line off the card. */
    public function destroyEntry(Request $request, LeaveLedgerEntry $entry, LeaveLedgerService $service)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Only HR can remove a ledger line.');

        $employee = $entry->user;

        try {
            $service->deleteEntry($entry);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "The line has been removed from {$employee->name}'s card.");
    }

    public function bulkStoreEarned(Request $request, LeaveLedgerService $service)
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only HR can post leave credits.');

        $data = $request->validate([
            'period_from' => ['required', 'date'],
            'period_to' => ['required', 'date', 'after_or_equal:period_from'],
            'vl_earned' => ['nullable', 'numeric', 'min:0'],
            'sl_earned' => ['nullable', 'numeric', 'min:0'],
            'service_earned' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:255'],
            'ledger' => ['nullable', Rule::in([LeaveLedgerEntry::LEAVE, LeaveLedgerEntry::SERVICE])],
        ]);

        // Deans and the Campus Director accrue credits too — they are staff
        // of the campus, not just reviewers of it.
        $employees = User::personnel()->where('status', 'active')->get();
        $skipped = [];
        $alreadyPosted = [];

        $ledger = $data['ledger'] ?? LeaveLedgerEntry::LEAVE;

        foreach ($employees as $employee) {
            // Posting the same month twice credits everybody twice, and the
            // running balance replays whatever rows exist — so the error
            // compounds quietly and only surfaces when someone's leave is
            // refused for credits they were never owed. A double-click, a
            // refresh, or two HR staff on the same day are all enough.
            $exists = $employee->leaveLedgerEntries()
                ->where('type', 'earned')
                ->where('ledger', $ledger)
                ->whereDate('period_from', $data['period_from'])
                ->whereDate('period_to', $data['period_to'])
                ->exists();

            if ($exists) {
                $alreadyPosted[] = $employee->name;

                continue;
            }

            try {
                $service->postEntry(
                    employee: $employee,
                    periodFrom: $data['period_from'],
                    periodTo: $data['period_to'],
                    type: 'earned',
                    // A validated 'nullable' field is simply absent when the
                    // box is left empty, so every read here needs a default.
                    remarks: ($data['remarks'] ?? null)
                        ?: "Earned {$data['period_from']} – {$data['period_to']}",
                    vlEarned: (float) ($data['vl_earned'] ?? 0),
                    slEarned: (float) ($data['sl_earned'] ?? 0),
                    serviceEarned: (float) ($data['service_earned'] ?? 0),
                    ledger: $ledger,
                );
            } catch (\RuntimeException $e) {
                $skipped[] = $employee->name;
            }
        }

        $posted = $employees->count() - count($skipped) - count($alreadyPosted);

        $message = 'Leave credits posted to ' . $posted . ' employee(s).';

        if ($alreadyPosted) {
            $message .= ' Already had this period: ' . implode(', ', $alreadyPosted) . '.';
        }

        if ($skipped) {
            $message .= ' Skipped (would go negative): ' . implode(', ', $skipped) . '.';
        }

        return back()->with('success', $message);
    }

    // ------------------------------------------------------------------
    // Exports
    // ------------------------------------------------------------------

    public function exportLedgerPdf(Request $request, User $employee)
    {
        $viewer = $request->user();

        abort_unless($viewer->isReviewer(), 403);
        abort_unless(
            ! $viewer->isDean() || $employee->college_id === $viewer->college_id,
            403,
            'This employee is not registered under your program.',
        );

        return LeaveApplicationController::renderLedgerCard(
            $employee,
            DocumentName::ledgerCard($employee),
        );
    }

    public function calendar(Request $request)
    {
        $viewer = $request->user();
        $month = $request->input('month', now()->format('Y-m'));
        $start = \Carbon\Carbon::parse($month . '-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        // Everything still moving through the chain is shown too, so HR can
        // spot clashes before approving. Returned forms never happened.
        $applications = $this->calendarQuery($viewer, $request)
            ->where('date_from', '<=', $end)
            ->where('date_to', '>=', $start)
            ->get();

        return view('admin.leave.calendar', [
            'days' => $this->buildCalendarDays($start, $end, $applications),
            'start' => $start,
            'end' => $end,
            'month' => $month,
            // A Dean has no college picker: their scope is fixed.
            'colleges' => $viewer->isDean()
                ? collect()
                : \App\Models\College::active()->orderBy('name')->get(),
            'viewerCollege' => $viewer->isDean() ? $viewer->college : null,
        ]);
    }

    /**
     * The calendar's base query, scoped server-side.
     *
     * A Dean sees only their own college. HR and the Campus Director see every
     * college, with an optional filter. The Dean's boundary is applied in the
     * query itself, not by hiding rows in the view.
     */
    private function calendarQuery(\App\Models\User $viewer, Request $request)
    {
        $query = LeaveApplication::with('user.college')
            ->whereNotIn('status', ['draft', 'dean_returned', 'hr_returned', 'cd_returned']);

        if ($viewer->isDean()) {
            // A Dean with no college sees nothing, rather than everything.
            $query->whereHas('user', fn ($q) => $q->where('college_id', $viewer->college_id ?? 0));

            return $query;
        }

        abort_unless($viewer->isAdmin() || $viewer->isCampusDirector(), 403);

        return $query->when(
            $request->college,
            fn ($q, $college) => $q->whereHas('user', fn ($u) => $u->where('college_id', $college)),
        );
    }

    private function buildCalendarDays($start, $end, $applications)
    {
        $days = [];
        $cursor = $start->copy();

        while ($cursor <= $end) {
            $days[$cursor->format('Y-m-d')] = collect();
            $cursor->addDay();
        }

        foreach ($applications as $app) {
            $rangeStart = $app->date_from->lt($start) ? $start->copy() : $app->date_from->copy();
            $rangeEnd = $app->date_to->gt($end) ? $end->copy() : $app->date_to->copy();
            $d = $rangeStart->copy();

            while ($d <= $rangeEnd) {
                $days[$d->format('Y-m-d')]->push($app);
                $d->addDay();
            }
        }

        return $days;
    }

    public function exportMonthPdf(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        $start = \Carbon\Carbon::parse($month . '-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        // The printed board only shows fully approved leave — listing forms
        // still under review as if they were confirmed would mislead.
        // Scoped the same way as the on-screen calendar.
        $applications = $this->calendarQuery($request->user(), $request)
            ->whereIn('status', ['cd_approved', 'completed'])
            ->where('date_from', '<=', $end)
            ->where('date_to', '>=', $start)
            ->get();

        $pdf = Pdf::loadView('admin.leave.calendar-pdf', [
            'days' => $this->buildCalendarDays($start, $end, $applications),
            'start' => $start,
            'end' => $end,
            'month' => $month,
            'generatedAt' => now(),
            'generatedBy' => auth()->user()->name ?? 'Admin',
        ])->setPaper('a4', 'landscape');

        return $pdf->stream(DocumentName::leaveCalendar($start));
    }

    public function exportAllPdf(Request $request)
    {
        // The same set the on-screen list shows, or the report silently
        // omits whoever is not a plain employee.
        $employees = User::personnel()
            ->visibleTo($request->user())
            ->with('leaveBalance')
            ->orderBy('name')
            ->get();

        $pdf = Pdf::loadView('admin.leave.all-balances-pdf', [
            'employees' => $employees,
            'generatedAt' => now(),
            'generatedBy' => auth()->user()->name ?? 'Admin',
        ])->setPaper('a4', 'portrait');

        return $pdf->stream(DocumentName::leaveBalances());
    }

    public function exportAllExcel(Request $request)
    {
        $employees = User::personnel()
            ->visibleTo($request->user())
            ->with('leaveBalance')
            ->orderBy('name')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(
            ['Employee No.', 'Name', 'College/Office', 'VL Balance', 'SL Balance', 'Service Credits'],
            null,
            'A1'
        );

        $row = 2;
        foreach ($employees as $employee) {
            $sheet->fromArray([
                $employee->employee_number,
                $employee->name,
                $employee->department,
                number_format($employee->leaveBalance->vl_balance ?? 0, 2),
                number_format($employee->leaveBalance->sl_balance ?? 0, 2),
                number_format($employee->leaveBalance->service_balance ?? 0, 2),
            ], null, "A{$row}");
            $row++;
        }

        $filename = DocumentName::leaveBalances(extension: 'xlsx');
        $tempPath = storage_path('app/temp/' . $filename);

        if (! is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        (new Xlsx($spreadsheet))->save($tempPath);

        return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
    }
}
