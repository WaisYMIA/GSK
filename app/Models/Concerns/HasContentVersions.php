<?php

namespace App\Models\Concerns;

use App\Models\ContentVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Shared by Page and Post. The public site reads only the published version, never the current draft.
trait HasContentVersions
{
    abstract public static function contentType(): string;

    public function versions(): HasMany
    {
        return $this->hasMany(ContentVersion::class, 'owner_id')->where('owner_type', static::contentType())->orderByDesc('version_number');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(ContentVersion::class, 'current_version_id');
    }

    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(ContentVersion::class, 'published_version_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_version_id');
    }

    public function isPublic(): bool
    {
        return $this->published_version_id !== null;
    }
}
