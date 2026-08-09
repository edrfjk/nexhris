<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\PdsSubmission;
use App\Models\PdsTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Process\Process;

class PdsEditorController extends Controller
{
    public function show()
    {
        $template = PdsTemplate::where('is_active', true)->first();

        $submission = PdsSubmission::firstOrCreate(
            ['user_id' => Auth::id(), 'applicable_year' => now()->year],
            ['status' => 'not_started']
        );

        return view('employee.pds.editor', compact('template', 'submission'));
    }

    public function upload(Request $request)
    {
        $template = PdsTemplate::where('is_active', true)->firstOrFail();

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ]);

        // Sanity check: confirm the uploaded file's sheet structure matches
        // the official template, so employees can't upload an unrelated file.
        try {
            $uploaded = IOFactory::load($request->file('file')->getPathname());
            $official = IOFactory::load(Storage::disk('public')->path($template->file_path));

            $uploadedSheets = $uploaded->getSheetNames();
            $officialSheets = $official->getSheetNames();
            sort($uploadedSheets);
            sort($officialSheets);

            if ($uploadedSheets !== $officialSheets) {
                return back()->withErrors([
                    'file' => 'This file does not match the official PDS template. Please download the template again and fill it in without renaming or removing sheets.',
                ]);
            }
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => 'This file could not be read as a valid Excel document.']);
        }

        $submission = PdsSubmission::firstOrCreate(
            ['user_id' => Auth::id(), 'applicable_year' => now()->year],
            ['status' => 'not_started']
        );

        if ($submission->file_path && Storage::disk('public')->exists($submission->file_path)) {
            Storage::disk('public')->delete($submission->file_path);
        }

        $path = $request->file('file')->storeAs('pds-working', Auth::id() . '_' . now()->year . '.xlsx', 'public');

        $submission->update([
            'file_path' => $path,
            'pds_template_id' => $template->id,
            'status' => $submission->status === 'not_started' ? 'draft' : $submission->status,
        ]);

        return back()->with('success', 'Your PDS file has been uploaded successfully.');
    }

    public function submit()
    {
        $submission = PdsSubmission::where('user_id', Auth::id())->where('applicable_year', now()->year)->firstOrFail();

        abort_unless($submission->file_path, 422, 'Please upload your completed PDS before submitting.');

        $submission->update(['status' => 'submitted', 'submitted_at' => now()]);

        return redirect()->route('pds.editor')->with('success', 'PDS submitted to HR for review.');
    }

    public function exportPdf()
    {
        $submission = PdsSubmission::where('user_id', Auth::id())->where('applicable_year', now()->year)->firstOrFail();
        abort_unless($submission->file_path, 422, 'Please upload your completed PDS before exporting.');

        $xlsxPath = Storage::disk('public')->path($submission->file_path);
        $outDir = dirname($xlsxPath);
        $pdfPath = preg_replace('/\.xlsx$/', '.pdf', $xlsxPath);

        $profileDir = storage_path('app/temp/lo_profile');
        if (!is_dir($profileDir)) {
            mkdir($profileDir, 0755, true);
        }
        $profileUrl = 'file:///' . str_replace('\\', '/', $profileDir);

        $process = new Process([
            config('services.soffice.path'),
            '--headless', '--norestore',
            "-env:UserInstallation={$profileUrl}",
            '--convert-to=pdf',
            '--outdir', $outDir,
            $xlsxPath,
        ]);
        $process->setTimeout(90);
        $process->run();

        if (!$process->isSuccessful() || !file_exists($pdfPath)) {
            return back()->with('error', 'PDF conversion failed. You can still download your saved Excel file.');
        }

        return response()->file($pdfPath, ['Content-Disposition' => 'inline; filename="My_PDS.pdf"']);
    }
}