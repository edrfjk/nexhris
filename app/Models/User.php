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
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
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
}