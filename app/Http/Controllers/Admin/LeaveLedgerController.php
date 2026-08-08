<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\LeaveApplication;
use App\Services\LeaveLedgerService;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Http\Request;

class LeaveLedgerController extends Controller
{
public function show(User $employee)
{
    $ledger = $employee->leaveLedgerEntries()->orderBy('period_from')->get();
    $balance = $employee->leaveBalance;
    $applications = $employee->leaveApplications()->latest()->paginate(10, ['*'], 'apps_page');

    return view('admin.leave.ledger', compact('employee', 'ledger', 'balance', 'applications'));
}

public function storeEarned(Request $request, User $employee, LeaveLedgerService $service)
{
    $data = $request->validate([
        'period_from' => ['required', 'date'],
        'period_to' => ['required', 'date', 'after_or_equal:period_from'],
        'vl_earned' => ['nullable', 'numeric'],
        'sl_earned' => ['nullable', 'numeric'],
        'remarks' => ['nullable', 'string', 'max:255'],
    ]);

    try {
        $service->postEntry(
            employee: $employee,
            periodFrom: $data['period_from'],
            periodTo: $data['period_to'],
            type: 'earned',
            remarks: $data['remarks'] ?? "Earned during {$data['period_from']} - {$data['period_to']}",
            vlEarned: $data['vl_earned'] ?? 0,
            slEarned: $data['sl_earned'] ?? 0,
        );
    } catch (\RuntimeException $e) {
        return back()->with('error', $e->getMessage());
    }

    return back()->with('success', 'Leave credits posted to ledger.');
}

public function storeAdjustment(Request $request, User $employee, LeaveLedgerService $service)
{
    $data = $request->validate([
        'date' => ['required', 'date'],
        'vl_adjustment' => ['nullable', 'numeric'],
        'sl_adjustment' => ['nullable', 'numeric'],
        'remarks' => ['required', 'string', 'max:255'],
    ]);

    try {
        $service->postEntry(
            employee: $employee,
            periodFrom: $data['date'],
            periodTo: $data['date'],
            type: 'adjustment',
            remarks: $data['remarks'],
            vlEarned: max(0, $data['vl_adjustment'] ?? 0),
            vlUsed: abs(min(0, $data['vl_adjustment'] ?? 0)),
            slEarned: max(0, $data['sl_adjustment'] ?? 0),
            slUsed: abs(min(0, $data['sl_adjustment'] ?? 0)),
        );
    } catch (\RuntimeException $e) {
        return back()->with('error', $e->getMessage());
    }

    return back()->with('success', 'Adjustment posted.');
}

public function bulkStoreEarned(Request $request, LeaveLedgerService $service)
{
    $data = $request->validate([
        'period_from' => ['required', 'date'],
        'period_to' => ['required', 'date', 'after_or_equal:period_from'],
        'vl_earned' => ['nullable', 'numeric', 'min:0'],
        'sl_earned' => ['nullable', 'numeric', 'min:0'],
        'remarks' => ['nullable', 'string', 'max:255'],
    ]);

    $employees = User::where('role', 'employee')->where('status', 'active')->get();
    $skipped = [];

    foreach ($employees as $employee) {
        try {
            $service->postEntry(
                employee: $employee,
                periodFrom: $data['period_from'],
                periodTo: $data['period_to'],
                type: 'earned',
                remarks: $data['remarks'] ?? "Earned during {$data['period_from']} - {$data['period_to']}",
                vlEarned: $data['vl_earned'] ?? 0,
                slEarned: $data['sl_earned'] ?? 0,
            );
        } catch (\RuntimeException $e) {
            $skipped[] = $employee->name;
        }
    }

    $message = "Leave credits posted to " . ($employees->count() - count($skipped)) . " employee(s).";
    if ($skipped) {
        $message .= " Skipped: " . implode(', ', $skipped) . " (would go negative).";
    }

    return back()->with('success', $message);
}


    public function pending(Request $request)
        {
            $applications = LeaveApplication::with('user')
                ->where('status', 'pending')
                ->when($request->type, fn ($q, $type) => $q->where('leave_type', $type))
                ->latest()
                ->paginate(15)
                ->withQueryString();

            return view('admin.leave.pending', compact('applications'));
    }

public function approve(Request $request, LeaveApplication $application, LeaveLedgerService $service)
{
    $application->update([
        'status' => 'approved',
        'reviewed_by' => auth()->id(),
        'reviewed_at' => now(),
    ]);

    $entry = $service->postEntry(
        employee: $application->user,
        periodFrom: $application->date_from,
        periodTo: $application->date_to,
        type: 'leave_deduction',
        remarks: "{$application->leave_type} - " . $application->date_from->format('M d') . ' to ' . $application->date_to->format('M d'),
        vlUsed: $application->leave_type === 'VL' ? $application->days : 0,
        slUsed: $application->leave_type === 'SL' ? $application->days : 0,
        leaveApplicationId: $application->id,
    );

    $application->update(['leave_ledger_entry_id' => $entry->id]);

    return back()->with('success', 'Leave application approved and deducted from balance.');
}

public function decline(Request $request, LeaveApplication $application)
{
    $request->validate(['remarks' => 'required|string|max:255']);

    $application->update([
        'status' => 'declined',
        'reviewed_by' => auth()->id(),
        'reviewed_at' => now(),
        'remarks' => $request->remarks,
    ]);

    return back()->with('success', 'Leave application declined.');
}

public function index(Request $request)
{
    $employees = User::where('role', 'employee')
        ->with('leaveBalance')
        ->when($request->search, function ($query, $search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('employee_number', 'like', "%{$search}%");
            });
        })
        ->when($request->college, fn ($q, $college) => $q->where('department', $college))
        ->when($request->sort === 'low_balance', function ($q) {
            $q->leftJoin('leave_balances', 'leave_balances.user_id', '=', 'users.id')
              ->orderByRaw('COALESCE(leave_balances.vl_balance, 0) + COALESCE(leave_balances.sl_balance, 0) ASC')
              ->select('users.*');
        }, fn ($q) => $q->orderBy('name'))
        ->paginate(15)
        ->withQueryString();

    $pendingCount = LeaveApplication::where('status', 'pending')->count();
    $colleges = config('colleges');

    return view('admin.leave.index', compact('employees', 'pendingCount', 'colleges'));
}


public function exportLedgerPdf(User $employee)
{
    $ledger = $employee->leaveLedgerEntries()->orderBy('period_from')->get();
    $balance = $employee->leaveBalance;

    $pdf = Pdf::loadView('admin.leave.ledger-pdf', [
        'employee' => $employee,
        'ledger' => $ledger,
        'balance' => $balance,
        'generatedAt' => now(),
        'generatedBy' => auth()->user()->name ?? 'Admin',
    ])->setPaper('legal', 'landscape');

    $filename = 'Leave_Ledger_' . preg_replace('/[^A-Za-z0-9_]/', '_', $employee->name) . '_' . now()->format('Ymd') . '.pdf';

    return $pdf->stream($filename);
}


// ── Replace the existing calendar() method in LeaveLedgerController with this ──

public function calendar(Request $request)
{
    $month = $request->input('month', now()->format('Y-m'));
    $start = \Carbon\Carbon::parse($month . '-01')->startOfMonth();
    $end = $start->copy()->endOfMonth();

    // Widened from ->where('status', 'approved') to include pending too, so HR can
    // spot potential scheduling conflicts before approving. Declined applications
    // are still excluded since they never actually happened.
    $applications = LeaveApplication::with('user')
        ->whereIn('status', ['approved', 'pending'])
        ->where('date_from', '<=', $end)
        ->where('date_to', '>=', $start)
        ->get();

    $days = $this->buildCalendarDays($start, $end, $applications);

    return view('admin.leave.calendar', compact('days', 'start', 'end', 'month'));
}

// ── New: extracted the day-bucketing loop into its own method so both calendar()
//    and exportMonthPdf() can share it instead of duplicating the logic ──

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

// ── New: export the visible month as a printable PDF ──

public function exportMonthPdf(Request $request)
{
    $month = $request->input('month', now()->format('Y-m'));
    $start = \Carbon\Carbon::parse($month . '-01')->startOfMonth();
    $end = $start->copy()->endOfMonth();

    // PDF only shows approved leaves — a printed board/payroll document showing
    // still-pending requests as if they were confirmed would be misleading.
    $applications = LeaveApplication::with('user')
        ->where('status', 'approved')
        ->where('date_from', '<=', $end)
        ->where('date_to', '>=', $start)
        ->get();

    $days = $this->buildCalendarDays($start, $end, $applications);

    $pdf = Pdf::loadView('admin.leave.calendar-pdf', [
        'days' => $days,
        'start' => $start,
        'end' => $end,
        'month' => $month,
        'generatedAt' => now(),
        'generatedBy' => auth()->user()->name ?? 'Admin',
    ])->setPaper('legal', 'landscape');

    $filename = 'Leave_Calendar_' . $start->format('F_Y') . '.pdf';

    return $pdf->stream($filename);
}

public function exportAllPdf()
{
    $employees = User::where('role', 'employee')->with('leaveBalance')->orderBy('name')->get();

    $pdf = Pdf::loadView('admin.leave.all-balances-pdf', [
        'employees' => $employees,
        'generatedAt' => now(),
        'generatedBy' => auth()->user()->name ?? 'Admin',
    ])->setPaper('legal', 'portrait');

    return $pdf->stream('Leave_Balances_' . now()->format('Ymd') . '.pdf');
}

public function exportAllExcel()
{
    $employees = User::where('role', 'employee')->with('leaveBalance')->orderBy('name')->get();

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray(['Employee No.', 'Name', 'College/Office', 'VL Balance', 'SL Balance'], null, 'A1');

    $row = 2;
    foreach ($employees as $employee) {
        $sheet->fromArray([
            $employee->employee_number,
            $employee->name,
            $employee->department,
            number_format($employee->leaveBalance->vl_balance ?? 0, 3),
            number_format($employee->leaveBalance->sl_balance ?? 0, 3),
        ], null, "A{$row}");
        $row++;
    }

    $filename = 'Leave_Balances_' . now()->format('Ymd') . '.xlsx';
    $tempPath = storage_path('app/temp/' . $filename);
    if (!is_dir(dirname($tempPath))) {
        mkdir(dirname($tempPath), 0755, true);
    }

    (new Xlsx($spreadsheet))->save($tempPath);

    return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
}

}