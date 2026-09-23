<?php

namespace App\Console\Commands\Music;

use App\Models\Track;
use Illuminate\Console\Command;

/**
 * Read-only health check: does tracks.file_path still point at a real file?
 * Distinct from music:backfill-file-paths, which tries to re-resolve a path
 * via beets/Navidrome — this just asks "is what's already there still true,"
 * cheap enough to run regularly (no beets shell-out, no Navidrome HTTP
 * calls) as an ongoing check that a path recorded as good hasn't gone stale
 * (file moved/renamed/deleted after being confirmed).
 */
class CheckFilePaths extends Command
{
    protected $signature = 'music:check-file-paths
                            {--fix : Clear file_path/path_confirmed_at for anything that fails}
                            {--output= : Optional path to write a CSV of failures}';

    protected $description = 'Report tracks whose file_path no longer points at a real file on disk';

    public function handle(): int
    {
        $fix        = (bool) $this->option('fix');
        $outputPath = $this->option('output');

        $tracks = Track::query()
            ->whereNotNull('file_path')
            ->with('song', 'release.artist')
            ->get();

        if ($tracks->isEmpty()) {
            $this->info('No tracks have a file_path set.');
            return self::SUCCESS;
        }

        $this->info("Checking {$tracks->count()} tracks...");

        $bad = [];

        foreach ($tracks as $track) {
            if (file_exists($track->file_path)) {
                continue;
            }

            $bad[] = [
                $track->id,
                $track->song->title ?? null,
                $track->release->artist->name ?? null,
                $track->file_path,
                $track->path_confirmed_at?->toDateTimeString() ?? 'never confirmed',
            ];

            if ($fix) {
                $track->update(['file_path' => null, 'path_confirmed_at' => null]);
            }
        }

        $this->newLine();
        $this->line(sprintf(
            '%d checked, %d bad%s.',
            $tracks->count(),
            count($bad),
            $fix ? ' (cleared)' : ''
        ));

        if (! empty($bad)) {
            $this->table(['ID', 'Title', 'Artist', 'file_path', 'Last confirmed'], $bad);
        }

        if ($outputPath && ! empty($bad)) {
            $handle = fopen($outputPath, 'w');
            fputcsv($handle, ['id', 'title', 'artist', 'file_path', 'last_confirmed']);
            foreach ($bad as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
            $this->info("Failures written to: {$outputPath}");
        }

        return self::SUCCESS;
    }
}
