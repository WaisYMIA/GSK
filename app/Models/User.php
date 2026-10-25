<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    // account_type is a coarse actor class (candidate, employer, partner, staff). It grants NO privileges.
    // Privileges come only from role assignments checked by App\Support\Access\AccessService.
    // staff_profile_id is deliberately not mass assignable.
    protected $fillable = ['name', 'email', 'password', 'account_type', 'status'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'invited_at' => 'datetime',
            'suspended_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->deleted_at === null;
    }

    public function isStaff(): bool
    {
        return $this->account_type === 'staff' || $this->staff_profile_id !== null;
    }

    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganisationMembership::class);
    }

    public function candidateProfile(): HasOne
    {
        return $this->hasOne(CandidateProfile::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'candidate_user_id');
    }
}
