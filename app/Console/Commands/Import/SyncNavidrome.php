<?php

namespace App\Console\Commands\Import;

use App\Models\Artist;
use App\Models\Genre;
use App\Models\Release;
use App\Models\Song;
use App\Models\Track;
use App\Services\NavidromeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncNavidrome extends Command
{
    protected $signature = 'import:navidrome
                            {--new-only : Skip artists/releases/songs that already exist, only add new tracks}
                            {--artist= : Only sync tracks for this artist name (partial match)}';

    protected $description = 'Sync new artists, releases, songs, and tracks from Navidrome';

    private int $artistsCreated  = 0;
    private int $releasesCreated = 0;
    private int $songsCreated    = 0;
    private int $tracksCreated   = 0;
    private int $tracksUpdated   = 0;

    public function handle(NavidromeService $navidrome): int
    {
        $newOnly      = $this->option('new-only');
        $artistFilter = $this->option('artist');
        $this->info('Fetching all songs from Navidrome…' . ($newOnly ? ' (new only)' : ''));
        $songs = $navidrome->getAllSongs();
        $this->info('Found ' . count($songs) . ' tracks.');

        if ($artistFilter) {
            $songs = array_filter($songs, fn($s) => stripos($s['artist'], $artistFilter) !== false);
            $this->info('Filtered to ' . count($songs) . ' tracks matching "' . $artistFilter . '".');
        }

        if (empty($songs)) {
            $this->warn('No songs returned from Navidrome.');
            return self::SUCCESS;
        }

        // Group songs by artistId then albumId for efficient upsert ordering
        $byArtist = collect($songs)->groupBy('artistId');

        foreach ($byArtist as $naviArtistId => $artistSongs) {
            DB::transaction(function () use ($naviArtistId, $artistSongs, $newOnly) {
                $firstSong = $artistSongs->first();
                $artist    = $this->upsertArtist($naviArtistId, $firstSong['artist'], $newOnly);

                if (! $artist) {
                    return; // soft-deleted — skip intentionally
                }

                $byAlbum = $artistSongs->groupBy('albumId');

                foreach ($byAlbum as $naviAlbumId => $albumSongs) {
                    $firstTrack = $albumSongs->first();
                    $release    = $this->upsertRelease($naviAlbumId, $firstTrack, $artist);

                    foreach ($albumSongs as $naviSong) {
                        $song  = $this->upsertSong($naviSong, $artist);
                        $this->upsertTrack($naviSong, $release, $song);
                    }
                }
            });
        }

        $this->info("Artists created:  {$this->artistsCreated}");
        $this->info("Releases created: {$this->releasesCreated}");
        $this->info("Songs created:    {$this->songsCreated}");
        $this->info("Tracks created:   {$this->tracksCreated}");
        $this->info("Tracks updated:   {$this->tracksUpdated}");

        return self::SUCCESS;
    }

    private function upsertArtist(string $naviArtistId, string $name, bool $newOnly = false): ?Artist
    {
        // Check including soft-deleted so we can skip intentionally removed artists
        $artist = Artist::withTrashed()->where('navidrome_artist_id', $naviArtistId)->first();

        if ($artist) {
            return $artist->trashed() ? null : $artist;
        }

        // Fall back to name match for artists imported before this column existed
        $artist = Artist::withTrashed()->where('name', $name)->first();

        if ($artist) {
            if ($artist->trashed()) return null;
            $artist->update(['navidrome_artist_id' => $naviArtistId]);
            return $artist;
        }

        $this->artistsCreated++;

        return Artist::create([
            'name'                => $name,
            'slug'                => $this->uniqueSlug('artists', $name),
            'navidrome_artist_id' => $naviArtistId,
            'type'                => 'band',
        ]);
    }

    private function upsertRelease(string $naviAlbumId, array $song, Artist $artist): Release
    {
        $release = Release::where('navidrome_album_id', $naviAlbumId)->first();

        if (! $release) {
            $this->releasesCreated++;

            $release = Release::create([
                'artist_id'          => $artist->id,
                'title'              => $song['album'] ?? 'Unknown Album',
                'slug'               => $this->uniqueSlug('releases', ($artist->name . ' ' . ($song['album'] ?? ''))),
                'type'               => 'album',
                'release_date'       => ! empty($song['year']) ? $song['year'] . '-01-01' : null,
                'navidrome_album_id' => $naviAlbumId,
            ]);
        }

        // Backfill genre from Navidrome whenever we don't have one yet — covers
        // releases that were imported before genre tagging existed, or before
        // Navidrome/Beets had the tag set, without needing a separate one-off
        // backfill command every time tagging catches up.
        if (! empty($song['genre']) && ! $release->genres()->exists()) {
            $genre = $this->firstOrCreateGenre($song['genre']);
            $release->genres()->syncWithoutDetaching([$genre->id]);
        }

        return $release;
    }

    private function upsertSong(array $naviSong, Artist $artist): Song
    {
        // Match by title + artist to avoid duplicates across covers/compilations
        $song = Song::where('original_artist_id', $artist->id)
            ->where('title', $naviSong['title'])
            ->first();

        if ($song) {
            return $song;
        }

        $this->songsCreated++;

        return Song::create([
            'title'              => $naviSong['title'],
            'original_artist_id' => $artist->id,
        ]);
    }

    private function upsertTrack(array $naviSong, Release $release, Song $song): void
    {
        $existing = Track::where('navidrome_track_id', $naviSong['id'])->first();

        if ($existing) {
            // Keep duration and MB recording ID in sync in case Navidrome metadata changed
            $duration      = isset($naviSong['duration']) ? (int) round((float) $naviSong['duration']) : null;
            $mbRecordingId = ($naviSong['musicBrainzId'] ?? '') ?: null;
            $updates       = [];
            if ($duration && $existing->duration_seconds !== $duration) {
                $updates['duration_seconds'] = $duration;
            }
            if ($mbRecordingId && $existing->mb_recording_id !== $mbRecordingId) {
                $updates['mb_recording_id'] = $mbRecordingId;
            }
            if ($updates) {
                $existing->update($updates);
                $this->tracksUpdated++;
            }
            return;
        }

        $this->tracksCreated++;

        Track::create([
            'release_id'         => $release->id,
            'song_id'            => $song->id,
            'position'           => $naviSong['track']      ?? null,
            'disc'               => $naviSong['discNumber'] ?? 1,
            'duration_seconds'   => isset($naviSong['duration']) ? (int) round((float) $naviSong['duration']) : null,
            'navidrome_track_id' => $naviSong['id'],
            'mb_recording_id'    => ($naviSong['musicBrainzId'] ?? '') ?: null,
        ]);
    }

    private function uniqueSlug(string $table, string $name): string
    {
        $base = Str::slug($name) ?: 'item';

        if (! DB::table($table)->where('slug', $base)->exists()) {
            return $base;
        }

        $i = 2;
        while (DB::table($table)->where('slug', $base . '-' . $i)->exists()) {
            $i++;
        }

        return $base . '-' . $i;
    }

    private function firstOrCreateGenre(string $name): Genre
    {
        $slug = Str::slug($name) ?: 'genre-' . Str::random(4);

        // Match case-insensitively by name, or by the slug we'd generate. Navidrome/Beets
        // send inconsistent casing for the same genre (e.g. "symphonic metal" vs the
        // "Symphonic Metal" already on file from an earlier import) — firstOrCreate's
        // exact-name lookup misses that and tries to insert a second row, which then
        // fails on genres_slug_unique since Str::slug() normalizes both to the same slug.
        $genre = Genre::whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->orWhere('slug', $slug)
            ->first();

        return $genre ?? Genre::create(['name' => $name, 'slug' => $slug]);
    }
}
