<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdsDeclaration extends Model
{
    protected $table = 'pds_declarations';
    protected $guarded = ['id'];
    protected $casts = ['date_accomplished' => 'date'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}