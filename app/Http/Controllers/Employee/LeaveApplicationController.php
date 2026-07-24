<?php
namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\LeaveApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeaveApplicationController extends Controller
{
    public function index()
    {
        $applications = Auth::user()->leaveApplications()->latest()->paginate(10);
        $balance = Auth::user()->leaveBalance;

        return view('employee.leave.index', compact('applications', 'balance'));
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
            return back()->withErrors(['date_to' => 'Insufficient leave credits for this request.']);
        }

        LeaveApplication::create([
            ...$data,
            'user_id' => Auth::id(),
            'days' => $days,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Leave application submitted for HR review.');
    }
}