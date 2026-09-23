<?php

namespace App\Jobs\Lyrics;

use App\Models\Song;
use App\Services\LyricsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class FetchLyricJob implements ShouldQueue
{
    use Queueable;

    public int $tries   = 3;
    public int $timeout = 30;
    public int $backoff = 10;

    public function __construct(public readonly int $songId) {}

    public function handle(LyricsService $lyrics): void
    {
        // One request per 5 seconds across all workers
        if (RateLimiter::tooManyAttempts('lyrics-ovh', 1)) {
            $wait = RateLimiter::availableIn('lyrics-ovh') + 1;
            Log::debug("FetchLyricJob: rate limited, releasing back in {$wait}s");
            $this->release($wait);
            return;
        }

        RateLimiter::hit('lyrics-ovh', 5);

        $song = Song::find($this->songId);

        if (! $song || $song->lyrics !== null || $song->lyrics_attempts >= LyricsService::MAX_ATTEMPTS) {
            return;
        }

        $lyrics->fetchForSong($song);
    }
}
