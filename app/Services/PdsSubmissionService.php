<?php

namespace App\Services;

use App\Models\PdsSubmission;
use App\Models\PdsSubmissionRevision;
use App\Models\PdsTemplate;
use App\Models\User;
use App\Notifications\PdsStatusChanged;
use App\Support\Notifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * The PDS round trip: download a blank, fill it offline, upload the workbook,
 * convert it to PDF, and carry it through HR review.
 *
 * Both files are kept. HR reviews the PDF; the workbook stays as the record of
 * what the employee actually submitted.
 */
class PdsSubmissionService
{
    public function __construct(
        private XlsxToPdfService $converter,
        private ActivityLogger $log,
    ) {
    }

    /** The employee's submission for a year, created on first visit. */
    public function forYear(User $employee, ?int $year = null): PdsSubmission
    {
        return PdsSubmission::firstOrCreate(
            ['user_id' => $employee->id, 'applicable_year' => $year ?? now()->year],
            ['status' => 'not_started', 'version' => 1],
        );
    }

    /**
     * Checks an uploaded workbook really is the official PDS.
     *
     * Returns null when valid, or a message explaining the mismatch.
     */
    public function validateWorkbook(UploadedFile $file, PdsTemplate $template): ?string
    {
        if (! $template->exists()) {
            return 'The official PDS template is missing from the server. Please contact the HR Office.';
        }

        try {
            $uploaded = IOFactory::load($file->getPathname());
            $official = IOFactory::load($template->absolutePath());

            $uploadedSheets = $uploaded->getSheetNames();
            $officialSheets = $official->getSheetNames();

            $uploaded->disconnectWorksheets();
            $official->disconnectWorksheets();

            sort($uploadedSheets);
            sort($officialSheets);

            if ($uploadedSheets !== $officialSheets) {
                $missing = array_diff($officialSheets, $uploadedSheets);
                $extra = array_diff($uploadedSheets, $officialSheets);

                $detail = $missing ? ' Missing sheet(s): ' . implode(', ', $missing) . '.' : '';
                $detail .= $extra ? ' Unexpected sheet(s): ' . implode(', ', $extra) . '.' : '';

                return 'This workbook does not match the official PDS template.' . $detail
                    . ' Download the template again and fill it in without renaming or removing sheets.';
            }
        } catch (\Throwable $e) {
            return 'This file could not be read as an Excel workbook. '
                . 'Make sure you are uploading the .xlsx you downloaded, not a PDF or a scan.';
        }

        return null;
    }

    /**
     * Stores the workbook, converts it to PDF, and archives whatever the
     * employee had uploaded before.
     */
    public function storeUpload(PdsSubmission $submission, UploadedFile $file, PdsTemplate $template): PdsSubmission
    {
        $submission = DB::transaction(function () use ($submission, $file, $template) {
            // Keep the previous attempt, together with the verdict it drew.
            if ($submission->workbookExists()) {
                PdsSubmissionRevision::create([
                    'pds_submission_id' => $submission->id,
                    'version' => $submission->version,
                    'pds_template_id' => $submission->pds_template_id,
                    'file_path' => $submission->file_path,
                    'pdf_path' => $submission->pdf_path,
                    'file_original_name' => $submission->file_original_name,
                    'outcome' => $submission->status,
                    'remarks' => $submission->return_remarks,
                    'reviewed_by' => $submission->reviewed_by,
                    'reviewed_at' => $submission->reviewed_at,
                ]);
            }

            $version = $submission->workbookExists() ? $submission->version + 1 : $submission->version;

            $path = $file->storeAs(
                'pds-working',
                $submission->user_id . '_' . $submission->applicable_year . '_v' . $version . '.xlsx',
                'local'
            );

            $submission->update([
                'file_path' => $path,
                'file_original_name' => $file->getClientOriginalName(),
                'pds_template_id' => $template->id,
                'version' => $version,
                'uploaded_at' => now(),
                'status' => 'draft',
                // A fresh upload clears the previous verdict.
                'return_remarks' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]);

            $this->log->log(
                'pds.uploaded',
                "{$submission->user->name} uploaded their {$submission->applicable_year} PDS (v{$version}).",
                $submission,
                ['version' => $version, 'template_version' => $template->version],
                $submission->user,
            );

            return $submission;
        });

        // Converting is deliberately outside the transaction above.
        //
        // A full PDS takes seconds to render, and holding a database
        // transaction open for that long is bad on its own. Worse, if the
        // request hits the host's execution limit mid-convert, the whole
        // transaction rolls back and an upload the employee watched succeed
        // disappears. The workbook is what matters; the PDF is a convenience
        // that the viewer will produce on demand if this does not finish.
        $this->convertQuietly($submission->refresh());

        return $submission;
    }

    /**
     * Renders the PDF without letting it break the upload.
     *
     * Failure here costs nothing: the download route converts on demand and
     * caches the result, so the first person to open the sheet pays instead.
     */
    private function convertQuietly(PdsSubmission $submission): void
    {
        // Shared hosting caps a request at thirty seconds by default, which a
        // four-page PDS can approach on a slow box.
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        try {
            $this->convert($submission);
        } catch (\Throwable $e) {
            Log::warning('PDS uploaded but not converted; it will render on first view.', [
                'submission' => $submission->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Converts the stored workbook to PDF. Failure is recorded but does not
     * abort the upload — the workbook is still on file, and HR can convert on
     * demand.
     */
    public function convert(PdsSubmission $submission): bool
    {
        if (! $submission->workbookExists()) {
            return false;
        }

        try {
            $pdf = $this->converter->convert($submission->workbookPath());

            // The renderer that produced it is part of the name, so a later
            // improvement to the renderer replaces this file rather than
            // leaving the employee looking at an older rendering for ever.
            $target = 'pds-pdf/' . $submission->user_id . '_' . $submission->applicable_year
                . '_v' . $submission->version
                . '-' . XlsxToPdfService::rendererStamp() . '.pdf';

            Storage::disk('local')->put($target, file_get_contents($pdf));

            $submission->update(['pdf_path' => $target, 'converted_at' => now()]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('PDS conversion failed.', [
                'submission' => $submission->id,
                'error' => $e->getMessage(),
            ]);

            $this->log->log(
                'pds.conversion_failed',
                "Could not convert {$submission->user->name}'s PDS to PDF.",
                $submission,
                ['error' => $e->getMessage()],
            );

            return false;
        }
    }

    /** Hands the submission to HR. */
    public function submit(PdsSubmission $submission): void
    {
        $submission->update(['status' => 'submitted', 'submitted_at' => now()]);

        $this->log->log(
            'pds.submitted',
            "{$submission->user->name} submitted their {$submission->applicable_year} PDS for review.",
            $submission,
            actor: $submission->user,
        );

        $hr = User::where('role', 'admin')->where('status', 'active')->get();

        if ($hr->isNotEmpty()) {
            Notifier::send($hr, new PdsStatusChanged(
                $submission,
                'A PDS is awaiting your review',
                "{$submission->user->name} submitted their {$submission->applicable_year} Personal Data Sheet.",
                'Review the PDS',
                route('admin.pds.show', $submission->user),
                'info',
            ));
        }
    }

    public function approve(PdsSubmission $submission, User $reviewer): void
    {
        $submission->update([
            'status' => 'approved',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'return_remarks' => null,
        ]);

        $this->log->log(
            'pds.approved',
            "{$reviewer->name} approved {$submission->user->name}'s {$submission->applicable_year} PDS.",
            $submission,
            ['version' => $submission->version],
            $reviewer,
        );

        Notifier::send($submission->user, new PdsStatusChanged(
            $submission,
            'Your PDS has been approved',
            "Your {$submission->applicable_year} Personal Data Sheet was approved by {$reviewer->name}.",
            'View my PDS',
            route('pds.editor'),
            'success',
        ));
    }

    public function returnForRevision(PdsSubmission $submission, User $reviewer, string $remarks): void
    {
        $submission->update([
            'status' => 'returned',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'return_remarks' => $remarks,
        ]);

        $this->log->log(
            'pds.returned',
            "{$reviewer->name} returned {$submission->user->name}'s {$submission->applicable_year} PDS.",
            $submission,
            ['remarks' => $remarks],
            $reviewer,
        );

        Notifier::send($submission->user, new PdsStatusChanged(
            $submission,
            'Your PDS was returned for correction',
            "{$reviewer->name} returned your PDS: \"{$remarks}\"",
            'Correct and re-upload',
            route('pds.editor'),
            'error',
        ));
    }
}
