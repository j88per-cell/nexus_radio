<?php

namespace App\Console\Commands\Music;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncBeetsMbIds extends Command
{
    protected $signature = 'music:sync-beets-mb-ids
                            {--dry-run : Show matches without writing to DB}';

    protected $description = 'Populate tracks.mb_recording_id from beets mb_trackid, matched by title + artist + album.';

    private const SEPARATOR = '|||';

    public function handle(): int
    {
        $this->info('Loading MB IDs from beets...');

        $beetsItems = $this->loadFromBeets();

        if (empty($beetsItems)) {
            $this->error('No tracks returned from beet.');
            return self::FAILURE;
        }

        $withMbId = array_filter($beetsItems, fn($i) => ! empty($i['mb_trackid']));

        $this->info(sprintf(
            '%d beets tracks loaded, %d have MB IDs.',
            count($beetsItems),
            count($withMbId),
        ));

        // Build lookup: normalised "title|||artist|||album" => mb_trackid
        $beetsMap = [];
        foreach ($withMbId as $item) {
            $key = $this->key($item['title'], $item['artist'], $item['album']);
            // Keep first match if duplicates exist
            $beetsMap[$key] ??= $item['mb_trackid'];
        }

        $this->info('Scanning tracks in DB missing mb_recording_id...');

        $tracks = DB::table('tracks')
            ->join('songs', 'tracks.song_id', '=', 'songs.id')
            ->join('releases', 'tracks.release_id', '=', 'releases.id')
            ->join('artists', 'releases.artist_id', '=', 'artists.id')
            ->whereNull('tracks.mb_recording_id')
            ->whereNull('tracks.deleted_at')
            ->select(
                'tracks.id',
                'songs.title as song_title',
                'artists.name as artist_name',
                'releases.title as release_title',
            )
            ->get();

        $this->info("{$tracks->count()} tracks without MB recording ID.");
        $this->newLine();

        $matched   = 0;
        $ambiguous = 0;

        foreach ($tracks as $track) {
            $key   = $this->key($track->song_title, $track->artist_name, $track->release_title);
            $mbId  = $beetsMap[$key] ?? null;

            if (! $mbId) {
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("  [{$track->id}] {$track->artist_name} — {$track->song_title} → {$mbId}");
            } else {
                DB::table('tracks')->where('id', $track->id)->update(['mb_recording_id' => $mbId]);
            }

            $matched++;
        }

        $this->newLine();

        if ($this->option('dry-run')) {
            $this->info("{$matched} tracks would be updated (dry run).");
        } else {
            $this->info("{$matched} tracks updated with MB recording IDs.");
        }

        if ($matched === 0) {
            $this->warn('No matches found. Titles/artists/albums may differ between beets and DB.');
        }

        return self::SUCCESS;
    }

    private function loadFromBeets(): array
    {
        $sep = self::SEPARATOR;
        $fmt = escapeshellarg("\$mb_trackid{$sep}\$title{$sep}\$artist{$sep}\$album");
        $output = shell_exec("beet ls -f {$fmt} 2>/dev/null");

        if (! $output) {
            return [];
        }

        $items = [];
        foreach (explode("\n", trim($output)) as $line) {
            $parts = explode($sep, $line);
            if (count($parts) !== 4) {
                continue;
            }

            [$mbId, $title, $artist, $album] = array_map('trim', $parts);

            $items[] = [
                'mb_trackid' => $mbId,
                'title'      => $title,
                'artist'     => $artist,
                'album'      => $album,
            ];
        }

        return $items;
    }

    private function key(string $title, string $artist, string $album): string
    {
        return implode('|||', [
            Str::lower(trim($title)),
            Str::lower(trim($artist)),
            Str::lower(trim($album)),
        ]);
    }
}
