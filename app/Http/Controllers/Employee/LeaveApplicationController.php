<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\LeaveApplication;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeaveApplicationController extends Controller
{
    public function index(Request $request)
    {
        $applications = Auth::user()->leaveApplications()
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->type, fn ($q, $type) => $q->where('leave_type', $type))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $balance = Auth::user()->leaveBalance;

        $pendingCount = Auth::user()->leaveApplications()->where('status', 'pending')->count();
        $approvedThisYear = Auth::user()->leaveApplications()
            ->where('status', 'approved')
            ->whereYear('date_from', now()->year)
            ->sum('days');

        $ledger = Auth::user()->leaveLedgerEntries()->orderBy('period_from')->take(6)->get()->reverse();

        return view('employee.leave.index', compact(
            'applications', 'balance', 'pendingCount', 'approvedThisYear', 'ledger'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'leave_type' => ['required', 'in:VL,SL'],
            'date_from' => ['required', 'date', 'after_or_equal:today'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $days = \Carbon\Carbon::parse($data['date_from'])
            ->diffInWeekdays(\Carbon\Carbon::parse($data['date_to'])->addDay());

        $balance = Auth::user()->leaveBalance;
        $available = $data['leave_type'] === 'VL' ? ($balance->vl_balance ?? 0) : ($balance->sl_balance ?? 0);

        if ($days > $available) {
            return back()->withErrors(['date_to' => "You only have {$available} day(s) of " . ($data['leave_type'] === 'VL' ? 'Vacation' : 'Sick') . " Leave available."])->withInput();
        }

        LeaveApplication::create([
            ...$data,
            'user_id' => Auth::id(),
            'days' => $days,
            'status' => 'pending',
        ]);

        return back()->with('success', "Leave application submitted for HR review ({$days} day(s)).");
    }

    public function exportLedgerPdf()
    {
        $employee = Auth::user();
        $ledger = $employee->leaveLedgerEntries()->orderBy('period_from')->get();
        $balance = $employee->leaveBalance;

        $pdf = Pdf::loadView('employee.leave.ledger-pdf', [
            'employee' => $employee,
            'ledger' => $ledger,
            'balance' => $balance,
            'generatedAt' => now(),
        ])->setPaper('legal', 'landscape');

        $filename = 'My_Leave_Ledger_' . now()->format('Ymd') . '.pdf';

        return $pdf->stream($filename);
    }
}
