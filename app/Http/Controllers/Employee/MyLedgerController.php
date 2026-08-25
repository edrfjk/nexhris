<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * A person's own leave and service credit card.
 *
 * The card itself is drawn from the posted ledger entries in the campus
 * template's layout, so this page only has to frame it — there is no workbook
 * to seed and nothing to convert.
 */
class MyLedgerController extends Controller
{
    public function show(Request $request)
    {
        $viewer = $request->user();

        return view('leave.my-ledger', [
            'employee' => $viewer,
            'balance' => $viewer->leaveBalance,
        ]);
    }
}
