<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdsCivilServiceEligibility extends Model
{
    protected $table = 'pds_civil_service_eligibilities';
    protected $guarded = ['id'];
    protected $casts = ['exam_date' => 'date', 'license_valid_until' => 'date'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}