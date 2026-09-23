<?php

namespace App\Console\Commands\Artists;

use App\Jobs\MusicBrainz\FetchArtistFromMusicBrainz;
use App\Models\Artist;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class FetchArtistsMusicBrainz extends Command
{
    protected $signature = 'artists:fetch-musicbrainz
                            {--artist=      : Fetch a specific artist by ID}
                            {--limit=0      : Max artists to dispatch (0 = all)}
                            {--refresh-days=30 : Re-fetch artists last fetched more than N days ago}';

    protected $description = 'Dispatch MusicBrainz fetch jobs for all non-deleted artists, staggered at 4s intervals';

    public function handle(): int
    {
        if ($artistId = $this->option('artist')) {
            $artist = Artist::find((int) $artistId);

            if (! $artist) {
                $this->error("Artist {$artistId} not found.");
                return self::FAILURE;
            }

            FetchArtistFromMusicBrainz::dispatch($artist->id);
            $this->info("Dispatched job for \"{$artist->name}\".");
            return self::SUCCESS;
        }

        $limit       = (int) $this->option('limit');
        $refreshDays = (int) $this->option('refresh-days');
        $stale       = Carbon::now()->subDays($refreshDays);

        $query = Artist::where(function ($q) use ($stale) {
                $q->whereNull('mb_fetched_at')
                  ->orWhere('mb_fetched_at', '<', $stale);
            })
            ->orderBy('id');

        if ($limit) {
            $query->limit($limit);
        }

        $artists = $query->get();

        if ($artists->isEmpty()) {
            $this->info('No artists need fetching.');
            return self::SUCCESS;
        }

        $stagger = (int) config('radio.musicbrainz.stagger', 4);
        $this->info("Dispatching {$artists->count()} artist jobs ({$stagger}s stagger)...");

        $artists->each(function (Artist $artist, int $index) use ($stagger) {
            FetchArtistFromMusicBrainz::dispatch($artist->id)
                ->delay(now()->addSeconds($index * $stagger));
        });

        $this->info('Done. Jobs queued.');

        return self::SUCCESS;
    }
}
