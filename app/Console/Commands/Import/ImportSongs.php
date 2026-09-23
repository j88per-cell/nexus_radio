<?php

namespace App\Console\Commands\Import;

use App\Models\Song;
use App\Services\ImportIdMap;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportSongs extends Command
{
    protected $signature = 'import:songs {--fresh : Clear existing song mappings before import}';

    protected $description = 'Import songs from old songs table into the new songs table (one song per old row, no dedup)';

    public function handle(): int
    {
        if (! DB::table('import_id_maps')->where('entity_type', 'artist')->exists()) {
            $this->error('No artist mappings found. Run import:artists first.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            ImportIdMap::clear('song');
            $this->line('Cleared existing song mappings.');
        }

        $old = DB::connection('pgsql_old');

        $oldSongs = $old->table('songs')->orderBy('id')->get();
        $this->info("Found {$oldSongs->count()} songs to import.");

        $created = 0;
        $skipped = 0;

        foreach ($oldSongs as $row) {
            $oldIdStr = (string) $row->id;

            if (ImportIdMap::has('song', $oldIdStr)) {
                $skipped++;
                continue;
            }

            $artistId = ImportIdMap::get('artist', $row->artist_id);
            // null originalArtist is valid (traditional/unknown), but if we have
            // a mapping it means it's a known artist — use it.

            $song = Song::create([
                'title'              => $row->title,
                'original_artist_id' => $artistId,
                'lyrics'             => $row->lyrics ?: null,
                // old summary → story is the closest semantic match
                'story'              => $row->summary ?: null,
            ]);

            ImportIdMap::put('song', $oldIdStr, $song->id);
            $created++;
        }

        $this->info("Done. created={$created}, skipped={$skipped}");

        return self::SUCCESS;
    }
}
