<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Person extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'mb_id',
        'mb_fetched_at',
        'gender',
        'born',
        'died',
        'bio',
        'story',
    ];

    protected function casts(): array
    {
        return [
            'born'          => 'date',
            'died'          => 'date',
            'mb_fetched_at' => 'datetime',
        ];
    }

    public function artistMemberships(): HasMany
    {
        return $this->hasMany(ArtistMember::class);
    }

    public function artists(): HasManyThrough
    {
        return $this->hasManyThrough(Artist::class, ArtistMember::class, 'person_id', 'id', 'id', 'artist_id');
    }

    public function songCredits(): HasMany
    {
        return $this->hasMany(SongCredit::class);
    }

    public function releaseCredits(): HasMany
    {
        return $this->hasMany(ReleaseCredit::class);
    }

    public function connectionsFrom(): HasMany
    {
        return $this->hasMany(Connection::class, 'from_id')->where('from_type', 'person');
    }

    public function connectionsTo(): HasMany
    {
        return $this->hasMany(Connection::class, 'to_id')->where('to_type', 'person');
    }

    public function getIsDeceasedAttribute(): bool
    {
        return ! is_null($this->died);
    }
}
