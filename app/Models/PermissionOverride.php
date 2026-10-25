<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermissionOverride extends Model
{
    protected $fillable = ['user_id', 'permission_id', 'effect', 'scope_type', 'scope_id', 'reason', 'granted_by', 'expires_at', 'revoked_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')
            ->where(function (Builder $w) {
                $w->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }
}
