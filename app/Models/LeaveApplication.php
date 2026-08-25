<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class LeaveApplication extends Model
{
    protected $fillable = [
        'user_id', 'leave_type', 'date_from', 'date_to', 'days', 'reason',
        'status', 'reviewed_by', 'reviewed_at', 'remarks',
        'file_path', 'file_original_name', 'uploaded_at', 'ledger_posted',
        'leave_ledger_entry_id',
        'leave_form_template_id',
        'dean_id', 'dean_status', 'dean_reviewed_at', 'dean_remarks',
        'hr_id', 'hr_status', 'hr_reviewed_at', 'hr_remarks',
        'director_id', 'director_status', 'director_reviewed_at', 'director_remarks',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'reviewed_at' => 'datetime',
        'uploaded_at' => 'datetime',
        'dean_reviewed_at' => 'datetime',
        'hr_reviewed_at' => 'datetime',
        'director_reviewed_at' => 'datetime',
        'ledger_posted' => 'boolean',
        'days' => 'decimal:2',
    ];

    // ------------------------------------------------------------------
    // The uploaded form
    // ------------------------------------------------------------------

    /** True when the uploaded file is a spreadsheet a browser cannot show. */
    public function formNeedsConverting(): bool
    {
        return in_array($this->formExtension(), ['xlsx', 'xls'], true);
    }

    /** What the converted copy should be called when it is downloaded. */
    public function formPdfName(): string
    {
        $name = preg_replace('/[^A-Za-z0-9_]/', '_', $this->user?->name ?? 'Employee');

        return 'Leave_Form_' . $name . '_' . str_pad((string) $this->id, 5, '0', STR_PAD_LEFT) . '.pdf';
    }

    // ------------------------------------------------------------------
    // Credits
    // ------------------------------------------------------------------

    /** Credits the applicant currently holds for this form's leave type. */
    public function availableCredits(): float
    {
        $balance = $this->user?->leaveBalance;

        if (! $balance) {
            return 0.0;
        }

        return (float) ($this->leave_type === 'SL'
            ? $balance->sl_balance
            : $balance->vl_balance);
    }

    /**
     * Days this form asks for beyond what the applicant holds.
     *
     * Approving past this is allowed — the excess is recorded on the ledger
     * card as leave without pay — but the reviewer signing it should be told
     * rather than having to open the ledger and subtract by hand.
     */
    public function creditShortfall(): float
    {
        return max(0.0, round((float) $this->days - $this->availableCredits(), 2));
    }

    public function exceedsAvailableCredits(): bool
    {
        return $this->creditShortfall() > 0;
    }

    // ------------------------------------------------------------------
    // Waiting time
    // ------------------------------------------------------------------

    /**
     * When this form landed on the desk of whoever it is waiting for now —
     * the moment the previous stage signed, or the upload for the first one.
     *
     * A stage the chain skips (a Dean does not sign their own leave) leaves
     * its timestamp null, so this falls back through the earlier ones rather
     * than reporting a form as brand new.
     */
    public function waitingSince(): ?\Carbon\CarbonInterface
    {
        return match ($this->status) {
            'submitted' => $this->uploaded_at ?? $this->created_at,
            'dean_approved' => $this->dean_reviewed_at ?? $this->uploaded_at ?? $this->created_at,
            'hr_approved' => $this->hr_reviewed_at ?? $this->dean_reviewed_at ?? $this->uploaded_at ?? $this->created_at,
            default => null,
        };
    }

    /** Whole days this form has been waiting on its current reviewer. */
    public function daysWaiting(): ?int
    {
        return $this->waitingSince()?->diffInDays(now());
    }

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function dean()
    {
        return $this->belongsTo(User::class, 'dean_id');
    }

    public function hrReviewer()
    {
        return $this->belongsTo(User::class, 'hr_id');
    }

    public function director()
    {
        return $this->belongsTo(User::class, 'director_id');
    }

    public function approvals()
    {
        return $this->hasMany(LeaveApproval::class)->orderBy('created_at');
    }

    public function ledgerEntry()
    {
        return $this->belongsTo(LeaveLedgerEntry::class, 'leave_ledger_entry_id');
    }

    /** The blank template version this form was filled on. */
    public function formTemplate()
    {
        return $this->belongsTo(LeaveFormTemplate::class, 'leave_form_template_id');
    }

    // ------------------------------------------------------------------
    // Workflow state
    // ------------------------------------------------------------------

    /** The stage currently expected to act, derived from the applicant's role. */
    public function currentStage(): ?string
    {
        return app(\App\Services\LeaveChain::class)->currentStage($this);
    }

    public function awaiting(string $stage): bool
    {
        return $this->currentStage() === $stage;
    }

    /** Stages that do not apply to this applicant, shown as N/A. */
    public function skippedStages(): array
    {
        return app(\App\Services\LeaveChain::class)->skippedFor($this->user);
    }

    /** Per-stage rows for the employee-facing stepper. */
    public function timeline(): array
    {
        return app(\App\Services\LeaveChain::class)->timeline($this);
    }

    /** Fully approved by all three reviewers — the employee may print. */
    public function isFullyApproved(): bool
    {
        return in_array($this->status, ['cd_approved', 'completed'], true);
    }

    /** Sent back to the employee for correction and re-upload. */
    public function isReturned(): bool
    {
        return str_ends_with($this->status, '_returned');
    }

    /** HR still owes this form a ledger posting. */
    public function awaitsLedgerPosting(): bool
    {
        return $this->status === 'cd_approved' && ! $this->ledger_posted;
    }

    public function currentStageLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Not yet submitted',
            'submitted' => 'Awaiting Dean review',
            'dean_approved' => 'Awaiting HR review',
            'dean_returned' => 'Returned by the Dean',
            'hr_approved' => 'Awaiting Campus Director review',
            'hr_returned' => 'Returned by HR',
            'cd_approved' => 'Approved — ready to print',
            'cd_returned' => 'Returned by the Campus Director',
            'completed' => 'Completed — posted to ledger',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }


    /** How many applicable stages have approved so far. */
    public function completedSteps(): int
    {
        return count(array_filter(
            $this->timeline(),
            fn (array $row) => $row['state'] === 'approved',
        ));
    }

    // ------------------------------------------------------------------
    // Uploaded form file
    // ------------------------------------------------------------------

    public function employeeFormUrl(): ?string
    {
        return $this->file_path ? Storage::disk('public')->url($this->file_path) : null;
    }

    public function formExtension(): ?string
    {
        return $this->file_path ? strtolower(pathinfo($this->file_path, PATHINFO_EXTENSION)) : null;
    }
}
