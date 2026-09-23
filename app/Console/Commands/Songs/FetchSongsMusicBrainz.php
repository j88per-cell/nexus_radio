<?php

namespace App\Console\Commands\Songs;

use App\Jobs\MusicBrainz\FetchSongFromMusicBrainz;
use App\Models\Song;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class FetchSongsMusicBrainz extends Command
{
    protected $signature = 'songs:fetch-musicbrainz
                            {--limit=0      : Max songs to dispatch (0 = all)}
                            {--refresh-days=30 : Re-fetch songs last fetched more than N days ago}';

    protected $description = 'Dispatch MusicBrainz fetch jobs for songs, staggered at 4s intervals';

    public function handle(): int
    {
        $limit       = (int) $this->option('limit');
        $refreshDays = (int) $this->option('refresh-days');
        // refresh-days=0 means "not already done today", not "before this exact
        // microsecond" — otherwise a re-run later the same day (e.g. after an
        // interrupted earlier pass) re-dispatches songs that were just fetched
        // minutes ago, instead of picking up only where the last run left off.
        $stale = $refreshDays > 0 ? Carbon::now()->subDays($refreshDays) : Carbon::today();

        $query = Song::where(function ($q) use ($stale) {
                $q->whereNull('mb_fetched_at')
                  ->orWhere('mb_fetched_at', '<', $stale)
                  ->orWhereHas('tracks', fn($q) => $q
                      ->whereNull('mb_recording_id')
                      ->whereNotNull('navidrome_track_id')
                  );
            })
            ->orderBy('id');

        if ($limit) {
            $query->limit($limit);
        }

        $songs = $query->get();

        if ($songs->isEmpty()) {
            $this->info('No songs need fetching.');
            return self::SUCCESS;
        }

        $stagger = (int) config('radio.musicbrainz.stagger', 4);
        $this->info("Dispatching {$songs->count()} song jobs ({$stagger}s stagger)...");

        $songs->each(function (Song $song, int $index) use ($stagger) {
            FetchSongFromMusicBrainz::dispatch($song->id)
                ->delay(now()->addSeconds($index * $stagger));
        });

        $this->info('Done. Jobs queued.');

        return self::SUCCESS;
    }
}
