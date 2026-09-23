<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StationId extends Model
{
    protected $fillable = [
        'script',
        'audio_path',
        'duration_seconds',
        'tags',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
        ];
    }
}
