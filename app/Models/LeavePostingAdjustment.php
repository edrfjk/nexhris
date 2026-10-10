<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * A posting that differed from the approved leave form: what was approved,
 * what HR posted, why, and who. An audit record, so it is append-only.
 */
class LeavePostingAdjustment extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'leave_application_id', 'leave_ledger_entry_id',
        'original_date_from', 'original_date_to', 'original_days', 'original_charges',
        'posted_date_from', 'posted_date_to', 'posted_days', 'posted_charges',
        'differences', 'reason', 'adjusted_by',
    ];

    protected $casts = [
        'original_date_from' => 'date',
        'original_date_to' => 'date',
        'posted_date_from' => 'date',
        'posted_date_to' => 'date',
        'original_days' => 'decimal:3',
        'posted_days' => 'decimal:3',
        'original_charges' => 'array',
        'posted_charges' => 'array',
        'differences' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $adjustment) {
            // The ledger line it explains may be removed later; the database
            // nulls that link, and that one change is allowed.
            if (array_keys($adjustment->getDirty()) !== ['leave_ledger_entry_id']) {
                throw new LogicException('A posting adjustment is an audit record and cannot be changed.');
            }
        });

        static::deleting(function () {
            throw new LogicException('A posting adjustment is an audit record and cannot be deleted.');
        });
    }

    public function application()
    {
        return $this->belongsTo(LeaveApplication::class, 'leave_application_id');
    }

    public function adjuster()
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }
}
