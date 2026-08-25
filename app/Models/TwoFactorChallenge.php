<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TwoFactorChallenge extends Model
{
    /** A challenge dies after this many wrong guesses. */
    public const MAX_ATTEMPTS = 5;

    protected $fillable = [
        'user_id', 'code_hash', 'expires_at', 'attempts', 'consumed_at', 'ip_address',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function isExhausted(): bool
    {
        return $this->attempts >= self::MAX_ATTEMPTS;
    }

    public function isUsable(): bool
    {
        return ! $this->isExpired() && ! $this->isConsumed() && ! $this->isExhausted();
    }

    public function attemptsLeft(): int
    {
        return max(0, self::MAX_ATTEMPTS - $this->attempts);
    }
}
