<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveBalance extends Model
{
    protected $fillable = ['user_id', 'vl_balance', 'sl_balance', 'service_balance'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
