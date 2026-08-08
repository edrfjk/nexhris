<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrPolicy extends Model
{
    protected $fillable = [
        'title', 'category', 'type', 'body',
        'file_path', 'file_original_name', 'link_url',
        'is_published', 'published_at',
        'is_pinned', 'effective_date', 'expiry_date',
        'requires_acknowledgment', 'created_by',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'is_pinned' => 'boolean',
        'requires_acknowledgment' => 'boolean',
        'published_at' => 'datetime',
        'effective_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function views()
    {
        return $this->hasMany(HrPolicyView::class);
    }

    public function categoryMeta(): array
    {
        return config("policy_categories.{$this->category}") ?? config('policy_categories.default');
    }

    public function statusLabel(): string
    {
        $today = now()->startOfDay();

        if ($this->expiry_date && $this->expiry_date->lt($today)) {
            return 'expired';
        }

        if ($this->effective_date && $this->effective_date->gt($today)) {
            return 'upcoming';
        }

        return 'active';
    }
}