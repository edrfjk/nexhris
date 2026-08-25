<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One line of an employee's CSC service record — a single appointment or
 * change in designation, salary or station. Distinct from *service credits*,
 * which are leave credits earned outside regular accrual and are tracked in
 * the ledger's service columns.
 */
class ServiceRecord extends Model
{
    protected $fillable = [
        'user_id', 'date_from', 'date_to', 'record_type', 'description',
        'status', 'designation', 'station', 'branch', 'salary',
        'lwop_days', 'separation_date', 'separation_cause', 'encoded_by',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'separation_date' => 'date',
        'salary' => 'decimal:2',
        'lwop_days' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function encoder()
    {
        return $this->belongsTo(User::class, 'encoded_by');
    }

    /** "Jan 05, 2020 – Present" as the printed record shows the service span. */
    public function servicePeriod(): string
    {
        $from = $this->date_from?->format('M d, Y') ?? '—';
        $to = $this->date_to?->format('M d, Y') ?? 'Present';

        return "{$from} – {$to}";
    }
}
