<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class ExcelToPdfConverter
{
    public function convert(string $xlsxAbsolutePath): string
    {
        if (!file_exists($xlsxAbsolutePath)) {
            throw new \RuntimeException("Source file not found: {$xlsxAbsolutePath}");
        }

        $outDir = dirname($xlsxAbsolutePath);
        $pdfPath = preg_replace('/\.xlsx$/i', '.pdf', $xlsxAbsolutePath);

        // A fresh, unique profile folder per conversion avoids lock
        // contention if a prior LibreOffice process hasn't fully released
        // a shared profile folder yet — the main cause of silent failures.
        $profileDir = storage_path('app/temp/lo_profile_' . Str::random(12));
        if (!is_dir($profileDir)) {
            mkdir($profileDir, 0755, true);
        }
        $profileUrl = 'file:///' . str_replace('\\', '/', $profileDir);

        $process = new Process([
            config('services.soffice.path'),
            '--headless',
            '--norestore',
            "-env:UserInstallation={$profileUrl}",
            '--convert-to', 'pdf',
            '--outdir', $outDir,
            $xlsxAbsolutePath,
        ]);
        $process->setTimeout(90);
        $process->run();

        File::deleteDirectory($profileDir);

        if (!$process->isSuccessful() || !file_exists($pdfPath)) {
            Log::error('Excel to PDF conversion failed', [
                'command' => $process->getCommandLine(),
                'exitCode' => $process->getExitCode(),
                'output' => $process->getOutput(),
                'errorOutput' => $process->getErrorOutput(),
            ]);

            throw new \RuntimeException('PDF conversion failed. Please try again, or contact HR if the problem continues.');
        }

        return $pdfPath;
    }
}