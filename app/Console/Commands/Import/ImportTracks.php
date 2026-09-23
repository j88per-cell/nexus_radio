<?php

namespace App\Console\Commands\Import;

use App\Models\Track;
use App\Services\ImportIdMap;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportTracks extends Command
{
    protected $signature = 'import:tracks {--fresh : Clear existing track mappings before import}';

    protected $description = 'Import tracks from old songs table into the new tracks table';

    public function handle(): int
    {
        foreach (['artist', 'release', 'song'] as $type) {
            if (! DB::table('import_id_maps')->where('entity_type', $type)->exists()) {
                $this->error("No {$type} mappings found. Run import:{$type}s first.");

                return self::FAILURE;
            }
        }

        if ($this->option('fresh')) {
            ImportIdMap::clear('track');
            $this->line('Cleared existing track mappings.');
        }

        $old = DB::connection('pgsql_old');

        // Order by album then old id — this becomes our track position within each release.
        // Old data has no explicit track ordering; using insertion order (id) is the best we have.
        $oldSongs = $old->table('songs')
            ->orderBy('album_id')
            ->orderBy('id')
            ->get();

        $this->info("Found {$oldSongs->count()} tracks to import.");

        // Track position counters per release_id
        $positions = [];

        $created = 0;
        $skipped = 0;
        $warnings = 0;

        foreach ($oldSongs as $row) {
            $oldIdStr = (string) $row->id;

            if (ImportIdMap::has('track', $oldIdStr)) {
                $skipped++;
                continue;
            }

            $releaseId = ImportIdMap::get('release', $row->album_id);
            $songId    = ImportIdMap::get('song', $oldIdStr);

            if (! $releaseId || ! $songId) {
                $this->warn("  Missing mapping for old song id={$row->id} (album_id={$row->album_id}). Skipping.");
                $warnings++;
                continue;
            }

            $positions[$releaseId] = ($positions[$releaseId] ?? 0) + 1;

            // duration from Navidrome is a float in seconds
            $durationSeconds = $row->duration ? (int) round((float) $row->duration) : null;

            $track = Track::create([
                'release_id'         => $releaseId,
                'song_id'            => $songId,
                'position'           => $positions[$releaseId],
                'disc'               => 1,
                'duration_seconds'   => $durationSeconds,
                'navidrome_track_id' => $row->navidrome_id ?: null,
                // do_not_play and file_path are not in the new tracks schema;
                // those come from Navidrome at runtime.
            ]);

            ImportIdMap::put('track', $oldIdStr, $track->id);
            $created++;
        }

        $this->info("Done. created={$created}, skipped={$skipped}, warnings={$warnings}");

        return self::SUCCESS;
    }
}
