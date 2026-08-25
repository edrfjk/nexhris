<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** One archived upload of a PDS, kept so a return/re-upload cycle is auditable. */
class PdsSubmissionRevision extends Model
{
    protected $fillable = [
        'pds_submission_id', 'version', 'pds_template_id',
        'file_path', 'pdf_path', 'file_original_name',
        'outcome', 'remarks', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function submission()
    {
        return $this->belongsTo(PdsSubmission::class, 'pds_submission_id');
    }

    public function template()
    {
        return $this->belongsTo(PdsTemplate::class, 'pds_template_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function pdfExists(): bool
    {
        return $this->pdf_path && Storage::disk('public')->exists($this->pdf_path);
    }
}
