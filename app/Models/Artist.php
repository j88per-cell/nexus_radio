<?php

namespace App\Models;

use App\Models\Concerns\HasEmbedding;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Pgvector\Laravel\Vector;

class Artist extends Model
{
    use HasEmbedding, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'navidrome_artist_id',
        'type',
        'formed_year',
        'disbanded_year',
        'origin',
        'bio',
        'bio_fetched',
        'mb_fetched_at',
        'story',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'embedding' => Vector::class,
        ];
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'artist_members')
            ->withPivot(['start_year', 'end_year', 'departure_reason', 'notes'])
            ->withTimestamps()
            ->using(ArtistMember::class);
    }

    public function currentMembers(): BelongsToMany
    {
        return $this->members()->wherePivotNull('end_year');
    }

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'artist_genres')
            ->withPivot('primary');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'artist_labels')
            ->withPivot(['start_year', 'end_year', 'notes'])
            ->withTimestamps();
    }

    public function releases(): HasMany
    {
        return $this->hasMany(Release::class);
    }

    public function songs(): HasMany
    {
        return $this->hasMany(Song::class, 'original_artist_id');
    }

    public function influences(): BelongsToMany
    {
        return $this->belongsToMany(
            Artist::class,
            'artist_influences',
            'artist_id',
            'influenced_by_artist_id'
        )->withPivot('notes')->withTimestamps();
    }

    public function influencedArtists(): BelongsToMany
    {
        return $this->belongsToMany(
            Artist::class,
            'artist_influences',
            'influenced_by_artist_id',
            'artist_id'
        )->withPivot('notes')->withTimestamps();
    }

    public function connectionsFrom(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Connection::class, 'from_id')->where('from_type', 'artist');
    }

    public function connectionsTo(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Connection::class, 'to_id')->where('to_type', 'artist');
    }

    public function getIsActivAttribute(): bool
    {
        return is_null($this->disbanded_year);
    }
}
