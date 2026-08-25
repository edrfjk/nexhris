<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One row per meaningful action. Append-only by intent: nothing in the app
 * updates or deletes these, so the trail stays trustworthy.
 */
class ActivityLog extends Model
{
    protected $fillable = [
        'user_id', 'action', 'description',
        'subject_type', 'subject_id',
        'ip_address', 'user_agent', 'properties',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** Badge tone for the action, so the log reads at a glance. */
    public function tone(): string
    {
        return match (true) {
            str_contains($this->action, 'failed'),
            str_contains($this->action, 'locked'),
            str_contains($this->action, 'deleted') => 'red',

            str_contains($this->action, 'approved'),
            str_contains($this->action, 'login') && ! str_contains($this->action, 'failed') => 'green',

            str_contains($this->action, 'returned'),
            str_contains($this->action, 'logout') => 'amber',

            str_contains($this->action, 'created'),
            str_contains($this->action, 'updated') => 'blue',

            default => 'slate',
        };
    }

    /** "leave.approved" -> "Leave approved" */
    public function actionLabel(): string
    {
        return ucfirst(str_replace(['.', '_'], ' ', $this->action));
    }

    /** A short, human name for the record this action touched. */
    public function subjectLabel(): ?string
    {
        if (! $this->subject_type) {
            return null;
        }

        return class_basename($this->subject_type)
            . ($this->subject_id ? " #{$this->subject_id}" : '');
    }
}
