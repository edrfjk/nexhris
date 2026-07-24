<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PdsSpreadsheetExportService;
use App\Services\PdsCheckboxService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class PdsPdfController extends Controller
{
    public function download(PdsSpreadsheetExportService $exportService, PdsCheckboxService $checkboxService)
    {
        $user = Auth::user()->load([
            'pdsPersonalInformation', 'pdsFamilyBackground', 'pdsChildren',
            'pdsEducationalBackgrounds', 'pdsCivilServiceEligibilities',
            'pdsWorkExperiences', 'pdsVoluntaryWorks', 'pdsTrainings',
            'pdsOtherInformation', 'pdsQuestionnaire', 'pdsReferences', 'pdsDeclaration',
        ]);

        $spreadsheet = $exportService->fill($user);

        $baseName = 'PDS_' . preg_replace('/[^A-Za-z0-9_]/', '_', $user->name) . '_' . now()->format('Ymd_His');
        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $xlsxPath = "{$tempDir}/{$baseName}.xlsx";
        $pdfPath = "{$tempDir}/{$baseName}.pdf";

        (new Xlsx($spreadsheet))->save($xlsxPath);

        $checkboxService->apply(
            $xlsxPath,
            $this->buildLabelChecks($user),
            $this->buildYesNoChecks($user)
        );

        try {
            $this->convertToPdf($xlsxPath, $tempDir);

            if (!file_exists($pdfPath)) {
                throw new \RuntimeException('PDF conversion did not produce an output file.');
            }

            unlink($xlsxPath);

            return response()->download($pdfPath, "{$baseName}.pdf")->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            Log::warning('PDS PDF conversion failed, falling back to Excel download: ' . $e->getMessage());

            return response()->download($xlsxPath, "{$baseName}.xlsx")->deleteFileAfterSend(true);
        }
    }

    private function convertToPdf(string $xlsxPath, string $outDir): void
    {
        $sofficeBinary = $this->resolveSofficeBinary();

        $process = new Process([
            $sofficeBinary,
            '--headless',
            '--convert-to', 'pdf',
            '--outdir', $outDir,
            $xlsxPath,
        ]);

        $process->setTimeout(60);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    private function resolveSofficeBinary(): string
    {
        return config('services.soffice.path');
    }

    private function buildLabelChecks(User $user): array
    {
        $p = $user->pdsPersonalInformation;
        $checks = [];

        if ($p) {
            if ($p->sex) {
                $checks[$p->sex] = true; // "Male" or "Female"
            }

            $civilMap = [
                'Single' => 'Single',
                'Married' => 'Married',
                'Widowed' => 'Widowed',
                'Separated' => 'Separated',
                'Solo Parent' => 'Other/s:', // template has no dedicated Solo Parent box
                'Others' => 'Other/s:',
            ];
            if (isset($civilMap[$p->civil_status])) {
                $checks[$civilMap[$p->civil_status]] = true;
            }

            if ($p->citizenship === 'Filipino') {
                $checks['Filipino'] = true;
            }
        }

        return $checks;
    }

    private function buildYesNoChecks(User $user): array
    {
        $q = $user->pdsQuestionnaire;
        if (!$q) {
            return [];
        }

        return [
            6 => $q->related_third_degree,
            8 => $q->related_fourth_degree,
            13 => $q->found_admin_guilty,
            18 => $q->criminally_charged,
            23 => $q->convicted_crime,
            27 => $q->separated_from_service,
            31 => $q->candidate_in_election,
            34 => $q->resigned_before_election,
            37 => $q->acquired_immigrant_status,
            43 => $q->is_indigenous_group_member,
            45 => $q->is_pwd,
            47 => $q->is_solo_parent,
        ];
    }
}