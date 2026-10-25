<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BreakGlassGrant extends Model
{
    protected $fillable = [
        'user_id', 'staff_profile_id', 'reason', 'permission_keys', 'activated_at', 'expires_at', 'ended_at',
        'review_status', 'reviewed_by', 'reviewer_staff_profile_id', 'reviewed_at', 'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'permission_keys' => 'array',
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'ended_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function scopeActiveNow(Builder $query): Builder
    {
        return $query->whereNull('ended_at')->where('expires_at', '>', now());
    }

    public function isActiveNow(): bool
    {
        return $this->ended_at === null && $this->expires_at !== null && $this->expires_at->isFuture();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
