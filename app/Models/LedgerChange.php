<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One cell edit on an employee's ledger: field, old value, new value, who. */
class LedgerChange extends Model
{
    protected $fillable = [
        'employee_ledger_id', 'cell', 'sheet', 'old_value', 'new_value', 'changed_by',
    ];

    public function ledger()
    {
        return $this->belongsTo(EmployeeLedger::class, 'employee_ledger_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function summary(): string
    {
        $from = $this->old_value === null || $this->old_value === '' ? '(blank)' : $this->old_value;
        $to = $this->new_value === null || $this->new_value === '' ? '(blank)' : $this->new_value;

        return "{$this->cell}: {$from} → {$to}";
    }
}
