<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SongCredit extends Model
{
    protected $fillable = [
        'song_id',
        'person_id',
        'role',
    ];

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
