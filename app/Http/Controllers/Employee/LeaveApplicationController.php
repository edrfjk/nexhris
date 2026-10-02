<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\LeaveApplication;
use App\Models\LeaveFormTemplate;
use App\Services\LeaveWorkflowService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\XlsxToPdfService;
use App\Services\LeaveForm\LeaveFormDocuments;
use App\Services\LeaveForm\LeaveFormPrintout;
use App\Services\LeaveForm\LeaveFormTemplateMismatch;
use App\Services\LeaveForm\LeavePolicy;
use App\Support\DocumentName;
use App\Support\Leave\LeaveTypes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeaveApplicationController extends Controller
{
    /**
     * What a filled leave form may be uploaded as. Word files used to be
     * accepted too, but nothing in the system can show one to a reviewer:
     * the preview came up empty and the download was mislabelled .xlsx.
     */
    private const FORM_FILE_RULES = ['required', 'file', 'mimes:xlsx,xls,pdf', 'max:10240'];

    private const FORM_FILE_MESSAGE = 'Upload the filled-in form as the Excel workbook (.xlsx or .xls) or as a PDF.';

    public function index(Request $request)
    {
        $user = Auth::user();

        $applications = $user->leaveApplications()
            ->with('approvals.approver')
            // "returned" covers whichever stage sent the form back.
            ->when($request->status, fn ($q, $status) => $status === 'returned'
                ? $q->whereIn('status', ['dean_returned', 'hr_returned', 'cd_returned'])
                : $q->where('status', $status))
            ->when($request->type, fn ($q, $type) => $q->where('leave_type', $type))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $balance = $user->leaveBalance;
        $template = LeaveFormTemplate::active();

        $inReview = $user->leaveApplications()
            ->whereIn('status', ['submitted', 'dean_approved', 'hr_approved'])
            ->count();

        $needsAttention = $user->leaveApplications()
            ->whereIn('status', ['dean_returned', 'hr_returned', 'cd_returned'])
            ->count();

        $readyToPrint = $user->leaveApplications()
            ->where('status', 'cd_approved')
            ->count();

        $approvedThisYear = $user->leaveApplications()
            ->whereIn('status', ['cd_approved', 'completed'])
            ->whereYear('date_from', now()->year)
            ->sum('days');

        $ledger = $user->leaveLedgerEntries()->latest('period_from')->take(6)->get()->reverse();

        return view('employee.leave.index', compact(
            'applications', 'balance', 'template', 'inReview', 'needsAttention',
            'readyToPrint', 'approvedThisYear', 'ledger'
        ));
    }

    /**
     * Downloads the blank leave form HR published. Falls back to the bundled
     * copy so employees are never blocked when HR has not uploaded one yet.
     */
    public function downloadTemplate(LeaveFormDocuments $documents)
    {
        $template = LeaveFormTemplate::active();

        if ($template && Storage::disk('public')->exists($template->file_path)) {
            // Named for what it is rather than whatever HR called the upload,
            // so the employee can tell it apart from the copy they fill in.
            // The copy handed out has the form's printing faults corrected;
            // HR's own file stays exactly as they uploaded it.
            return response()->download(
                $documents->correctedTemplate($template),
                DocumentName::template('Leave Form Template', $template->version, $template->extension() ?: 'xlsx'),
            );
        }

        $fallback = resource_path('templates/CS-Form-6-2020.xlsx');
        abort_unless(is_file($fallback), 404, 'No leave form template has been published yet.');

        return response()->download($fallback, 'Official-Leave-Form.xlsx');
    }

    // ------------------------------------------------------------------
    // Filing on screen
    // ------------------------------------------------------------------

    /** CS Form No. 6, filled in on screen. */
    public function create(Request $request)
    {
        return $this->formView($request, null);
    }

    /** A returned on-screen form, opened again for correcting. */
    public function edit(Request $request, LeaveApplication $application)
    {
        $this->authorizeCorrection($request, $application);

        return $this->formView($request, $application);
    }

    /**
     * The policy check the form runs as the employee types — the same check
     * the filing gets when it is submitted.
     */
    public function check(Request $request, LeavePolicy $policy)
    {
        $input = $this->formInput($request);
        $input['has_attachments'] = $request->boolean('has_attachments');

        return response()->json($policy->check($input, $request->user(), $this->ownApplicationId($request)));
    }

    /** The form as it would print, before it is sent. */
    public function preview(Request $request, LeavePolicy $policy, LeaveFormDocuments $documents)
    {
        $request->validate($this->formRules(forPreview: true), $this->formMessages());

        $input = $this->formInput($request);
        $result = $policy->check($input, $request->user(), $this->ownApplicationId($request));

        if (! LeaveTypes::get($input['leave_type'])['days_only']) {
            $input['days'] = $result['days'];
        }

        try {
            return $documents->draftPdfResponse($input, $request->user());
        } catch (LeaveFormTemplateMismatch $e) {
            abort(422, $e->getMessage());
        }
    }

    /** Files the on-screen form and sends it to the first reviewer. */
    public function storeOnline(Request $request, LeavePolicy $policy, LeaveWorkflowService $workflow)
    {
        $user = $request->user();
        $request->validate($this->formRules(), $this->formMessages());

        $input = $this->formInput($request);
        $result = $policy->check($input + ['has_attachments' => $request->hasFile('attachments')], $user);

        if ($result['errors']) {
            throw ValidationException::withMessages($result['errors']);
        }

        $application = LeaveApplication::create($this->recordFor($input, $result, $user) + [
            'user_id' => $user->id,
            'leave_form_template_id' => LeaveFormTemplate::active()?->id,
            'filing_method' => LeaveApplication::ONLINE,
            // The right first stage straight away, as an upload gets.
            'status' => $workflow->chain()->initialStatus($user),
            'uploaded_at' => now(),
        ]);

        $this->storeAttachments($request, $application);

        try {
            $workflow->submit($application);
            $nextStage = $application->fresh()->currentStage();
            $nextLabel = $nextStage ? \App\Services\LeaveChain::LABELS[$nextStage] : 'review';
        } catch (\Throwable $e) {
            report($e);
            Log::error('Leave form saved, but its follow-up routing failed.', [
                'leave_application_id' => $application->id,
                'user_id' => $application->user_id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('leave.index')
                ->with('success', 'Your leave form was saved successfully.')
                ->with('warning', 'The reviewer notification could not be completed right now. Do not submit the form again; HR can still find the saved form in the leave queue.');
        }

        $redirect = redirect()->route('leave.index')->with('success', sprintf(
            'Leave form submitted (%s). It is now with the %s.',
            $this->daysLabel($application),
            $nextLabel,
        ));

        return $result['warnings']
            ? $redirect->with('warning', implode(' ', $result['warnings']))
            : $redirect;
    }

    /** Saves the corrections to a returned on-screen form and restarts the chain. */
    public function update(Request $request, LeaveApplication $application, LeavePolicy $policy, LeaveWorkflowService $workflow)
    {
        $this->authorizeCorrection($request, $application);
        $user = $request->user();

        $request->validate($this->formRules(), $this->formMessages());

        $input = $this->formInput($request);
        $removing = array_map('intval', (array) $request->input('remove_attachments', []));
        $keeping = [];
        $dropping = [];

        foreach ($application->attachments() as $i => $file) {
            in_array($i, $removing, true) ? $dropping[] = $file : $keeping[] = $file;
        }

        $result = $policy->check(
            $input + ['has_attachments' => $keeping || $request->hasFile('attachments')],
            $user,
            $application->id,
        );

        if ($result['errors']) {
            throw ValidationException::withMessages($result['errors']);
        }

        // Documents the employee took off the form go with it.
        foreach ($dropping as $file) {
            Storage::disk('local')->delete($file['path']);
        }

        $record = $this->recordFor($input, $result, $user);
        $record['form_data']['attachments'] = $keeping;

        $application->update($record + [
            'leave_form_template_id' => LeaveFormTemplate::active()?->id ?? $application->leave_form_template_id,
            'uploaded_at' => now(),
        ]);

        $this->storeAttachments($request, $application);
        $workflow->resetForResubmission($application);

        $nextStage = $application->fresh()->currentStage();
        $nextLabel = $nextStage ? \App\Services\LeaveChain::LABELS[$nextStage] : 'reviewer';

        $redirect = redirect()->route('leave.index')
            ->with('success', "Corrected form resubmitted. It is now with the {$nextLabel} for review.");

        return $result['warnings']
            ? $redirect->with('warning', implode(' ', $result['warnings']))
            : $redirect;
    }

    /** A supporting document the employee attached to their own filing. */
    public function attachment(Request $request, LeaveApplication $application, int $index)
    {
        abort_unless($application->user_id === $request->user()->id, 403, 'This is not your leave form.');

        return self::streamAttachment($application, $index);
    }

    /** Shown inline: these are read on the review page, not filed away. */
    public static function streamAttachment(LeaveApplication $application, int $index)
    {
        $file = $application->attachments()[$index] ?? null;

        abort_unless($file && Storage::disk('local')->exists($file['path']), 404, 'That document is no longer available.');

        return Storage::disk('local')->response($file['path'], $file['name'], [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function formView(Request $request, ?LeaveApplication $application)
    {
        $user = $request->user();
        $chain = app(\App\Services\LeaveChain::class);

        return view('employee.leave.apply', [
            'application' => $application,
            'values' => $application
                ? array_merge($application->form_data ?? [], [
                    'leave_type' => $application->leave_type,
                    'date_from' => $application->isDaysOnly() ? null : $application->date_from?->toDateString(),
                    'date_to' => $application->isDaysOnly() ? null : $application->date_to?->toDateString(),
                    'days' => $application->isDaysOnly() ? LeavePolicy::number((float) $application->days) : null,
                    'reason' => $application->reason,
                ])
                : ['commutation' => 'not_requested'],
            'applicant' => LeaveFormPrintout::applicant($user),
            'balance' => $user->leaveBalance,
            'chainLabels' => array_map(fn ($stage) => \App\Services\LeaveChain::LABELS[$stage], $chain->stagesFor($user)),
            'dean' => $chain->stageApplies($user, 'dean') ? LeaveFormPrintout::signatory($user->college?->dean) : null,
        ]);
    }

    private function authorizeCorrection(Request $request, LeaveApplication $application): void
    {
        abort_unless($application->user_id === $request->user()->id, 403, 'This is not your leave form.');
        abort_unless($application->isOnline(), 422, 'This form was uploaded. Upload a corrected copy instead.');
        abort_unless($application->isReturned(), 422, 'Only a form returned to you can be changed.');
    }

    /** The id of the filing being corrected, provided it is the requester's own. */
    private function ownApplicationId(Request $request): ?int
    {
        $id = $request->integer('application') ?: null;

        return $id && LeaveApplication::whereKey($id)->where('user_id', $request->user()->id)->exists() ? $id : null;
    }

    private function formRules(bool $forPreview = false): array
    {
        return [
            'leave_type' => ['required', Rule::in(array_keys(LeaveApplication::TYPES))],
            'others_specify' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'days' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'details' => ['nullable', 'array'],
            'details.location' => ['nullable', Rule::in(array_keys(LeaveTypes::LOCATIONS))],
            'details.location_specify' => ['nullable', 'string', 'max:100'],
            'details.sickness' => ['nullable', Rule::in(array_keys(LeaveTypes::SICKNESS))],
            'details.illness' => ['nullable', 'string', 'max:200'],
            'details.women_illness' => ['nullable', 'string', 'max:200'],
            'details.study' => ['nullable', Rule::in(array_keys(LeaveTypes::STUDY))],
            'details.calamity_date' => ['nullable', 'date'],
            'commutation' => [$forPreview ? 'nullable' : 'required', Rule::in(array_keys(LeaveTypes::COMMUTATION))],
            'salary' => ['nullable', 'string', 'max:40'],
            'reason' => ['nullable', 'string', 'max:500'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'remove_attachments' => ['nullable', 'array'],
        ];
    }

    private function formMessages(): array
    {
        return [
            'leave_type.required' => 'Choose the type of leave.',
            'leave_type.in' => 'Choose one of the types of leave on the form.',
            'commutation.required' => 'Say whether commutation is requested.',
            'attachments.max' => 'Attach up to five documents.',
            'attachments.*.mimes' => 'Attach documents as PDF, JPG or PNG files.',
            'attachments.*.max' => 'Each document may be up to 10 MB.',
        ];
    }

    /**
     * The form as posted, trimmed to what applies to the chosen type: a
     * country left over from switching vacation leave to sick leave must
     * not print on the form.
     */
    private function formInput(Request $request): array
    {
        $code = (string) $request->input('leave_type');
        $type = LeaveTypes::has($code) ? LeaveTypes::get($code) : null;
        $posted = (array) $request->input('details', []);
        $text = fn ($value) => is_string($value) ? trim(preg_replace('/\s+/', ' ', $value)) : null;

        $details = match ($type['detail'] ?? null) {
            LeaveTypes::DETAIL_LOCATION => [
                'location' => $posted['location'] ?? null,
                'location_specify' => $text($posted['location_specify'] ?? null),
            ],
            LeaveTypes::DETAIL_SICKNESS => [
                'sickness' => $posted['sickness'] ?? null,
                'illness' => $text($posted['illness'] ?? null),
            ],
            LeaveTypes::DETAIL_WOMEN => ['women_illness' => $text($posted['women_illness'] ?? null)],
            LeaveTypes::DETAIL_STUDY => ['study' => $posted['study'] ?? null],
            default => [],
        };

        if ($code === 'SEL') {
            $details['calamity_date'] = $posted['calamity_date'] ?? null;
        }

        $daysOnly = (bool) ($type['days_only'] ?? false);

        return [
            'leave_type' => $code,
            'others_specify' => $code === 'OTHERS' ? $text($request->input('others_specify')) : null,
            'date_from' => $daysOnly ? null : $request->input('date_from'),
            'date_to' => $daysOnly ? null : $request->input('date_to'),
            'days' => $daysOnly ? (float) $request->input('days') : null,
            'details' => array_filter($details, fn ($v) => filled($v)),
            'commutation' => $request->input('commutation'),
            'salary' => $text($request->input('salary')),
            'reason' => $text($request->input('reason')),
        ];
    }

    /** The columns and form data a checked filing is saved as. */
    private function recordFor(array $input, array $result, $user): array
    {
        $daysOnly = LeaveTypes::get($input['leave_type'])['days_only'];
        $today = now()->toDateString();

        return [
            'leave_type' => $input['leave_type'],
            // Monetization and terminal leave have no inclusive dates; the
            // filing date stands in so the record still sorts and filters.
            'date_from' => $daysOnly ? $today : $input['date_from'],
            'date_to' => $daysOnly ? $today : $input['date_to'],
            'days' => $daysOnly ? $input['days'] : $result['days'],
            'reason' => $input['reason'],
            'form_data' => [
                // Items 1, 2 and 4 as the employee's record stood on filing.
                'applicant' => LeaveFormPrintout::applicant($user),
                'date_filed' => $today,
                'others_specify' => $input['others_specify'],
                'details' => $input['details'],
                'commutation' => $input['commutation'],
                'salary' => $input['salary'],
                'attachments' => [],
            ],
        ];
    }

    private function storeAttachments(Request $request, LeaveApplication $application): void
    {
        if (! $request->hasFile('attachments')) {
            return;
        }

        $data = $application->form_data ?? [];
        $files = $data['attachments'] ?? [];

        foreach ((array) $request->file('attachments') as $file) {
            if (count($files) >= 5) {
                break;
            }

            $files[] = [
                // The private disk: these carry medical and legal details.
                'path' => $file->store("leave-attachments/{$application->id}", 'local'),
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
            ];
        }

        $data['attachments'] = $files;
        $application->update(['form_data' => $data]);
    }

    private function daysLabel(LeaveApplication $application): string
    {
        $unit = $application->isDaysOnly() || LeaveTypes::get($application->leave_type)['calendar'] ? 'day(s)' : 'working day(s)';

        return LeavePolicy::number((float) $application->days) . ' ' . $unit;
    }

    /**
     * Uploads a filled-in leave form. This is the only way a form enters the
     * approval chain — it goes straight to the employee's Dean.
     */
    public function store(Request $request, LeaveWorkflowService $workflow)
    {
        $data = $request->validate([
            'leave_type' => ['required', \Illuminate\Validation\Rule::in(array_keys(LeaveApplication::TYPES))],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'reason' => ['nullable', 'string', 'max:500'],
            'leave_form' => self::FORM_FILE_RULES,
        ], [
            'leave_form.required' => 'Attach the filled-in leave form before submitting.',
            'leave_form.mimes' => self::FORM_FILE_MESSAGE,
        ]);

        // The same days filed twice — a double submit, or a new form instead
        // of re-uploading a returned one — would put two forms through the
        // chain and, once both are posted, charge the ledger twice.
        $overlapping = $request->user()->leaveApplications()
            ->whereNotIn('status', ['draft', 'dean_returned', 'hr_returned', 'cd_returned'])
            // Monetization and terminal leave hold days, not dates.
            ->whereNotIn('leave_type', ['MONETIZE', 'TERMINAL'])
            ->whereDate('date_from', '<=', $data['date_to'])
            ->whereDate('date_to', '>=', $data['date_from'])
            ->first();

        if ($overlapping) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'date_from' => sprintf(
                    'You already filed leave covering %s to %s. Choose other dates, or wait for that form to be returned before filing it again.',
                    $overlapping->date_from->format('M j, Y'),
                    $overlapping->date_to->format('M j, Y'),
                ),
            ]);
        }

        $file = $request->file('leave_form');

        $days = \Carbon\Carbon::parse($data['date_from'])
            ->diffInWeekdays(\Carbon\Carbon::parse($data['date_to'])->addDay());

        // Warn when the ledger cannot cover the request. This is advisory, not
        // a block: HR may still approve leave without pay, and the ledger is
        // reconciled by hand after the Campus Director signs off.
        $balance = Auth::user()->leaveBalance;
        $charge = LeaveTypes::charge($data['leave_type']);
        $available = (float) match ($charge) {
            'vl' => $balance->vl_balance ?? 0,
            'sl' => $balance->sl_balance ?? 0,
            'service' => $balance->service_balance ?? 0,
            default => 0,
        };

        $shortfall = $charge !== null && $days > $available
            ? round($days - $available, 2) : 0;

        // Stamp the template version in force at submission, so the form can
        // still be read against the exact blank the employee downloaded.
        $template = LeaveFormTemplate::active();

        $application = LeaveApplication::create([
            'user_id' => Auth::id(),
            'leave_form_template_id' => $template?->id,
            'leave_type' => $data['leave_type'],
            'date_from' => $data['date_from'],
            'date_to' => $data['date_to'],
            'reason' => $data['reason'] ?? null,
            'days' => $days,
            // Save the correct first stage immediately. If later audit or
            // notification work fails, a Dean's form must not remain in the
            // Dean queue by mistake.
            'status' => $workflow->chain()->initialStatus($request->user()),
            'file_path' => $file->store('leave-applications', 'local'),
            'file_original_name' => $file->getClientOriginalName(),
            'uploaded_at' => now(),
            'filing_method' => LeaveApplication::UPLOAD,
        ]);

        // The file and leave record now exist. Routing, audit logging and
        // reviewer notifications are important, but an outage in one of
        // those follow-up services must never turn a successful submission
        // into a misleading 500 page that invites the employee to submit the
        // same form again.
        try {
            $workflow->submit($application);
            $nextStage = $application->fresh()->currentStage();
            $nextLabel = $nextStage ? \App\Services\LeaveChain::LABELS[$nextStage] : 'review';
        } catch (\Throwable $e) {
            report($e);
            Log::error('Leave form saved, but its follow-up routing failed.', [
                'leave_application_id' => $application->id,
                'user_id' => $application->user_id,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->with('success', 'Your leave form was saved successfully.')
                ->with('warning', 'The reviewer notification could not be completed right now. Do not submit the form again; HR can still find the saved form in the leave queue.');
        }

        $message = "Leave form submitted ({$days} working day(s)). It is now with the {$nextLabel}.";

        if ($shortfall > 0) {
            return back()
                ->with('success', $message)
                ->with('warning', sprintf(
                    'Heads up: you requested %s day(s) but only have %s %s day(s) available — a shortfall of %s. '
                    . 'HR will confirm whether the excess is charged without pay.',
                    rtrim(rtrim(number_format($days, 2), '0'), '.'),
                    rtrim(rtrim(number_format($available, 2), '0'), '.'),
                    LeaveApplication::TYPES[$data['leave_type']],
                    rtrim(rtrim(number_format($shortfall, 2), '0'), '.'),
                ));
        }

        return back()->with('success', $message);
    }

    /**
     * Replaces the file on a returned form and restarts the chain.
     */
    public function resubmit(Request $request, LeaveApplication $application, LeaveWorkflowService $workflow)
    {
        abort_unless($application->user_id === Auth::id(), 403);
        abort_unless($application->isReturned(), 422,
            'Only a returned form can be re-submitted.');
        abort_if($application->isOnline(), 422,
            'This form was filled in on screen. Open it and correct it there.');

        $request->validate([
            'leave_form' => self::FORM_FILE_RULES,
        ], [
            'leave_form.mimes' => self::FORM_FILE_MESSAGE,
        ]);

        $file = $request->file('leave_form');

        if ($application->file_path && Storage::disk('local')->exists($application->file_path)) {
            Storage::disk('local')->delete($application->file_path);
        }

        $application->update([
            'file_path' => $file->store('leave-applications', 'local'),
            'file_original_name' => $file->getClientOriginalName(),
            'uploaded_at' => now(),
            // A corrected form may have been filled on a newer blank.
            'leave_form_template_id' => LeaveFormTemplate::active()?->id
                ?? $application->leave_form_template_id,
        ]);

        $workflow->resetForResubmission($application);

        $nextStage = $application->fresh()->currentStage();
        $nextLabel = $nextStage ? \App\Services\LeaveChain::LABELS[$nextStage] : 'reviewer';

        return back()->with('success', "Corrected form uploaded. It is now with the {$nextLabel} for review.");
    }

    /**
     * The printable approval sheet. Only unlocked once the Campus Director has
     * signed off — the whole point of the online chain is that nobody prints a
     * form that was going to be rejected anyway.
     */
    public function printApproved(LeaveApplication $application, LeaveFormDocuments $documents)
    {
        abort_unless($application->user_id === Auth::id(), 403);
        abort_unless($application->isFullyApproved(), 403,
            'This form is not fully approved yet, so it cannot be printed.');

        // An on-screen filing prints as the completed CS Form No. 6 itself.
        if ($application->isOnline()) {
            return self::printedForm($application, $documents);
        }

        return $this->renderApprovalSheet($application);
    }

    /** The printed form of an on-screen filing, or why it could not be printed. */
    public static function printedForm(LeaveApplication $application, LeaveFormDocuments $documents, bool $embedded = false)
    {
        try {
            return $documents->pdfResponse($application, $embedded);
        } catch (LeaveFormTemplateMismatch $e) {
            abort(422, $e->getMessage());
        }
    }

    public function exportLedgerPdf()
    {
        $employee = Auth::user();

        // Named after the employee, not "My …" — the file leaves the browser
        // and lands in a folder where "who is this" has to be obvious.
        return $this->renderLedgerCard($employee, DocumentName::ledgerCard($employee));
    }

    /**
     * The employee's own uploaded workbook.
     *
     * These used to be linked straight at /storage, which put every filed
     * leave form — medical grounds and all — on the open web. They live on the
     * private disk now, so reaching one goes through here and past this check.
     */
    public function downloadForm(LeaveApplication $application, LeaveFormDocuments $documents)
    {
        abort_unless($application->user_id === Auth::id(), 403, 'This is not your leave form.');

        // An on-screen filing's workbook is the form as the system prints it.
        if ($application->isOnline()) {
            try {
                return $documents->workbookResponse($application);
            } catch (LeaveFormTemplateMismatch $e) {
                abort(422, $e->getMessage());
            }
        }

        abort_unless(
            $application->file_path && Storage::disk('local')->exists($application->file_path),
            404,
            'You have not uploaded a form for this application.'
        );

        // Named with the file's real type: a PDF saved as ".xlsx" will not open.
        return Storage::disk('local')->download(
            $application->file_path,
            DocumentName::leaveForm($application->user, $application->reference(), $application->formExtension() ?: 'xlsx'),
        );
    }

    /**
     * The employee's own uploaded form, converted.
     *
     * Whoever filled the workbook should be able to see the PDF the reviewers
     * will be reading, and keep a copy of it.
     */
    public function exportFormPdf(LeaveApplication $application, XlsxToPdfService $converter, LeaveFormDocuments $documents)
    {
        abort_unless($application->user_id === Auth::id(), 403,
            'This is not your leave form.');

        if ($application->isOnline()) {
            return self::printedForm($application, $documents);
        }

        abort_unless(
            $application->file_path && Storage::disk('local')->exists($application->file_path),
            404,
            'You have not uploaded a form for this application.'
        );

        return self::streamFormAsPdf($application, $converter);
    }

    /**
     * The uploaded form as a PDF for reading in the browser. A PDF upload is
     * already that, so it is sent as it is; only a workbook is converted.
     * Sending a PDF through the workbook converter failed and handed the
     * reader their own PDF back labelled as a spreadsheet.
     */
    public static function streamFormAsPdf(
        LeaveApplication $application,
        XlsxToPdfService $converter,
        bool $allowIncompletePreview = false,
    ) {
        $path = Storage::disk('local')->path($application->file_path);

        if ($application->formExtension() === 'pdf') {
            return response()->file($path, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => DocumentName::disposition($application->formPdfName()),
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return $converter->stream(
            $path,
            $application->formPdfName(),
            cacheKey: 'leave-form:' . $application->id,
            allowIncompletePreview: $allowIncompletePreview,
        );
    }


    // ------------------------------------------------------------------
    // Shared renderers — the admin side prints the identical documents.
    // ------------------------------------------------------------------

    public static function renderLedgerCard($employee, string $filename)
    {
        $ledger = $employee->leaveLedgerEntries()->orderBy('period_from')->get();

        $pdf = Pdf::loadView('pdf.leave-ledger-card', [
            'employee' => $employee,
            'ledger' => $ledger,
            'balance' => $employee->leaveBalance,
            'serviceRows' => $ledger->filter->touchesServiceCredits()->values(),
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream($filename);
    }

    public static function renderApprovalSheet(LeaveApplication $application)
    {
        $application->loadMissing(['user', 'dean', 'hrReviewer', 'director', 'approvals.approver']);

        $pdf = Pdf::loadView('pdf.leave-approval-sheet', [
            'application' => $application,
            'employee' => $application->user,
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream($application->approvalSheetName());
    }
}
