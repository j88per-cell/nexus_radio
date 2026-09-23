<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArtistInfluence extends Model
{
    protected $table = 'artist_influences';

    protected $fillable = [
        'artist_id',
        'influenced_by_artist_id',
        'notes',
    ];

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function influencer(): BelongsTo
    {
        return $this->belongsTo(Artist::class, 'influenced_by_artist_id');
    }
}
