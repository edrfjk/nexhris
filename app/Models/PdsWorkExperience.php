<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdsWorkExperience extends Model
{
    protected $table = 'pds_work_experiences';
    protected $guarded = ['id'];
    protected $casts = ['date_from' => 'date', 'date_to' => 'date', 'is_government_service' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}