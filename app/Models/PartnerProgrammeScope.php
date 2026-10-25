<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PartnerProgrammeScope extends Model
{
    protected $fillable = ['organisation_id', 'programme_id', 'function', 'approved_by', 'starts_at', 'expires_at', 'revoked_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')
            ->where(function (Builder $w) {
                $w->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $w) {
                $w->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }
}
