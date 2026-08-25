<?php

namespace App\Models;

use App\Models\Concerns\IsVersionedTemplate;
use Illuminate\Database\Eloquent\Model;

/**
 * The master leave ledger workbook. Seeded once and never handed to employees
 * — it is the source each employee's own ledger is copied from.
 */
class LedgerTemplate extends Model
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

    public function ledgers()
    {
        return $this->hasMany(EmployeeLedger::class);
    }
}
