<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrPolicyView extends Model
{
    protected $fillable = ['hr_policy_id', 'user_id', 'viewed_at', 'acknowledged_at'];

    protected $casts = [
        'viewed_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function policy()
    {
        return $this->belongsTo(HrPolicy::class, 'hr_policy_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}