<?php

namespace App\Console\Commands\Lyrics;

use App\Models\Song;
use App\Services\LyricsService;
use Illuminate\Console\Command;

class FetchLyrics extends Command
{
    protected $signature = 'lyrics:fetch
                            {--sleep=45    : Seconds to wait between requests}
                            {--limit=0     : Max songs to process this run (0 = all)}
                            {--artist=     : Only fetch lyrics for songs by this artist (partial name match)}
                            {--release=    : Only fetch lyrics for songs on this release/album (partial title match)}';

    protected $description = 'Fetch missing lyrics from lyrics.ovh, throttled to avoid rate limits';

    public function handle(LyricsService $lyrics): int
    {
        $sleep        = (int) $this->option('sleep');
        $limit        = (int) $this->option('limit');
        $artistFilter = $this->option('artist');
        $releaseFilter = $this->option('release');

        $query = Song::whereNull('lyrics')
            ->where('lyrics_attempts', '<', LyricsService::MAX_ATTEMPTS)
            ->whereDoesntHave('tags', fn($t) => $t->where('tag', 'instrumental'))
            ->when($artistFilter, fn($q) => $q->whereHas(
                'tracks.release.artist',
                fn($a) => $a->where('name', 'ilike', "%{$artistFilter}%")
            ))
            ->when($releaseFilter, fn($q) => $q->whereHas(
                'tracks.release',
                fn($r) => $r->where('title', 'ilike', "%{$releaseFilter}%")
            ))
            ->orderBy('id');

        $total = $query->count();

        if ($total === 0) {
            $this->info('No songs missing lyrics.');
            return self::SUCCESS;
        }

        $this->info("Found {$total} songs without lyrics. Fetching" . ($limit ? " up to {$limit}" : ' all') . " ({$sleep}s between requests)...");

        $processed = 0;

        $query->each(function (Song $song) use ($lyrics, $sleep, $limit, &$processed) {
            if ($limit && $processed >= $limit) {
                return false; // stop iteration
            }

            $this->line("  [{$song->id}] {$song->title}");

            try {
                $lyrics->fetchForSong($song);
            } catch (\Throwable $e) {
                $this->error("  [{$song->id}] failed: {$e->getMessage()}");
            }

            $processed++;

            if (! $limit || $processed < $limit) {
                sleep($sleep);
            }
        });

        $this->info("Done. Processed {$processed} songs.");

        return self::SUCCESS;
    }
}
