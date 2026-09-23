<?php

namespace App\Console\Commands\People;

use App\Jobs\MusicBrainz\FetchPersonFromMusicBrainz;
use App\Jobs\MusicBrainz\SearchPersonOnMusicBrainz;
use App\Models\Person;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class FetchPeopleMusicBrainz extends Command
{
    protected $signature = 'people:fetch-musicbrainz
                            {--limit=0          : Max people to dispatch per phase (0 = all)}
                            {--refresh-days=30  : Re-fetch people last fetched more than N days ago}';

    protected $description = 'Search MB for unmatched people, then fetch full details for those with mb_id';

    public function handle(): int
    {
        $limit       = (int) $this->option('limit');
        $refreshDays = (int) $this->option('refresh-days');
        $stale       = Carbon::now()->subDays($refreshDays);
        $stagger     = 4;
        $offset      = 0;

        // Phase 1: search MB by name for people without mb_id
        $searchQuery = Person::whereNull('mb_id')->orderBy('id');
        if ($limit) {
            $searchQuery->limit($limit);
        }
        $toSearch = $searchQuery->get();

        if ($toSearch->isNotEmpty()) {
            $this->info("Phase 1: searching MB for {$toSearch->count()} unmatched people...");
            $toSearch->each(function (Person $person, int $index) use ($stagger, &$offset) {
                SearchPersonOnMusicBrainz::dispatch($person->id)
                    ->delay(now()->addSeconds($offset * $stagger));
                $offset++;
            });
        } else {
            $this->info('Phase 1: all people already matched to MB.');
        }

        // Phase 2: fetch full details for people with mb_id that are stale/unfetched
        $fetchQuery = Person::whereNotNull('mb_id')
            ->where(function ($q) use ($stale) {
                $q->whereNull('mb_fetched_at')
                  ->orWhere('mb_fetched_at', '<', $stale);
            })
            ->orderBy('id');

        if ($limit) {
            $fetchQuery->limit($limit);
        }

        $toFetch = $fetchQuery->get();

        if ($toFetch->isNotEmpty()) {
            $this->info("Phase 2: fetching MB details for {$toFetch->count()} people...");
            $toFetch->each(function (Person $person, int $index) use ($stagger, $offset) {
                FetchPersonFromMusicBrainz::dispatch($person->id)
                    ->delay(now()->addSeconds(($offset + $index) * $stagger));
            });
        } else {
            $this->info('Phase 2: no people need detail fetching.');
        }

        if ($toSearch->isEmpty() && $toFetch->isEmpty()) {
            $this->info('Nothing to do.');
            return self::SUCCESS;
        }

        $total = $toSearch->count() + $toFetch->count();
        $this->info("Done. {$total} jobs queued.");

        return self::SUCCESS;
    }
}
