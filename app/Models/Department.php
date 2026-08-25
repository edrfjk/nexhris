<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A department, programme or office sitting under a College — BSIT under CAS,
 * the Registrar under Administrative Offices.
 *
 * Approval routing stays at college level: the Dean signs for the whole
 * college, so a department is an organisational and reporting unit rather
 * than a second approval boundary.
 */
class Department extends Model
{
    protected $fillable = [
        'college_id', 'code', 'name', 'description', 'head_id', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function college()
    {
        return $this->belongsTo(College::class);
    }

    public function employees()
    {
        return $this->hasMany(User::class);
    }

    /** The programme chair or office head, where the campus names one. */
    public function head()
    {
        return $this->belongsTo(User::class, 'head_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function label(): string
    {
        return "{$this->code} — {$this->name}";
    }

    /** Only an empty department may be removed outright. */
    public function isDeletable(): bool
    {
        return $this->employees()->count() === 0;
    }
}
