<?php

namespace App\Models;

use App\Models\Concerns\IsVersionedTemplate;
use Illuminate\Database\Eloquent\Model;

/** The blank leave application form employees download and fill offline. */
class LeaveFormTemplate extends Model
{
    use IsVersionedTemplate;

    protected $fillable = [
        'label', 'version', 'file_path', 'original_filename',
        'checksum', 'is_active', 'superseded_at', 'notes', 'uploaded_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'superseded_at' => 'datetime',
    ];

    public function applications()
    {
        return $this->hasMany(LeaveApplication::class);
    }
}
