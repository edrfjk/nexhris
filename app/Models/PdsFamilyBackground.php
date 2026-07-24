<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdsFamilyBackground extends Model
{
    protected $table = 'pds_family_backgrounds';
    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}