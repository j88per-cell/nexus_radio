<?php

namespace App\Console\Commands\Lyrics;

use App\Jobs\Lyrics\FetchLyricJob;
use App\Models\Song;
use App\Services\LyricsService;
use Illuminate\Console\Command;

class DispatchLyricsFetch extends Command
{
    protected $signature = 'lyrics:dispatch
                            {--limit=100 : Max songs to dispatch per run}';

    protected $description = 'Dispatch FetchLyricJob for songs missing lyrics, ordered by artist';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        // Join through tracks → releases → artists so we can order by artist name,
        // processing one artist's songs together in the queue.
        $ids = Song::whereNull('songs.lyrics')
            ->where('songs.lyrics_attempts', '<', LyricsService::MAX_ATTEMPTS)
            ->join('tracks', 'songs.id', '=', 'tracks.song_id')
            ->join('releases', 'tracks.release_id', '=', 'releases.id')
            ->join('artists', 'releases.artist_id', '=', 'artists.id')
            ->whereNull('artists.deleted_at')
            ->orderBy('artists.name')
            ->orderBy('songs.title')
            ->select('songs.id', 'artists.name as artist_name', 'songs.title')
            ->distinct()
            ->limit($limit)
            ->pluck('songs.id');

        if ($ids->isEmpty()) {
            $this->info('No songs missing lyrics.');
            return self::SUCCESS;
        }

        foreach ($ids as $songId) {
            FetchLyricJob::dispatch($songId)->onQueue('lyrics');
        }

        $this->info("Dispatched {$ids->count()} lyrics fetch jobs.");

        return self::SUCCESS;
    }
}
