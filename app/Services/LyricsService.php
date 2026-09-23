<?php

namespace App\Services;

use App\Models\Song;
use App\Models\SongTag;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LyricsService
{
    private const LRCLIB_URL  = 'https://lrclib.net/api/get';
    private const OVHLYRICS_URL = 'https://api.lyrics.ovh/v1';

    /** Stop retrying a song after this many failed lookup attempts and log it for review. */
    public const MAX_ATTEMPTS = 3;

    /**
     * Fetch and store lyrics for a song.
     *
     * Gating is on `lyrics` + `lyrics_attempts`, not a one-shot fetched flag:
     * `lyrics === null` means "not resolved yet, eligible for (re)try" up to
     * MAX_ATTEMPTS; an empty string means "confirmed no lyrics expected"
     * (instrumental, no resolvable artist) and is never retried. This lets a
     * song that failed an earlier attempt (API down, bad search terms,
     * since-fixed title) get picked up again automatically without an
     * explicit reset, while capping how many times a genuinely unfindable
     * song gets re-queried instead of hammering the same failure nightly.
     *
     * Artist name resolution:
     *   - For covers (original_artist_id set): use the original artist's name
     *     since lyrics are more commonly indexed under the originator.
     *   - Otherwise: use the first track's release artist.
     */
    public function fetchForSong(Song $song): void
    {
        if ($song->lyrics !== null || $song->lyrics_attempts >= self::MAX_ATTEMPTS) {
            return;
        }

        $skipTags = [SongTag::INSTRUMENTAL, SongTag::INTRO_OUTRO];
        if ($song->tags()->whereIn('tag', $skipTags)->exists()) {
            $song->lyrics = '';
            $song->save();
            return;
        }

        $artistName = $this->resolveArtistName($song);

        if (! $artistName) {
            Log::warning("LyricsService: no artist name for song {$song->id} \"{$song->title}\", skipping");
            $song->lyrics = '';
            $song->save();
            return;
        }

        $trackPosition = $song->tracks->first()?->position;
        $searchTitle   = $this->sanitizeTitle($song->title, $trackPosition);
        $searchArtist = $this->sanitize($artistName);

        $lyrics = $this->fetchFromLrclib($searchArtist, $searchTitle, $song)
            ?? $this->fetchFromOvh($searchArtist, $searchTitle, $song);

        $song->lyrics_attempts++;
        $song->lyrics = $lyrics ?: null;
        $song->save();

        if ($lyrics) {
            Log::info("LyricsService: fetched lyrics for \"{$song->title}\" by {$artistName}");
            return;
        }

        if ($song->lyrics_attempts >= self::MAX_ATTEMPTS) {
            Log::warning("LyricsService: giving up on song {$song->id} \"{$song->title}\" by {$artistName} after {$song->lyrics_attempts} failed attempts — no lyrics found via lrclib or lyrics.ovh, needs manual review");
        } else {
            Log::info("LyricsService: no lyrics found for \"{$song->title}\" by {$artistName} (attempt {$song->lyrics_attempts}/" . self::MAX_ATTEMPTS . ')');
        }
    }

    private function fetchFromLrclib(string $artist, string $title, Song $song): ?string
    {
        try {
            $params = ['artist_name' => $artist, 'track_name' => $title];

            $track = $song->tracks->first();
            if ($track?->duration_seconds) {
                $params['duration'] = $track->duration_seconds;
            }

            $response = Http::timeout(10)->get(self::LRCLIB_URL, $params);

            if ($response->successful()) {
                return trim($response->json('plainLyrics') ?? '') ?: null;
            }
        } catch (\Throwable $e) {
            Log::debug("LyricsService: lrclib failed for \"{$song->title}\" — {$e->getMessage()}");
        }

        return null;
    }

    private function fetchFromOvh(string $artist, string $title, Song $song): ?string
    {
        try {
            $url      = self::OVHLYRICS_URL . '/' . rawurlencode($artist) . '/' . rawurlencode($title);
            $response = Http::timeout(10)->get($url);

            if ($response->successful()) {
                return trim($response->json('lyrics') ?? '') ?: null;
            }

            Log::debug("LyricsService: ovh {$response->status()} for \"{$song->title}\"");
        } catch (\Throwable $e) {
            Log::debug("LyricsService: ovh failed for \"{$song->title}\" — {$e->getMessage()}");
        }

        return null;
    }

    private function resolveArtistName(Song $song): ?string
    {
        if ($song->original_artist_id) {
            $song->loadMissing('originalArtist');
            return $song->originalArtist?->name;
        }

        $song->loadMissing('tracks.release.artist');
        return $song->tracks->first()?->release?->artist?->name;
    }

    private function sanitizeTitle(string $title, ?int $trackPosition = null): string
    {
        // Strip a leading track number ("08 Title", "12 - Title"), but only when the
        // leading digits actually match this track's real position — real song titles
        // can start with a number too ("24 Hours Ago", "7 Days to the Wolves"), so
        // matching on digit-shape alone would wrongly mangle those searches.
        if ($trackPosition !== null) {
            $variants = array_unique([(string) $trackPosition, sprintf('%02d', $trackPosition)]);
            usort($variants, fn($a, $b) => strlen($b) <=> strlen($a));
            $pattern = '/^(' . implode('|', array_map('preg_quote', $variants)) . ')[\s.\-]+/';
            $title   = preg_replace($pattern, '', $title);
        }

        // Strip parenthetical and dash subtitles — search works better without them
        // "Death Of A Dream (The Embrace That Smothers, part VII)" → "Death Of A Dream"
        // "La'petach chatat rovetz – The Last Embrace" → "La'petach chatat rovetz"
        $title = preg_replace('/\s*[\(\[].*/', '', $title);
        $title = preg_replace('/\s*[–—-]\s+.*/', '', $title);

        return $this->sanitize($title);
    }

    private function sanitize(string $value): string
    {
        $sanitized = preg_replace('/[^\w\s\-\/]/u', '', $value);

        // preg_replace returns null instead of throwing when it hits invalid UTF-8
        // under the /u modifier — fall back to a byte-safe (non-Unicode) pass rather
        // than crash; this string is only ever used as a search query, not displayed.
        return $sanitized ?? (preg_replace('/[^\w\s\-\/]/', '', $value) ?? '');
    }
}
