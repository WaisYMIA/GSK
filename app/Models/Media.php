<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = [
        'uploaded_by', 'stored_path', 'mime', 'size_bytes', 'width', 'height', 'alt_text', 'caption',
        'credit_or_source', 'rights_confirmed', 'people_consent_recorded', 'status',
    ];

    protected function casts(): array
    {
        return ['rights_confirmed' => 'boolean', 'people_consent_recorded' => 'boolean'];
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->stored_path);
    }
}
