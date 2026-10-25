<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organisation extends Model
{
    use SoftDeletes;

    protected $fillable = ['owner_user_id', 'legal_name', 'registration_number', 'country_code', 'verification_status', 'verification_notes', 'verified_at'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function vacancies(): HasMany
    {
        return $this->hasMany(Vacancy::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganisationMembership::class);
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified' && $this->verified_at !== null;
    }
}
