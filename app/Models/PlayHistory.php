<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayHistory extends Model
{
    protected $table = 'play_history';

    public $timestamps = false;

    protected $fillable = [
        'track_id',
        'played_at',
    ];

    protected function casts(): array
    {
        return [
            'played_at' => 'datetime',
        ];
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

}
