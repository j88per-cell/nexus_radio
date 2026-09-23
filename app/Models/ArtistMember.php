<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ArtistMember extends Pivot
{
    protected $table = 'artist_members';

    public $incrementing = true;

    protected $fillable = [
        'artist_id',
        'person_id',
        'start_year',
        'end_year',
        'departure_reason',
        'notes',
    ];

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function instruments(): BelongsToMany
    {
        return $this->belongsToMany(Instrument::class, 'artist_member_instruments', 'artist_member_id', 'instrument_id');
    }

    public function getIsCurrentAttribute(): bool
    {
        return is_null($this->end_year);
    }
}
