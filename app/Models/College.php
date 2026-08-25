<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A college or administrative office. This is the unit the Dean's data
 * boundary is drawn around — approval queues, calendars and dashboards all
 * scope on `college_id`.
 */
class College extends Model
{
    protected $fillable = ['code', 'name', 'short_name', 'description', 'dean_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function employees()
    {
        return $this->hasMany(User::class);
    }

    public function departments()
    {
        return $this->hasMany(Department::class)->orderBy('name');
    }

    public function activeDepartments()
    {
        return $this->hasMany(Department::class)->where('is_active', true)->orderBy('name');
    }

    /** Everyone in this college who is not a Dean or the Campus Director. */
    public function staff()
    {
        return $this->hasMany(User::class)->where('role', 'employee');
    }

    public function dean()
    {
        return $this->belongsTo(User::class, 'dean_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function label(): string
    {
        return $this->short_name ? "{$this->short_name} — {$this->name}" : $this->name;
    }

    /** A college with people or departments must not be deleted outright. */
    public function isDeletable(): bool
    {
        return $this->employees()->count() === 0
            && $this->departments()->count() === 0;
    }
}
