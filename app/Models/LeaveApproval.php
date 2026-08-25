<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Audit trail for the leave approval chain. One row per decision, so a form
 * that is returned and re-submitted keeps the full history rather than only
 * the latest verdict.
 */
class LeaveApproval extends Model
{
    protected $fillable = ['leave_application_id', 'stage', 'user_id', 'action', 'remarks'];

    public function leaveApplication()
    {
        return $this->belongsTo(LeaveApplication::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function stageLabel(): string
    {
        return \App\Services\LeaveChain::LABELS[$this->stage] ?? ucfirst($this->stage);
    }
}
