<?php

namespace App\Console\Commands\Artists;

use App\Models\Artist;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RollupArtistGenres extends Command
{
    protected $signature = 'artists:rollup-genres
                            {--top=5   : Max number of genres to assign per artist}
                            {--force   : Re-assign genres even if artist already has some}';

    protected $description = 'Populate artist_genres from release genres, ranked by frequency across releases';

    public function handle(): int
    {
        $top   = (int) $this->option('top');
        $force = $this->option('force');

        $query = Artist::has('releases');

        if (! $force) {
            $query->doesntHave('genres');
        }

        $artists = $query->get();

        if ($artists->isEmpty()) {
            $this->info('No artists need genre rollup. Use --force to re-assign all.');
            return self::SUCCESS;
        }

        $this->info("Rolling up genres for {$artists->count()} artists (top {$top} by frequency)...");

        $bar = $this->output->createProgressBar($artists->count());
        $bar->start();

        foreach ($artists as $artist) {
            // Count genre occurrences across all releases for this artist
            $genreCounts = DB::table('release_genres')
                ->join('releases', 'releases.id', '=', 'release_genres.release_id')
                ->join('genres', 'genres.id', '=', 'release_genres.genre_id')
                ->where('releases.artist_id', $artist->id)
                ->whereNull('releases.deleted_at')
                ->select('genres.id', 'genres.name', DB::raw('count(*) as count'))
                ->groupBy('genres.id', 'genres.name')
                ->orderByDesc('count')
                ->limit($top)
                ->get();

            if ($genreCounts->isEmpty()) {
                $bar->advance();
                continue;
            }

            // Sync genres — first result is primary
            $syncData = [];
            foreach ($genreCounts as $index => $genre) {
                $syncData[$genre->id] = ['primary' => $index === 0];
            }

            $artist->genres()->sync($syncData);

            $bar->advance();
        }

        $bar->finish();
        $this->line('');
        $this->info('Done.');

        return self::SUCCESS;
    }
}
