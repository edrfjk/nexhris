<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'employee_number',
        'name',
        'email',
        'position',
        'department',
        'contact_number',
        'role',
        'status',
        'password',
        'failed_login_attempts',
        'locked_until',
        'profile_photo_path',
        'program',
        'first_day_of_service',
        'college_id',
        'department_id',
        'date_hired',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        // Behind the ID card's QR code; never render it in a payload.
        'verification_token',
    ];

    protected static function booted(): void
    {
        // Every account is verifiable from the moment it exists.
        static::creating(function (self $user) {
            $user->verification_token ??= \Illuminate\Support\Str::random(40);
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'first_day_of_service' => 'date',
            'date_hired' => 'date',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function leaveBalance()
{
    return $this->hasOne(LeaveBalance::class);
}

public function leaveLedgerEntries()
{
    return $this->hasMany(LeaveLedgerEntry::class)->orderBy('period_from');
}

public function leaveApplications()
{
    return $this->hasMany(LeaveApplication::class);
}

public function pdsPersonalInformation()
{
    return $this->hasOne(PdsPersonalInformation::class);
}

public function pdsFamilyBackground()
{
    return $this->hasOne(PdsFamilyBackground::class);
}

public function pdsChildren()
{
    return $this->hasMany(PdsChild::class);
}

public function pdsEducationalBackgrounds()
{
    return $this->hasMany(PdsEducationalBackground::class);
}

public function pdsCivilServiceEligibilities()
{
    return $this->hasMany(PdsCivilServiceEligibility::class);
}

public function pdsWorkExperiences()
{
    return $this->hasMany(PdsWorkExperience::class)->orderByDesc('date_from');
}

public function pdsVoluntaryWorks()
{
    return $this->hasMany(PdsVoluntaryWork::class);
}

public function pdsTrainings()
{
    return $this->hasMany(PdsTraining::class);
}

public function pdsOtherInformation()
{
    return $this->hasOne(PdsOtherInformation::class);
}

public function pdsQuestionnaire()
{
    return $this->hasOne(PdsQuestionnaire::class);
}

public function pdsReferences()
{
    return $this->hasMany(PdsReference::class);
}

public function pdsDeclaration()
{
    return $this->hasOne(PdsDeclaration::class);
}

public function pdsSubmissions()
{
    return $this->hasMany(PdsSubmission::class);
}

public function policyViews()
{
    return $this->hasMany(HrPolicyView::class);
}

public function isDean(): bool
{
    return $this->role === 'dean';
}

public function isCampusDirector(): bool
{
    return $this->role === 'campus_director';
}

public function isEmployee(): bool
{
    return $this->role === 'employee';
}

/** Anyone who reviews leave forms: Dean, HR Administrator, Campus Director. */
public function isReviewer(): bool
{
    return in_array($this->role, ['admin', 'dean', 'campus_director'], true);
}

/**
 * Which stage of the leave approval chain this user acts on.
 * Returns null for plain employees.
 */
public function approvalStage(): ?string
{
    return match ($this->role) {
        'dean' => 'dean',
        'admin' => 'hr',
        'campus_director' => 'campus_director',
        default => null,
    };
}

public function roleLabel(): string
{
    return match ($this->role) {
        'admin' => 'HR Administrator',
        'dean' => 'Dean',
        'campus_director' => 'Campus Director',
        default => 'Employee',
    };
}

/** Where this role lands after signing in. */
public function homeRoute(): string
{
    return match ($this->role) {
        'admin' => '/admin/dashboard',
        'dean', 'campus_director' => '/admin/leave/review',
        default => '/dashboard',
    };
}

public function activityLogs()
{
    return $this->hasMany(ActivityLog::class)->latest();
}
public function college()
{
    return $this->belongsTo(College::class);
}

/**
 * Named `departmentRecord` rather than `department` because `department` is
 * also a legacy string column (the college code) that the printed ledger
 * card and service record still read. An attribute of the same name
 * shadows the relation on property access, so the two must not collide.
 */
public function departmentRecord()
{
    return $this->belongsTo(Department::class, 'department_id');
}

/** The department name, falling back to the legacy free-text programme. */
public function departmentName(): ?string
{
    return $this->departmentRecord?->name ?: ($this->program ?: null);
}

/** The college name, falling back to the legacy free-text code. */
public function collegeName(): ?string
{
    return $this->college?->name ?: ($this->attributes['department'] ?? null) ?: null;
}

/**
 * One line naming where this person sits in the organisation, e.g.
 * "College of Arts and Sciences - BS Information Technology".
 *
 * Every screen that prints an employee's affiliation reads this, so the
 * ledger card, the PDS review and the directory can never disagree.
 */
public function orgLine(string $separator = " \u{00B7} "): string
{
    return collect([$this->collegeName(), $this->departmentName()])
        ->filter()
        ->unique()
        ->implode($separator) ?: 'No college assigned';
}

/** The college this Dean signs for, if they are one. */
public function deanOfCollege()
{
    return $this->hasOne(College::class, 'dean_id');
}

/**
 * Limits a User query to the records this viewer may see.
 *
 * A Dean sees only their own college; HR and the Campus Director see
 * everyone; a plain employee sees only themselves. Enforced here so every
 * call site inherits the same boundary instead of re-deriving it.
 */
public function scopeVisibleTo($query, User $viewer)
{
    if ($viewer->isDean()) {
        return $query->where('college_id', $viewer->college_id);
    }

    if ($viewer->isAdmin() || $viewer->isCampusDirector()) {
        return $query;
    }

    return $query->whereKey($viewer->id);
}

public function serviceRecords()
{
    return $this->hasMany(ServiceRecord::class)->orderBy('date_from');
}

/** Family name, first name and middle initial, as the ledger card prints them. */
public function nameParts(): array
{
    $name = trim((string) $this->name);

    // "SURNAME, First M." is the form HR enters most often; fall back to
    // treating the last word as the surname when there is no comma.
    if (str_contains($name, ',')) {
        [$family, $rest] = array_map('trim', explode(',', $name, 2));
    } else {
        $words = preg_split('/\s+/', $name) ?: [];
        $family = count($words) > 1 ? array_pop($words) : $name;
        $rest = implode(' ', $words);
    }

    $restWords = $rest === '' ? [] : preg_split('/\s+/', $rest);
    $middle = '';

    if (count($restWords) > 1) {
        $last = end($restWords);
        // A trailing initial like "S." or a full middle name both reduce to
        // a single printed initial on the official card.
        $middle = strtoupper(substr(trim($last, '.'), 0, 1)) . '.';
        array_pop($restWords);
    }

    return [
        'family' => strtoupper($family),
        'first' => strtoupper(implode(' ', $restWords)),
        'middle' => $middle,
    ];
}
}