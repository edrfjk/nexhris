<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\PdsPdfExportService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PdsPdfController extends Controller
{
    public function download(PdsPdfExportService $pdfExport)
    {
        $user = Auth::user()->load([
            'pdsPersonalInformation', 'pdsFamilyBackground', 'pdsChildren',
            'pdsEducationalBackgrounds', 'pdsCivilServiceEligibilities',
            'pdsWorkExperiences', 'pdsVoluntaryWorks', 'pdsTrainings',
            'pdsOtherInformation', 'pdsQuestionnaire', 'pdsReferences', 'pdsDeclaration',
        ]);

        $baseName = 'PDS_' . preg_replace('/[^A-Za-z0-9_]/', '_', $user->name) . '_' . now()->format('Ymd_His');

        try {
            return response($pdfExport->render($user), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$baseName}.pdf\"",
            ]);
        } catch (\Throwable $e) {
            Log::error('PDS PDF generation failed: ' . $e->getMessage());

            return back()->with('error', 'Your PDS PDF could not be generated. Please try again or contact HR if the problem continues.');
        }
    }
}
