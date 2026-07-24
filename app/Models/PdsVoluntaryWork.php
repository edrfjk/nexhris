<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdsVoluntaryWork extends Model
{
    protected $table = 'pds_voluntary_works';
    protected $guarded = ['id'];
    protected $casts = ['date_from' => 'date', 'date_to' => 'date'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}