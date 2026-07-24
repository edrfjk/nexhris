<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdsQuestionnaire extends Model
{
    protected $table = 'pds_questionnaires';
    protected $guarded = ['id'];

    protected $casts = [
        'related_third_degree' => 'boolean',
        'related_fourth_degree' => 'boolean',
        'found_admin_guilty' => 'boolean',
        'criminally_charged' => 'boolean',
        'criminally_charged_date_filed' => 'date',
        'convicted_crime' => 'boolean',
        'separated_from_service' => 'boolean',
        'candidate_in_election' => 'boolean',
        'resigned_before_election' => 'boolean',
        'acquired_immigrant_status' => 'boolean',
        'is_indigenous_group_member' => 'boolean',
        'is_pwd' => 'boolean',
        'is_solo_parent' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}