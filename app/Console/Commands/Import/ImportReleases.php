<?php

namespace App\Console\Commands\Import;

use App\Models\Genre;
use App\Models\Release;
use App\Services\ImportIdMap;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportReleases extends Command
{
    protected $signature = 'import:releases {--fresh : Clear existing release mappings before import}';

    protected $description = 'Import releases (albums) from old songs table into the new releases table';

    public function handle(): int
    {
        if (! DB::table('import_id_maps')->where('entity_type', 'artist')->exists()) {
            $this->error('No artist mappings found. Run import:artists first.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            ImportIdMap::clear('release');
            $this->line('Cleared existing release mappings.');
        }

        $old = DB::connection('pgsql_old');

        // One release per unique album_id — take first row's metadata per album
        $albums = $old->table('songs')
            ->select('album_id', 'album', 'artist_id', 'year', 'genre')
            ->whereNotNull('album_id')
            ->whereNotNull('album')
            ->orderBy('album_id')
            ->get()
            ->unique('album_id');

        $this->info("Found {$albums->count()} unique albums.");

        $created = 0;
        $skipped = 0;
        $warnings = 0;

        foreach ($albums as $album) {
            if (ImportIdMap::has('release', $album->album_id)) {
                $skipped++;
                continue;
            }

            $artistId = ImportIdMap::get('artist', $album->artist_id);

            if (! $artistId) {
                $this->warn("  No artist mapping for navidrome artist_id={$album->artist_id} (album: {$album->album}). Skipping.");
                $warnings++;
                continue;
            }

            $releaseDate = $album->year ? $album->year.'-01-01' : null;

            $release = Release::create([
                'artist_id'          => $artistId,
                'title'              => $album->album,
                'slug'               => $this->uniqueSlug($album->artist_id, $album->album),
                'type'               => 'album',
                'release_date'       => $releaseDate,
                'navidrome_album_id' => $album->album_id,
            ]);

            ImportIdMap::put('release', $album->album_id, $release->id);

            if (! empty($album->genre)) {
                $genre = $this->firstOrCreateGenre($album->genre);
                $release->genres()->syncWithoutDetaching([$genre->id]);
            }

            $created++;
        }

        $this->info("Done. created={$created}, skipped={$skipped}, warnings={$warnings}");

        return self::SUCCESS;
    }

    private function uniqueSlug(string $artistNavId, string $title): string
    {
        // Use artist name from mapping to build a more meaningful slug
        $artistName = DB::table('artists')
            ->join('import_id_maps', function ($join) use ($artistNavId) {
                $join->on('artists.id', '=', 'import_id_maps.new_id')
                    ->where('import_id_maps.entity_type', 'artist')
                    ->where('import_id_maps.old_id', $artistNavId);
            })
            ->value('artists.name');

        $base = Str::slug(($artistName ? $artistName.' ' : '').$title) ?: 'release';

        if (! DB::table('releases')->where('slug', $base)->exists()) {
            return $base;
        }

        $i = 2;
        while (DB::table('releases')->where('slug', $base.'-'.$i)->exists()) {
            $i++;
        }

        return $base.'-'.$i;
    }

    private function firstOrCreateGenre(string $name): Genre
    {
        $slug = Str::slug($name) ?: Str::slug('genre-'.Str::random(4));

        return Genre::firstOrCreate(
            ['name' => $name],
            ['slug' => $slug]
        );
    }
}
