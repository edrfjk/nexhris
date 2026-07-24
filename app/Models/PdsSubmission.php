<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdsSubmission extends Model
{
    protected $table = 'pds_submissions';
    protected $guarded = ['id'];
    protected $casts = ['submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}