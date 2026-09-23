<?php

namespace App\Console\Commands\Import;

use App\Models\PlayHistory;
use App\Services\ImportIdMap;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportPlayHistory extends Command
{
    protected $signature = 'import:play-history';

    protected $description = 'Import play_history from old DB into new play_history (show_instance_id=null)';

    // Chunk size for bulk inserts
    private const CHUNK = 500;

    public function handle(): int
    {
        if (! DB::table('import_id_maps')->where('entity_type', 'track')->exists()) {
            $this->error('No track mappings found. Run import:tracks first.');

            return self::FAILURE;
        }

        $old = DB::connection('pgsql_old');

        $total = $old->table('play_history')->count();
        $this->info("Found {$total} play history rows to import.");

        $inserted = 0;
        $skipped  = 0;
        $chunk    = [];

        $old->table('play_history')->orderBy('id')->each(function ($row) use (&$inserted, &$skipped, &$chunk) {
            $trackId = ImportIdMap::get('track', (string) $row->song_id);

            if (! $trackId) {
                $skipped++;

                return;
            }

            $chunk[] = [
                'track_id'         => $trackId,
                'show_instance_id' => null,
                'played_at'        => $row->played_at,
            ];

            if (count($chunk) >= self::CHUNK) {
                DB::table('play_history')->insert($chunk);
                $inserted += count($chunk);
                $chunk = [];
                $this->output->write('.');
            }
        });

        // Insert any remaining rows
        if (! empty($chunk)) {
            DB::table('play_history')->insert($chunk);
            $inserted += count($chunk);
        }

        $this->newLine();
        $this->info("Done. inserted={$inserted}, skipped={$skipped} (no track mapping)");

        return self::SUCCESS;
    }
}
