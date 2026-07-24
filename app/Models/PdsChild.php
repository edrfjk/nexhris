<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdsChild extends Model
{
    protected $table = 'pds_children';
    protected $guarded = ['id'];
    protected $casts = ['date_of_birth' => 'date'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}