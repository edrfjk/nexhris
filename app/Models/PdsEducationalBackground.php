<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdsEducationalBackground extends Model
{
    protected $table = 'pds_educational_backgrounds';
    protected $guarded = ['id'];
    protected $casts = ['period_from' => 'date', 'period_to' => 'date'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}