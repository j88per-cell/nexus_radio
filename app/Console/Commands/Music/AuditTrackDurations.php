<?php

namespace App\Console\Commands\Music;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuditTrackDurations extends Command
{
    protected $signature = 'music:audit-durations
                            {--threshold=15 : Percentage shorter than MB official to flag as truncated}
                            {--limit= : Max number of tracks to check (useful for testing)}
                            {--output= : Optional path to write CSV results}
                            {--delete : Soft-delete flagged tracks and store their file paths}
                            {--dry-run : Show results without deleting or writing anything}';

    protected $description = 'Compare stored track durations against MB official durations to find truncated files.';

    private const SEPARATOR = '|||';

    public function handle(): int
    {
        $threshold  = (float) $this->option('threshold') / 100;
        $limit      = $this->option('limit') ? (int) $this->option('limit') : null;
        $outputPath = $this->option('output');
        $delete     = $this->option('delete') && ! $this->option('dry-run');

        $query = DB::table('tracks')
            ->join('songs', 'tracks.song_id', '=', 'songs.id')
            ->join('releases', 'tracks.release_id', '=', 'releases.id')
            ->join('artists', 'releases.artist_id', '=', 'artists.id')
            ->whereNotNull('tracks.navidrome_track_id')
            ->whereNotNull('tracks.duration_seconds')
            ->where('tracks.duration_seconds', '>', 0)
            ->whereNotNull('tracks.mb_duration_seconds')
            ->whereNull('tracks.deleted_at')
            ->select(
                'tracks.id',
                'tracks.duration_seconds',
                'tracks.mb_duration_seconds',
                'tracks.mb_recording_id',
                'songs.title as song_title',
                'artists.name as artist_name',
                'releases.title as release_title',
            );

        if ($limit) {
            $query->limit($limit);
        }

        $tracks = $query->get();

        if ($tracks->isEmpty()) {
            $this->warn('No tracks with mb_duration_seconds found. Run songs:fetch-musicbrainz first.');
            return self::SUCCESS;
        }

        $this->info("Comparing {$tracks->count()} tracks against MB durations...");

        // Load beets paths for any flagged tracks
        $this->info('Loading beets path map...');
        $beetsMap = $this->buildBeetsPathMap();

        $flagged = [];

        foreach ($tracks as $track) {
            $delta = $track->mb_duration_seconds - $track->duration_seconds;
            $pct   = $track->mb_duration_seconds > 0 ? $delta / $track->mb_duration_seconds : 0;

            if ($pct >= $threshold) {
                $key  = $this->key($track->song_title, $track->artist_name, $track->release_title);
                $path = $beetsMap[$key] ?? null;

                $flagged[] = [
                    'id'           => $track->id,
                    'mb_trackid'   => $track->mb_recording_id,
                    'path'         => $path,
                    'song_title'   => $track->song_title,
                    'artist_name'  => $track->artist_name,
                    'stored_sec'   => round($track->duration_seconds, 1),
                    'official_sec' => round($track->mb_duration_seconds, 1),
                    'delta_sec'    => round($delta, 1),
                    'delta_pct'    => round($pct * 100, 1),
                ];
            }
        }

        if (empty($flagged)) {
            $this->info('No truncated tracks found.');
        } else {
            $this->warn(count($flagged) . ' truncated tracks flagged:');
            $this->newLine();
            $this->table(
                ['ID', 'Artist', 'Title', 'Stored', 'Official', 'Short by', '%', 'Path'],
                array_map(fn($f) => [
                    $f['id'],
                    $f['artist_name'],
                    $f['song_title'],
                    $this->fmt($f['stored_sec']),
                    $this->fmt($f['official_sec']),
                    $this->fmt($f['delta_sec']),
                    $f['delta_pct'] . '%',
                    $f['path'] ?? '(no beets match)',
                ], $flagged)
            );
        }

        $this->newLine();
        $this->line(sprintf('%d tracks checked, %d flagged.', $tracks->count(), count($flagged)));

        if ($outputPath && ! empty($flagged) && ! $this->option('dry-run')) {
            $this->writeCsv($outputPath, $flagged);
            $this->info("Results written to: {$outputPath}");
        }

        if ($delete && ! empty($flagged)) {
            $this->softDeleteFlagged($flagged);
        }

        return self::SUCCESS;
    }

    private function buildBeetsPathMap(): array
    {
        $sep    = self::SEPARATOR;
        $fmt    = escapeshellarg("\$title{$sep}\$artist{$sep}\$album{$sep}\$path");
        $output = shell_exec("beet ls -f {$fmt} 2>/dev/null");

        if (! $output) {
            return [];
        }

        $map = [];
        foreach (explode("\n", trim($output)) as $line) {
            $parts = explode($sep, $line);
            if (count($parts) !== 4) {
                continue;
            }

            [$title, $artist, $album, $path] = array_map('trim', $parts);
            $map[$this->key($title, $artist, $album)] ??= $path;
        }

        return $map;
    }

    private function softDeleteFlagged(array $flagged): void
    {
        $now     = now();
        $deleted = 0;

        foreach ($flagged as $f) {
            $updated = DB::table('tracks')
                ->where('id', $f['id'])
                ->whereNull('deleted_at')
                ->update([
                    'deleted_at' => $now,
                    'file_path'  => $f['path'],
                ]);

            $deleted += $updated;
        }

        $this->info("{$deleted} tracks soft-deleted in DB.");

        if ($deleted < count($flagged)) {
            $this->warn(
                (count($flagged) - $deleted) . ' flagged tracks could not be soft-deleted — check output.'
            );
        }
    }

    private function key(string $title, string $artist, string $album): string
    {
        return implode('|||', [
            Str::lower(trim($title)),
            Str::lower(trim($artist)),
            Str::lower(trim($album)),
        ]);
    }

    private function fmt(float $seconds): string
    {
        $m = (int) floor($seconds / 60);
        $s = (int) round(fmod($seconds, 60));
        return sprintf('%d:%02d', $m, $s);
    }

    private function writeCsv(string $path, array $rows): void
    {
        $handle = fopen($path, 'w');
        fputcsv($handle, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
    }
}
