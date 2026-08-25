<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * One employee's own copy of the master ledger workbook. HR edits the real
 * cells of this file, so the campus's formatting and merged cells survive.
 */
class EmployeeLedger extends Model
{
    protected $fillable = [
        'user_id', 'ledger_template_id', 'file_path',
        'template_version', 'last_edited_at', 'last_edited_by',
    ];

    protected $casts = ['last_edited_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function template()
    {
        return $this->belongsTo(LedgerTemplate::class, 'ledger_template_id');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'last_edited_by');
    }

    public function changes()
    {
        return $this->hasMany(LedgerChange::class)->latest();
    }

    public function exists(): bool
    {
        return Storage::disk('local')->exists($this->file_path);
    }

    public function absolutePath(): string
    {
        return Storage::disk('local')->path($this->file_path);
    }

    /** True when the master has moved on since this copy was taken. */
    public function isBehindMaster(): bool
    {
        $active = LedgerTemplate::active();

        return $active && $this->template_version !== null
            && $active->version > $this->template_version;
    }
}
