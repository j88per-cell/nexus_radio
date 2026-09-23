<?php

return [

    'stream_url' => env('STREAM_URL'),

    'liquidsoap' => [
        'host'    => env('LIQUIDSOAP_HOST', '127.0.0.1'),
        'port'    => (int) env('LIQUIDSOAP_PORT', 1234),
        'queue'   => env('LIQUIDSOAP_QUEUE', 'queue'),
        'timeout' => (int) env('LIQUIDSOAP_TIMEOUT', 5),
    ],

    'supervisor' => [
        'url'  => env('SUPERVISOR_URL', 'http://127.0.0.1:9001'),
        'user' => env('SUPERVISOR_USER'),
        'pass' => env('SUPERVISOR_PASS'),
    ],

    'musicbrainz' => [
        // Seconds between queued MusicBrainz fetch jobs (free tier: 1 req/sec max)
        'stagger' => (int) env('MB_STAGGER', 4),
        // Minimum real gap enforced between actual outbound MB requests
        // (MusicBrainzService::throttle()), regardless of worker count.
        'min_gap' => (float) env('MB_MIN_GAP', 1.5),
    ],

    'playback' => [
        // Seconds before the same artist can play again
        'artist_cooldown' => (int) env('DJ_ARTIST_COOLDOWN', 3600),
        // Seconds before the same song can play again (default 4 hours)
        'song_cooldown'  => (int) env('DJ_SONG_COOLDOWN', 14400),
        // How many tracks ahead the scheduler looks when building a queue.
        // 1 = current playing + one queued up next, nothing more.
        'queue_lookahead' => (int) env('DJ_QUEUE_LOOKAHEAD', 2),
        // Comma-separated list of genres the station plays. Empty = no genre filter.
        'genres' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('DJ_GENRES', ''))
        ))),
    ],

];
