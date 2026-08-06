<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\LeaveApplication;
use App\Services\LeaveLedgerService;
use Illuminate\Http\Request;

class LeaveLedgerController extends Controller
{
    public function show(User $employee)
    {
        $ledger = $employee->leaveLedgerEntries()->orderBy('period_from')->get();
        $balance = $employee->leaveBalance;

        return view('admin.leave.ledger', compact('employee', 'ledger', 'balance'));
    }

    public function storeEarned(Request $request, User $employee, LeaveLedgerService $service)
    {
        $data = $request->validate([
            'period_from' => ['required', 'date'],
            'period_to' => ['required', 'date', 'after_or_equal:period_from'],
            'vl_earned' => ['nullable', 'numeric', 'min:0'],
            'sl_earned' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $service->postEntry(
            employee: $employee,
            periodFrom: $data['period_from'],
            periodTo: $data['period_to'],
            type: 'earned',
            remarks: $data['remarks'] ?? "Earned during {$data['period_from']} - {$data['period_to']}",
            vlEarned: $data['vl_earned'] ?? 0,
            slEarned: $data['sl_earned'] ?? 0,
        );

        return back()->with('success', 'Leave credits posted to ledger.');
    }

    public function storeAdjustment(Request $request, User $employee, LeaveLedgerService $service)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'vl_adjustment' => ['nullable', 'numeric'], // can be negative
            'sl_adjustment' => ['nullable', 'numeric'],
            'remarks' => ['required', 'string', 'max:255'],
        ]);

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

        return back()->with('success', 'Adjustment posted.');
    }


    public function pending()
{
    $applications = \App\Models\LeaveApplication::with('user')
        ->where('status', 'pending')->latest()->paginate(15);

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
        ->orderBy('name')
        ->paginate(15)
        ->withQueryString();

    $pendingCount = \App\Models\LeaveApplication::where('status', 'pending')->count();

    return view('admin.leave.index', compact('employees', 'pendingCount'));
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

    foreach ($employees as $employee) {
        $service->postEntry(
            employee: $employee,
            periodFrom: $data['period_from'],
            periodTo: $data['period_to'],
            type: 'earned',
            remarks: $data['remarks'] ?? "Earned during {$data['period_from']} - {$data['period_to']}",
            vlEarned: $data['vl_earned'] ?? 0,
            slEarned: $data['sl_earned'] ?? 0,
        );
    }

    return back()->with('success', "Leave credits posted to {$employees->count()} employee(s).");
}
}