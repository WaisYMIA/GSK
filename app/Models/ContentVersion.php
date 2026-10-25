<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

// Versions are immutable so history cannot be rewritten.
class ContentVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'owner_type', 'owner_id', 'version_number', 'title', 'summary', 'blocks', 'seo_title', 'seo_description',
        'created_by', 'change_note', 'created_at',
    ];

    protected function casts(): array
    {
        return ['blocks' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Content versions are immutable.');
        });
        static::deleting(function () {
            throw new LogicException('Content versions cannot be deleted.');
        });
    }
}
