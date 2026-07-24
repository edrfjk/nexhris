<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveLedgerEntry extends Model
{
    protected $fillable = [
        'user_id', 'period_from', 'period_to', 'remarks', 'type',
        'vl_earned', 'vl_used', 'vl_balance',
        'sl_earned', 'sl_used', 'sl_balance',
        'leave_application_id', 'encoded_by',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}