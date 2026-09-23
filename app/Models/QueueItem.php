<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueItem extends Model
{
    const TYPE_SONG = 'song';
    const TYPE_AD   = 'ad';

    const STATUS_PENDING = 'pending';
    const STATUS_PUSHED  = 'pushed';
    const STATUS_PLAYING = 'playing';
    const STATUS_PLAYED  = 'played';
    const STATUS_STALLED = 'stalled';

    protected $fillable = [
        'type',
        'status',
        'priority',
        'track_id',
        'audio_path',
        'pushed_at',
        'played_at',
    ];

    protected function casts(): array
    {
        return [
            'pushed_at' => 'datetime',
            'played_at' => 'datetime',
        ];
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    public function isSong(): bool
    {
        return $this->type === self::TYPE_SONG;
    }
}
