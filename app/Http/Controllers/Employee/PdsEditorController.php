<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\PdsTemplate;
use App\Services\PdsSubmissionService;
use App\Services\XlsxToPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * The employee side of the PDS: download the official blank, fill it offline,
 * upload the workbook back, and follow it through HR review.
 *
 * Applies to every role — Deans and the Campus Director file a PDS too.
 */
class PdsEditorController extends Controller
{
    public function __construct(private PdsSubmissionService $pds)
    {
    }

    public function show()
    {
        $submission = $this->pds->forYear(Auth::user());
        $submission->load('revisions.reviewer', 'template', 'reviewer');

        return view('employee.pds.editor', [
            'template' => PdsTemplate::active(),
            'submission' => $submission,
        ]);
    }

    /** Downloads the blank workbook HR published. */
    public function downloadTemplate()
    {
        $template = PdsTemplate::active();

        abort_unless($template && $template->exists(), 404,
            'No PDS template has been published yet. Please contact the HR Office.');

        return Storage::disk('public')->download($template->file_path, $template->original_filename);
    }

    public function upload(Request $request)
    {
        $template = PdsTemplate::active();

        if (! $template) {
            return back()->withErrors([
                'file' => 'No PDS template has been published yet, so uploads cannot be checked against it.',
            ]);
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ], [
            'file.mimes' => 'Upload the .xlsx workbook you downloaded, not a PDF or a scan.',
        ]);

        $submission = $this->pds->forYear(Auth::user());

        abort_unless($submission->isEditable(), 422,
            'This PDS has already been submitted. Wait for HR to review it, or ask them to return it.');

        // The upload must be the official workbook, not an arbitrary file.
        if ($error = $this->pds->validateWorkbook($request->file('file'), $template)) {
            return back()->withErrors(['file' => $error]);
        }

        $submission = $this->pds->storeUpload($submission, $request->file('file'), $template);

        return back()->with(
            $submission->pdfExists() ? 'success' : 'warning',
            $submission->pdfExists()
                ? 'Your PDS was uploaded and converted to PDF. Review the preview, then submit it to HR.'
                : 'Your PDS was uploaded, but the PDF preview could not be generated. '
                    . 'You can still submit it — HR will be able to open the workbook.'
        );
    }

    public function submit()
    {
        $submission = $this->pds->forYear(Auth::user());

        abort_unless($submission->workbookExists(), 422,
            'Upload your completed PDS before submitting it.');
        abort_unless($submission->isEditable(), 422,
            'This PDS has already been submitted.');

        $this->pds->submit($submission);

        return redirect()->route('pds.editor')
            ->with('success', 'Your PDS has been submitted to HR for review.');
    }

    /** The employee's own PDS as a PDF, at any time. */
    public function exportPdf(XlsxToPdfService $converter)
    {
        $submission = $this->pds->forYear(Auth::user());

        abort_unless($submission->workbookExists(), 422,
            'Upload your completed PDS before exporting it.');

        // Serve the stored conversion when it exists; convert on demand if the
        // upload-time conversion had failed.
        if ($submission->pdfExists()) {
            return response()->file($submission->pdfPath(), [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="My_PDS_' . $submission->applicable_year . '.pdf"',
            ]);
        }

        try {
            return $converter->stream(
                $submission->workbookPath(),
                'My_PDS_' . $submission->applicable_year . '.pdf'
            );
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
