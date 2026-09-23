<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SongTag extends Model
{
    protected $fillable = [
        'song_id',
        'tag',
    ];

    // Valid tags — used for validation and documentation
    const INSTRUMENTAL = 'instrumental';
    const LIVE         = 'live';
    const ACOUSTIC     = 'acoustic';
    const HOLIDAY      = 'holiday';
    const BONUS        = 'bonus';
    const REMIX        = 'remix';
    const INTRO_OUTRO  = 'intro_outro';
    const COVER        = 'cover';

    const ALL = [
        self::INSTRUMENTAL,
        self::LIVE,
        self::ACOUSTIC,
        self::HOLIDAY,
        self::BONUS,
        self::REMIX,
        self::INTRO_OUTRO,
        self::COVER,
    ];

    // Tags excluded from free play by default
    const FREE_PLAY_EXCLUDED = [
        self::INSTRUMENTAL,
        self::LIVE,
        self::HOLIDAY,
        self::REMIX,
        self::INTRO_OUTRO,
    ];

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }
}
