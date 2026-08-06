<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PdsSubmission;
use App\Models\User;
use App\Services\PdsPdfExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PdsReviewController extends Controller
{

    public function index(Request $request)
    {
        $year = $request->input('year', now()->year);
        $years = collect(range(now()->year, now()->year - 2))->values();

        $employees = User::where('role', 'employee')
            ->with(['pdsSubmissions' => fn ($q) => $q->where('applicable_year', $year)])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%");
                });
            })
            ->when($request->status, function ($query, $status) use ($year) {
                if ($status === 'not_started') {
                    // True "not started" = no submission row for this year at all,
                    // OR a row that explicitly still says not_started.
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

        $totalEmployees = User::where('role', 'employee')->count();

        $counts = PdsSubmission::where('applicable_year', $year)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.pds.index', compact('employees', 'year', 'years', 'counts', 'totalEmployees'));
    }



    public function show(User $employee)
    {
        $employee->load([
            'pdsPersonalInformation', 'pdsFamilyBackground', 'pdsChildren',
            'pdsEducationalBackgrounds', 'pdsCivilServiceEligibilities',
            'pdsWorkExperiences', 'pdsVoluntaryWorks', 'pdsTrainings',
            'pdsOtherInformation', 'pdsQuestionnaire', 'pdsReferences', 'pdsDeclaration',
        ]);

        $submission = PdsSubmission::where('user_id', $employee->id)
            ->where('applicable_year', now()->year)
            ->first();

        return view('admin.pds.show', compact('employee', 'submission'));
    }

    public function approve(Request $request, User $employee)
    {
        $submission = PdsSubmission::where('user_id', $employee->id)
            ->where('applicable_year', now()->year)
            ->firstOrFail();

        $submission->update([
            'status' => 'approved',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'return_remarks' => null,
        ]);

        return redirect()->route('admin.pds.index')->with('success', "{$employee->name}'s PDS has been approved.");
    }

    public function returnForRevision(Request $request, User $employee)
    {
        $data = $request->validate([
            'return_remarks' => ['required', 'string', 'max:1000'],
        ]);

        $submission = PdsSubmission::where('user_id', $employee->id)
            ->where('applicable_year', now()->year)
            ->firstOrFail();

        $submission->update([
            'status' => 'returned',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'return_remarks' => $data['return_remarks'],
        ]);

        return redirect()->route('admin.pds.index')->with('success', "{$employee->name}'s PDS was returned for revision.");
    }

    public function download(User $employee, PdsPdfExportService $pdfExport)
    {
        $employee->load([
            'pdsPersonalInformation', 'pdsFamilyBackground', 'pdsChildren',
            'pdsEducationalBackgrounds', 'pdsCivilServiceEligibilities',
            'pdsWorkExperiences', 'pdsVoluntaryWorks', 'pdsTrainings',
            'pdsOtherInformation', 'pdsQuestionnaire', 'pdsReferences', 'pdsDeclaration',
        ]);

        $baseName = 'PDS_' . preg_replace('/[^A-Za-z0-9_]/', '_', $employee->name) . '_' . now()->format('Ymd_His');

        try {
            return response($pdfExport->render($employee), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "inline; filename=\"{$baseName}.pdf\"",
            ]);
        } catch (\Throwable $e) {
            Log::error('PDS PDF generation failed (admin view) for user ' . $employee->id . ': ' . $e->getMessage());

            return back()->with('error', "This employee's PDS PDF could not be generated. Please try again or check the error log.");
        }
    }
}