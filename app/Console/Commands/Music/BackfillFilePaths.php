<?php

namespace App\Console\Commands\Music;

use App\Models\Track;
use App\Services\NavidromeService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Backfills tracks.file_path, primarily from beets, matched by (title,
 * artist, album) — same lookup AuditTrackDurations already does for its
 * flagged subset, just generalized to the whole library. Navidrome resolves
 * this same real filesystem path internally to serve a track, but Laravel
 * never persisted it — this is what lets the radio pipeline read files
 * directly instead of going through Navidrome's HTTP/transcode layer for
 * every play.
 *
 * Anything beets can't match (naming variance between its tags and
 * Laravel's DB — not every track is beets-tagged the same way) falls back
 * to Navidrome's own getSong API, keyed by navidrome_track_id instead of a
 * fuzzy string match — exact by construction, just one HTTP call per track
 * so only worth it for the leftover minority, not the whole library.
 */
class BackfillFilePaths extends Command
{
    protected $signature = 'music:backfill-file-paths
                            {--limit= : Max number of tracks to process (useful for testing)}
                            {--force : Overwrite tracks that already have a file_path}
                            {--no-navidrome : Skip the Navidrome fallback pass, beets only}
                            {--output= : Optional path to write a CSV of still-unmatched tracks}
                            {--dry-run : Show results without writing anything}';

    protected $description = 'Backfill tracks.file_path from beets (primary) and Navidrome (fallback), matched by title/artist/album or navidrome_track_id';

    private const SEPARATOR = '|||';

    public function handle(NavidromeService $navidrome): int
    {
        $limit         = $this->option('limit') ? (int) $this->option('limit') : null;
        $force         = (bool) $this->option('force');
        $dryRun        = (bool) $this->option('dry-run');
        $skipNavidrome = (bool) $this->option('no-navidrome');
        $outputPath    = $this->option('output');
        $musicRoot     = rtrim(config('services.navidrome.music_root'), '/');

        $this->info('Loading beets path map...');
        $beetsMap = $this->buildBeetsPathMap();

        if (empty($beetsMap)) {
            $this->error('beets returned no results — is it installed and configured on this host?');
            return self::FAILURE;
        }
        $this->info(count($beetsMap) . ' paths loaded from beets.');

        $query = Track::query()->with('song', 'release.artist');

        if (! $force) {
            $query->whereNull('file_path');
        }

        if ($limit) {
            $query->limit($limit);
        }

        $tracks = $query->get();

        if ($tracks->isEmpty()) {
            $this->info('Nothing to do.');
            return self::SUCCESS;
        }

        $this->info("Matching {$tracks->count()} tracks against beets...");

        $matchedBeets     = 0;
        $matchedNavidrome = 0;
        $unmatched        = [];
        $needsNavidrome    = [];

        foreach ($tracks as $track) {
            $title  = $track->song->title ?? null;
            $artist = $track->release->artist->name ?? null;
            $album  = $track->release->title ?? null;

            $path = ($title && $artist && $album)
                ? ($beetsMap[$this->key($title, $artist, $album)] ?? null)
                : null;

            if ($path) {
                $resolved = $this->resolveOnDisk($path, $musicRoot);
                if (! $resolved) {
                    $this->clearIfStale($track, $dryRun);
                    $unmatched[] = [$track->id, $title, $artist, $album, "beets match but file missing on disk: {$path}"];
                    continue;
                }
                if (! $dryRun) {
                    $track->update(['file_path' => $resolved, 'path_confirmed_at' => now()]);
                }
                $matchedBeets++;
                continue;
            }

            if ($track->navidrome_track_id) {
                $needsNavidrome[] = $track;
            } else {
                $this->clearIfStale($track, $dryRun);
                $unmatched[] = [$track->id, $title, $artist, $album, 'no beets match, no navidrome_track_id'];
            }
        }

        if (! $skipNavidrome && ! empty($needsNavidrome)) {
            $this->info('Falling back to Navidrome for ' . count($needsNavidrome) . ' tracks beets could not match...');
            $bar = $this->output->createProgressBar(count($needsNavidrome));

            foreach ($needsNavidrome as $track) {
                $song = $navidrome->getSong($track->navidrome_track_id);
                $relativePath = $song['path'] ?? null;

                if ($relativePath) {
                    $path     = $musicRoot . '/' . ltrim($relativePath, '/');
                    $resolved = $this->resolveOnDisk($path, $musicRoot);
                    if (! $resolved) {
                        $this->clearIfStale($track, $dryRun);
                        $unmatched[] = [
                            $track->id,
                            $track->song->title ?? null,
                            $track->release->artist->name ?? null,
                            $track->release->title ?? null,
                            "navidrome match but file missing on disk: {$path}",
                        ];
                    } else {
                        if (! $dryRun) {
                            $track->update(['file_path' => $resolved, 'path_confirmed_at' => now()]);
                        }
                        $matchedNavidrome++;
                    }
                } else {
                    $this->clearIfStale($track, $dryRun);
                    $unmatched[] = [
                        $track->id,
                        $track->song->title ?? null,
                        $track->release->artist->name ?? null,
                        $track->release->title ?? null,
                        'no beets match, navidrome getSong returned nothing',
                    ];
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        } elseif (! empty($needsNavidrome)) {
            // --no-navidrome: still record these as unmatched-for-now rather
            // than silently dropping them from the report. Deliberately does
            // NOT clear file_path here — skipping the Navidrome pass isn't
            // evidence the existing value is wrong, just that this run
            // didn't check it.
            foreach ($needsNavidrome as $track) {
                $unmatched[] = [
                    $track->id,
                    $track->song->title ?? null,
                    $track->release->artist->name ?? null,
                    $track->release->title ?? null,
                    'no beets match (navidrome fallback skipped)',
                ];
            }
        }

        $this->newLine();
        $this->line(sprintf(
            '%d tracks processed, %d matched via beets, %d matched via Navidrome%s, %d unmatched.',
            $tracks->count(),
            $matchedBeets,
            $matchedNavidrome,
            $dryRun ? ' (dry run, nothing written)' : '',
            count($unmatched)
        ));

        if ($outputPath && ! empty($unmatched)) {
            $this->writeCsv($outputPath, $unmatched);
            $this->info("Unmatched tracks written to: {$outputPath}");
        }

        return self::SUCCESS;
    }

    /**
     * A track landing in $unmatched this run may still be holding a
     * file_path written by an earlier, less careful run (before this
     * validate-on-disk logic existed) or one that's since gone stale.
     * Clears it so file_path IS NULL actually means "no confirmed real
     * path" — not "confirmed", not "unknown", genuinely null. Without
     * this a failed validation silently left the old (possibly wrong)
     * value in place, exactly what happened before 2026-08-30.
     */
    private function clearIfStale(Track $track, bool $dryRun): void
    {
        if ($track->file_path !== null && ! $dryRun) {
            $track->update(['file_path' => null, 'path_confirmed_at' => null]);
        }
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

    /**
     * Confirms $path actually exists, falling back to a case-insensitive
     * walk of each path segment below $root if not — ext4 is case-sensitive,
     * but Navidrome's own recorded path (and beets' tags) don't always match
     * the real on-disk casing exactly (e.g. "Live After Death" vs. the real
     * "Live after Death" — confirmed 2026-08-30, Navidrome title-cases from
     * ID3 display metadata rather than preserving the literal directory name
     * it scanned). Returns the real, correctly-cased path if found, null if
     * the file genuinely isn't there under any casing.
     */
    private function resolveOnDisk(string $path, string $root): ?string
    {
        if (file_exists($path)) {
            return $path;
        }

        if (! str_starts_with($path, $root)) {
            return null; // outside the root we know how to walk
        }

        $segments = array_filter(explode('/', substr($path, strlen($root))), fn($s) => $s !== '');
        $current  = $root;

        foreach ($segments as $segment) {
            $exact = $current . '/' . $segment;
            if (file_exists($exact)) {
                $current = $exact;
                continue;
            }

            if (! is_dir($current)) {
                return null;
            }

            $match = null;
            foreach (scandir($current) ?: [] as $entry) {
                if (strcasecmp($entry, $segment) === 0) {
                    $match = $entry;
                    break;
                }
            }

            if (! $match) {
                return null;
            }

            $current = $current . '/' . $match;
        }

        return file_exists($current) ? $current : null;
    }

    private function key(string $title, string $artist, string $album): string
    {
        return implode(self::SEPARATOR, [
            Str::lower(trim($title)),
            Str::lower(trim($artist)),
            Str::lower(trim($album)),
        ]);
    }

    private function writeCsv(string $path, array $rows): void
    {
        $handle = fopen($path, 'w');
        fputcsv($handle, ['id', 'title', 'artist', 'album', 'reason']);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
    }
}
