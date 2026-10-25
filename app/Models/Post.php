<?php

namespace App\Models;

use App\Models\Concerns\HasContentVersions;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasContentVersions;

    protected $fillable = [
        'slug', 'title', 'category', 'sensitive_area', 'status', 'current_version_id',
        'published_version_id', 'published_at', 'created_by',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public static function contentType(): string
    {
        return 'post';
    }
}
