<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboard)
    {
    }

    public function index(Request $request)
    {
        return view('employee.dashboard', [
            'data' => $this->dashboard->forEmployee($request->user()),
        ]);
    }
}
