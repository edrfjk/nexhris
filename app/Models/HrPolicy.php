<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrPolicy extends Model
{
    protected $fillable = [
        'title', 'category', 'type', 'body',
        'file_path', 'file_original_name',
        'is_published', 'published_at', 'created_by',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}