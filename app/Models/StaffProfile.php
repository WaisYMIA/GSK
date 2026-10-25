<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffProfile extends Model
{
    protected $fillable = ['person_ref', 'full_name', 'work_email', 'status', 'identity_checked_at', 'identity_checked_by'];

    protected function casts(): array
    {
        return ['identity_checked_at' => 'datetime'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(StaffInvitation::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
