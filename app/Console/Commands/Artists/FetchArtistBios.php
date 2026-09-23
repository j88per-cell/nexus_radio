<?php

namespace App\Console\Commands\Artists;

use App\Models\Artist;
use App\Services\WikipediaBioService;
use Illuminate\Console\Command;

class FetchArtistBios extends Command
{
    protected $signature = 'artists:fetch-bios
                            {--sleep=4    : Seconds to wait between Wikipedia requests}
                            {--limit=0    : Max artists to process this run (0 = all)}
                            {--refetch    : Also process artists with bio_fetched=true but no bio}';

    protected $description = 'Fetch missing artist bios from Wikipedia';

    public function handle(WikipediaBioService $bioService): int
    {
        $sleep   = max(1, (int) $this->option('sleep'));
        $limit   = (int) $this->option('limit');
        $refetch = $this->option('refetch');

        $query = Artist::query()
            ->when(! $refetch, fn($q) => $q->where('bio_fetched', false))
            ->when($refetch,   fn($q) => $q->where(fn($q2) =>
                $q2->where('bio_fetched', false)
                   ->orWhere(fn($q3) => $q3->whereNull('bio')->orWhere('bio', ''))
            ))
            ->orderBy('id');

        $total = $query->count();

        if ($total === 0) {
            $this->info('No artists to process.');
            return self::SUCCESS;
        }

        $this->info("Found {$total} artists to process. Fetching"
            . ($limit ? " up to {$limit}" : ' all')
            . " ({$sleep}s between requests)…");

        $processed = 0;

        $query->each(function (Artist $artist) use ($bioService, $sleep, $limit, &$processed) {
            if ($limit && $processed >= $limit) {
                return false;
            }

            $this->line("  [{$artist->id}] {$artist->name}");

            $bioService->fetchForArtist($artist);

            $processed++;

            if (! $limit || $processed < $limit) {
                sleep($sleep);
            }
        });

        $this->info("Done. Processed {$processed} artists.");

        return self::SUCCESS;
    }
}
