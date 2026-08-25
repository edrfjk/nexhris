<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A dated notice from HR. Distinct from an HR policy, which is a standing
 * document requiring acknowledgment.
 */
class Announcement extends Model
{
    protected $fillable = [
        'title', 'body', 'category', 'is_pinned',
        'is_published', 'published_at', 'college_id', 'posted_by',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function college()
    {
        return $this->belongsTo(College::class);
    }

    /** Published notices visible to this reader, newest first, pinned on top. */
    public function scopeVisibleTo($query, User $reader)
    {
        return $query
            ->where('is_published', true)
            ->where(function ($q) use ($reader) {
                // Campus-wide notices, plus any aimed at the reader's college.
                $q->whereNull('college_id')
                    ->orWhere('college_id', $reader->college_id);
            })
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at');
    }

    public function excerpt(int $words = 28): string
    {
        return \Illuminate\Support\Str::words(strip_tags($this->body), $words);
    }
}
