<?php

namespace App\Models;

use App\Models\Concerns\HasEmbedding;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Pgvector\Laravel\Vector;

class Song extends Model
{
    use HasEmbedding, HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'original_artist_id',
        'lyrics',
        'lyrics_fetched',
        'lyrics_attempts',
        'mb_fetched_at',
        'story',
        'embedding',
        'blocked',
        'requires_predecessor_song_id',
    ];

    protected function casts(): array
    {
        return [
            'embedding'       => Vector::class,
            'blocked'         => 'boolean',
            'lyrics_attempts' => 'integer',
        ];
    }

    public function originalArtist(): BelongsTo
    {
        return $this->belongsTo(Artist::class, 'original_artist_id');
    }

    public function tags(): HasMany
    {
        return $this->hasMany(SongTag::class);
    }

    public function credits(): HasMany
    {
        return $this->hasMany(SongCredit::class);
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(Track::class);
    }

    public function releases(): HasManyThrough
    {
        return $this->hasManyThrough(Release::class, Track::class, 'song_id', 'id', 'id', 'release_id');
    }

    public function signal(): HasOne
    {
        return $this->hasOne(SongSignal::class);
    }

    public function isCoverOn(Release $release): bool
    {
        return $this->original_artist_id !== null
            && $this->original_artist_id !== $release->artist_id;
    }

    /** The song that must play immediately before this one (e.g. an intro piece). */
    public function requiredPredecessor(): BelongsTo
    {
        return $this->belongsTo(Song::class, 'requires_predecessor_song_id');
    }

    /** Songs that require this song to play immediately before them. */
    public function requiredBy(): HasMany
    {
        return $this->hasMany(Song::class, 'requires_predecessor_song_id');
    }
}
