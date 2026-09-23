<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SongRequest extends Model
{
    protected $fillable = [
        'song_id', 'track_id', 'queue_item_id', 'requested_by',
    ];

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    public function queueItem(): BelongsTo
    {
        return $this->belongsTo(QueueItem::class);
    }
}
