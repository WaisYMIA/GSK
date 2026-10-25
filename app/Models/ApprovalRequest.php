<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalRequest extends Model
{
    protected $fillable = [
        'action_type', 'subject_type', 'subject_id', 'requested_by', 'requester_staff_profile_id',
        'status', 'reason', 'payload', 'expires_at', 'decided_at', 'consumed_at',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array', 'expires_at' => 'datetime', 'decided_at' => 'datetime', 'consumed_at' => 'datetime'];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(ApprovalDecision::class);
    }
}
