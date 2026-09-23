<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackTag extends Model
{
    protected $fillable = [
        'track_id',
        'tag',
    ];

    const INSTRUMENTAL = 'instrumental';
    const LIVE         = 'live';
    const ACOUSTIC     = 'acoustic';
    const HOLIDAY      = 'holiday';
    const BONUS        = 'bonus';
    const REMIX        = 'remix';

    const ALL = [
        self::INSTRUMENTAL,
        self::LIVE,
        self::ACOUSTIC,
        self::HOLIDAY,
        self::BONUS,
        self::REMIX,
    ];

    // Tags excluded from free play by default
    const FREE_PLAY_EXCLUDED = [
        self::INSTRUMENTAL,
        self::LIVE,
        self::HOLIDAY,
        self::REMIX,
    ];

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }
}
