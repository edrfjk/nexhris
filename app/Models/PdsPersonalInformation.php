<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdsPersonalInformation extends Model
{
    protected $table = 'pds_personal_information';
    protected $guarded = ['id'];

    protected $casts = [
        'date_of_birth' => 'date',
        'is_dual_citizen' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}