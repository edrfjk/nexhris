<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PdsSubmission extends Model
{
    protected $fillable = [
        'user_id',
        'applicable_year',
        'status',
        'pds_template_id',
        'file_path',
        'pdf_path',
        'file_original_name',
        'version',
        'uploaded_at',
        'converted_at',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'return_remarks',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'converted_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** The template version this submission was filled on. */
    public function template()
    {
        return $this->belongsTo(PdsTemplate::class, 'pds_template_id');
    }

    public function revisions()
    {
        return $this->hasMany(PdsSubmissionRevision::class)->orderByDesc('version');
    }

    // ------------------------------------------------------------------
    // State
    // ------------------------------------------------------------------

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isReturned(): bool
    {
        return $this->status === 'returned';
    }

    /** The employee may still change the file. */
    public function isEditable(): bool
    {
        return in_array($this->status, ['not_started', 'draft', 'returned'], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'not_started' => 'Not started',
            'draft' => 'Draft — not yet submitted',
            'submitted' => 'Submitted — awaiting HR review',
            'returned' => 'Returned for correction',
            'approved' => 'Approved',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            'approved' => 'green',
            'returned' => 'red',
            'submitted' => 'amber',
            'draft' => 'blue',
            default => 'slate',
        };
    }

    // ------------------------------------------------------------------
    // Files
    // ------------------------------------------------------------------

    public function workbookExists(): bool
    {
        return $this->file_path && Storage::disk('public')->exists($this->file_path);
    }

    public function pdfExists(): bool
    {
        return $this->pdf_path && Storage::disk('public')->exists($this->pdf_path);
    }

    public function workbookPath(): ?string
    {
        return $this->workbookExists() ? Storage::disk('public')->path($this->file_path) : null;
    }

    public function pdfPath(): ?string
    {
        return $this->pdfExists() ? Storage::disk('public')->path($this->pdf_path) : null;
    }
}
