<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Instrument extends Model
{
    protected $fillable = ['name', 'slug', 'category'];

    public function artistMembers(): BelongsToMany
    {
        return $this->belongsToMany(ArtistMember::class, 'artist_member_instruments');
    }
}
