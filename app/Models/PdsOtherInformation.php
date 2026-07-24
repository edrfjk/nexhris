<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdsOtherInformation extends Model
{
    protected $table = 'pds_other_information';
    protected $guarded = ['id'];

    protected $casts = [
        'special_skills_hobbies' => 'array',
        'non_academic_distinctions' => 'array',
        'membership_associations' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}