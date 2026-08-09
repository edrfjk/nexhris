<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdsTemplate extends Model
{
    protected $fillable = ['label', 'file_path', 'original_filename', 'is_active', 'uploaded_by'];
    protected $casts = ['is_active' => 'boolean'];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}