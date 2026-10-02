<?php

namespace App\Services\LeaveForm;

use App\Models\LeaveApplication;
use App\Models\LeaveFormTemplate;
use App\Models\User;
use App\Services\XlsxToPdfService;
use App\Support\DocumentName;
use Illuminate\Support\Facades\Storage;

/**
 * The printed CS Form No. 6 of an on-screen filing, as a workbook or a PDF.
 *
 * A filing's form changes as it moves along the chain, so it is printed when
 * it is read rather than once at filing. Printing is keyed to everything the
 * form shows: while nothing on it has changed, the same workbook is handed
 * out — and so the converter's cached PDF of it — instead of being rebuilt.
 */
class LeaveFormDocuments
{
    private const DIRECTORY = 'leave-generated';

    public function __construct(
        private LeaveFormGenerator $generator,
        private XlsxToPdfService $converter,
        private CsForm6Repairs $repairs,
    ) {
    }

    /** The filled workbook for a filing, printed if it is not current. */
    public function workbook(LeaveApplication $application): string
    {
        $content = LeaveFormPrintout::forApplication($application);
        $template = $this->generator->templatePath($application);

        $key = substr(hash('sha256', json_encode([$content, $template, @filemtime($template), CsForm6::class])), 0, 16);
        $relative = self::DIRECTORY . "/{$application->id}-{$key}.xlsx";
        $disk = Storage::disk('local');

        if (! $disk->exists($relative)) {
            // Earlier printings of this filing are out of date now.
            foreach ($disk->files(self::DIRECTORY) as $old) {
                if (str_starts_with(basename($old), $application->id . '-')) {
                    $disk->delete($old);
                }
            }

            $this->generator->generate($content, $disk->path($relative), $template);
        }

        return $disk->path($relative);
    }

    /** The filing's form as a PDF, shown in the browser. */
    public function pdfResponse(LeaveApplication $application, bool $embedded = false)
    {
        return $this->converter->stream(
            $this->workbook($application),
            $application->formPdfName(),
            cacheKey: 'leave-online:' . $application->id,
            allowIncompletePreview: $embedded,
        );
    }

    /** The filing's form as the Excel workbook. */
    public function workbookResponse(LeaveApplication $application)
    {
        return response()->download(
            $this->workbook($application),
            DocumentName::leaveForm($application->user, $application->reference(), 'xlsx'),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /** A filing not yet sent, printed so the employee can check it first. */
    public function draftPdfResponse(array $input, User $applicant)
    {
        $content = LeaveFormPrintout::forDraft($input, $applicant);
        $relative = self::DIRECTORY . '/draft-' . $applicant->id . '.xlsx';
        $path = Storage::disk('local')->path($relative);

        $this->generator->generate($content, $path, $this->generator->templatePath());

        // Never cached: a draft is looked at once and changed.
        $pdf = $this->converter->convert($path, true, false, 'leave-draft:' . $applicant->id);

        return response()->file($pdf, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => DocumentName::disposition(DocumentName::forPerson($applicant, 'Leave Form (preview)')),
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ])->deleteFileAfterSend(true);
    }

    /**
     * A published template with the form's printing faults corrected, kept
     * beside the original so HR's own file is never altered.
     */
    public function correctedTemplate(LeaveFormTemplate $template): string
    {
        $relative = 'leave-form-corrected/' . ($template->checksum ?: $template->id) . '.xlsx';
        $disk = Storage::disk('local');

        if (! $disk->exists($relative)) {
            $disk->put($relative, (string) file_get_contents($template->absolutePath()));

            if (strtolower(pathinfo($template->file_path, PATHINFO_EXTENSION)) === 'xlsx') {
                $this->repairs->repair($disk->path($relative));
            }
        }

        return $disk->path($relative);
    }
}
