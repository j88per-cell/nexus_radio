<?php

namespace App\Console\Commands\Import;

use App\Models\Artist;
use App\Models\Genre;
use App\Services\ImportIdMap;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportArtists extends Command
{
    protected $signature = 'import:artists {--fresh : Clear existing artist mappings before import}';

    protected $description = 'Import artists from old artist_info table into the new artists table';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            ImportIdMap::clear('artist');
            $this->line('Cleared existing artist mappings.');
        }

        $old = DB::connection('pgsql_old');

        // Pass 1: import from artist_info (has rich data)
        $artistInfoRows = $old->table('artist_info')->get();
        $this->info("Found {$artistInfoRows->count()} rows in artist_info.");

        $created = 0;
        $skipped = 0;

        foreach ($artistInfoRows as $row) {
            if (ImportIdMap::has('artist', $row->artist_id)) {
                $skipped++;
                continue;
            }

            $artist = Artist::create([
                'name'         => $row->artist,
                'slug'         => $this->uniqueSlug($row->artist),
                'type'         => 'band',
                'formed_year'  => $row->formed_year ?: null,
                'origin'       => $row->country ?: null,
                'bio'          => $row->biography ?: null,
                'story'        => $row->bio_summary ?: null,
            ]);

            ImportIdMap::put('artist', $row->artist_id, $artist->id);

            // Attach genre if present
            if (! empty($row->genre)) {
                $genre = $this->firstOrCreateGenre($row->genre);
                $artist->genres()->syncWithoutDetaching([$genre->id => ['primary' => true]]);
            }

            $created++;
        }

        $this->info("Pass 1 (artist_info): created={$created}, skipped={$skipped}");

        // Pass 2: pick up any artist_ids referenced in songs but missing from artist_info
        $unmappedArtists = $old->table('songs')
            ->select('artist_id', 'artist')
            ->whereNotNull('artist_id')
            ->distinct()
            ->get()
            ->reject(fn ($row) => ImportIdMap::has('artist', $row->artist_id));

        $created2 = 0;

        foreach ($unmappedArtists as $row) {
            $artist = Artist::create([
                'name' => $row->artist,
                'slug' => $this->uniqueSlug($row->artist),
                'type' => 'band',
            ]);

            ImportIdMap::put('artist', $row->artist_id, $artist->id);
            $created2++;
        }

        if ($created2 > 0) {
            $this->warn("Pass 2 (songs fallback): created {$created2} bare artist(s) with no artist_info.");
        }

        $total = $created + $created2;
        $this->info("Done. Total artists created: {$total}");

        return self::SUCCESS;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'artist';

        if (! DB::table('artists')->where('slug', $base)->exists()) {
            return $base;
        }

        $i = 2;
        while (DB::table('artists')->where('slug', $base.'-'.$i)->exists()) {
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
