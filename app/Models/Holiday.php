<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    public const CATEGORIES = [
        'regular' => 'Regular holiday',
        'special_non_working' => 'Special non-working day',
        'special_local' => 'Special local non-working day',
        'special_working' => 'Special working day',
        'religious' => 'Religious holiday',
    ];

    /**
     * Whether a new holiday of this category is left out of leave days unless
     * HR says otherwise. A special working day is a working day, so it is
     * charged. A religious holiday applies only to the employees it covers,
     * so HR decides each one.
     */
    public const EXCLUDED_BY_DEFAULT = [
        'regular' => true,
        'special_non_working' => true,
        'special_local' => true,
        'special_working' => false,
        'religious' => false,
    ];

    public const SCOPES = [
        'national' => 'Nationwide',
        'local' => 'Local',
    ];

    protected $fillable = [
        'name', 'date', 'category', 'scope', 'locality', 'excludes_from_leave', 'source',
    ];

    protected $casts = [
        'date' => 'date',
        'excludes_from_leave' => 'boolean',
    ];

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }
}
