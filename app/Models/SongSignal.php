<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SongSignal extends Model
{
    protected $fillable = [
        'song_id',
        'energy',
        'tempo',
        'notes',
        'integrated_loudness',
        'true_peak',
        'loudness_range',
        'loudness_analyzed',
        'tempo_bpm',
        'energy_raw',
        'key',
        'danceability',
        'mood_tags',
        'embedding',
        'audio_analyzed',
        'shape_note',
        'shape_note_meta',
    ];

    protected $casts = [
        'integrated_loudness' => 'float',
        'true_peak'           => 'float',
        'loudness_range'      => 'float',
        'loudness_analyzed'   => 'boolean',
        'tempo_bpm'           => 'float',
        'energy_raw'          => 'float',
        'danceability'        => 'float',
        'mood_tags'           => 'array',
        'embedding'           => 'array',
        'audio_analyzed'      => 'boolean',
        'shape_note_meta'     => 'array',
    ];

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }
}
