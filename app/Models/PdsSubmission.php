<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdsSubmission extends Model
{
    protected $fillable = [
        'user_id',
        'applicable_year',
        'status',
        'pds_template_id',
        'file_path',
        'file_original_name',
        'uploaded_at',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'return_remarks',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
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

    public function template()
    {
        return $this->belongsTo(PdsTemplate::class, 'pds_template_id');
    }
}