<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShowTrack extends Model
{
    protected $fillable = [
        'show_id',
        'track_id',
        'position',
        'duration_seconds',
    ];

    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    /**
     * Convenience: load the song title / artist / album from the track hierarchy.
     */
    public function getSongTitleAttribute(): ?string
    {
        return $this->track?->song?->title;
    }

    public function getArtistNameAttribute(): ?string
    {
        return $this->track?->release?->artist?->name;
    }

    public function getAlbumTitleAttribute(): ?string
    {
        return $this->track?->release?->title;
    }
}
