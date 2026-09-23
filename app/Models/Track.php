<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Track extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'release_id',
        'song_id',
        'position',
        'disc',
        'duration_seconds',
        'navidrome_track_id',
        'file_path',
        'path_confirmed_at',
        'mb_recording_id',
        'mb_duration_seconds',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'path_confirmed_at' => 'datetime',
        ];
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }

    public function playHistory(): HasMany
    {
        return $this->hasMany(PlayHistory::class);
    }

    public function getIsCoverAttribute(): bool
    {
        return $this->song->original_artist_id !== null
            && $this->song->original_artist_id !== $this->release->artist_id;
    }

    public function getDurationFormattedAttribute(): ?string
    {
        if (! $this->duration_seconds) {
            return null;
        }

        return gmdate('i:s', $this->duration_seconds);
    }
}
