<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Genre extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    public function artists(): BelongsToMany
    {
        return $this->belongsToMany(Artist::class, 'artist_genres')
            ->withPivot('primary');
    }

    public function releases(): BelongsToMany
    {
        return $this->belongsToMany(Release::class, 'release_genres');
    }
}
