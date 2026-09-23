<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Release extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'artist_id',
        'title',
        'slug',
        'type',
        'release_date',
        'catalog_number',
        'event_name',
        'venue',
        'description',
        'navidrome_album_id',
        'blocked',
    ];

    protected function casts(): array
    {
        return [
            'release_date' => 'date',
            'blocked'      => 'boolean',
        ];
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function artists(): BelongsToMany
    {
        return $this->belongsToMany(Artist::class, 'release_artists')
            ->withPivot('role');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'release_labels');
    }

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'release_genres');
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(Track::class)->orderBy('disc')->orderBy('position');
    }

    public function songs(): HasManyThrough
    {
        return $this->hasManyThrough(Song::class, Track::class, 'release_id', 'id', 'id', 'song_id');
    }

    public function credits(): HasMany
    {
        return $this->hasMany(ReleaseCredit::class)->with('person');
    }

    public function getIsLiveAttribute(): bool
    {
        return $this->type === 'live';
    }
}
