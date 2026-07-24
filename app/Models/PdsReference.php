<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdsReference extends Model
{
    protected $table = 'pds_references';
    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}