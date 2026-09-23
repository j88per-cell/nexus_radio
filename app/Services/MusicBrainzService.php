<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class MusicBrainzService
{
    private const BASE_URL = 'https://musicbrainz.org/ws/2';
    private const USER_AGENT = 'nexus-radio/1.0 (https://github.com/j88per-cell/nexus_radio)';

    /**
     * MB's real limit is ~1 req/sec, but 1.1s still produced real 503s the
     * night this was built (2026-08-08) — likely load-dependent on MB's own
     * side, not a fixed number that's simply "correct" once found. Lives in
     * config (radio.musicbrainz.min_gap / MB_MIN_GAP env) rather than a
     * hardcoded constant specifically so it can be tuned live without a
     * deploy, matching how MB_STAGGER already works — this got adjusted
     * several times in one evening chasing MB's actual real-world behavior.
     */
    private function minRequestGapSeconds(): float
    {
        return (float) config('radio.musicbrainz.min_gap', 1.5);
    }

    /**
     * Cross-process pacing, not just per-dispatch stagger. Two queue workers
     * pacing themselves independently (each only aware of its own last call)
     * is exactly how bursts happen despite a "safe-looking" stagger between
     * dispatches — confirmed live (2026-08-08): even a 4-6s dispatch stagger
     * still produced real 429/503s once 2 workers were pulling jobs
     * concurrently, especially on multi-track songs firing several sequential
     * MB calls within one job with no internal pacing at all.
     *
     * Uses Laravel's RateLimiter (Redis-backed, same store the queue already
     * uses) rather than a hand-rolled Cache::lock()+get/put dance — same
     * cross-process coordination, but on the framework's own atomic
     * increment/decay primitive instead of a manually-locked read-then-write
     * sequence. tooManyAttempts()/hit() reject rather than wait by default,
     * so this wraps that into "block until it's our turn" — the actual
     * semantics we need.
     */
    private function throttle(): void
    {
        while (RateLimiter::tooManyAttempts('musicbrainz-fetch', 1)) {
            usleep((int) (RateLimiter::availableIn('musicbrainz-fetch') * 1_000_000) ?: 100_000);
        }

        RateLimiter::hit('musicbrainz-fetch', (int) ceil($this->minRequestGapSeconds()));
    }

    private function get(string $endpoint, array $params = []): ?array
    {
        $this->throttle();

        $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
            ->timeout(15)
            ->get(self::BASE_URL . $endpoint, array_merge(['fmt' => 'json'], $params));

        if ($response->successful()) {
            return $response->json();
        }

        if ($response->status() === 429 || $response->status() === 503) {
            throw new \RuntimeException("MusicBrainz rate limited ({$response->status()}) for {$endpoint}");
        }

        Log::warning("MusicBrainzService: {$response->status()} for {$endpoint}");

        return null;
    }

    /**
     * Search for an artist by name, return the best match.
     */
    public function searchArtist(string $name): ?array
    {
        $data    = $this->get('/artist', ['query' => 'artist:' . $name, 'limit' => 10]);
        $artists = $data['artists'] ?? [];

        if (empty($artists)) {
            return null;
        }

        // Prefer exact name match (case-insensitive)
        foreach ($artists as $artist) {
            if (strcasecmp($artist['name'] ?? '', $name) === 0) {
                return $artist;
            }
        }

        // No exact match — log what we got for debugging, return null rather than wrong result
        Log::info("MusicBrainz: no exact match for \"{$name}\", candidates: " .
            collect($artists)->pluck('name')->join(', '));

        return null;
    }

    /**
     * Search for a person by name, return exact match only.
     * Filters to type:Person to avoid matching bands with the same name.
     */
    public function searchPerson(string $name): ?array
    {
        $data    = $this->get('/artist', ['query' => 'artist:"' . addslashes($name) . '" AND type:Person', 'limit' => 5]);
        $artists = $data['artists'] ?? [];

        foreach ($artists as $artist) {
            if (strcasecmp($artist['name'] ?? '', $name) === 0) {
                return $artist;
            }
        }

        return null;
    }

    /**
     * Fetch full artist detail by MBID, including member relations.
     */
    public function getArtist(string $mbid): ?array
    {
        return $this->get("/artist/{$mbid}", [
            'inc' => 'artist-rels+url-rels',
        ]);
    }

    /**
     * Fetch a recording directly by MBID.
     */
    public function getRecording(string $mbid): ?array
    {
        // releases+release-groups needed for detectSongTags() to check
        // release-group secondary-types (MB's actual "Live"/"Compilation"/
        // "Remix" classification), not just guess from title text.
        return $this->get("/recording/{$mbid}", ['inc' => 'releases+release-groups+tags']);
    }

    /**
     * Search for a recording by title and artist, return the best match.
     */
    public function searchRecording(string $title, string $artist): ?array
    {
        $data = $this->get('/recording', [
            'query' => 'recording:"' . addslashes($title) . '" AND artist:"' . addslashes($artist) . '" AND video:false',
            'limit' => 5,
        ]);

        // Pick the highest-score non-video result then fetch full detail with tags
        foreach ($data['recordings'] ?? [] as $recording) {
            if (! empty($recording['video'])) {
                continue;
            }

            return $this->getRecording($recording['id']);
        }

        return null;
    }

    /** MB's own release-group secondary-type strings -> our tag vocabulary. */
    private const SECONDARY_TYPE_TAGS = [
        'live'         => 'live',
        'compilation'  => 'compilation',
        'remix'        => 'remix',
        'soundtrack'   => 'soundtrack',
        'dj-mix'       => 'remix',
        'demo'         => 'demo',
    ];

    private const KEYWORD_PATTERNS = [
        'live'         => ['live', 'concert', 'in concert'],
        'acoustic'     => ['acoustic', 'unplugged'],
        'instrumental' => ['instrumental', 'karaoke'],
        'remix'        => ['remix', 'remixed', 'edit'],
        'bonus'        => ['bonus track', 'hidden track'],
    ];

    /**
     * Detect tags for a recording. Two signals, in order of trust:
     *
     * 1. MB's own release-group secondary-types (e.g. a release's
     *    release-group is explicitly typed "Live" or "Compilation" by MB's
     *    own editors) — this is real classification data, not a guess, and
     *    catches cases a title never spells out (an album called "Wembley
     *    1987" with a plain-titled tracklist, no "(Live)" anywhere).
     * 2. Keyword matching against the recording title, disambiguation, and
     *    each associated release's title — the original fallback, kept for
     *    when MB's release-group typing is itself incomplete (common for
     *    less mainstream releases).
     *
     * Needs getRecording() to have fetched with inc=releases+release-groups
     * for signal 1 to have anything to look at — degrades gracefully to
     * keyword-only if that data isn't present (e.g. a stale cached response).
     */
    public function detectSongTags(array $recording): array
    {
        $tags = [];

        foreach ($recording['releases'] ?? [] as $release) {
            foreach ($release['release-group']['secondary-types'] ?? [] as $secondaryType) {
                $key = strtolower($secondaryType);
                if (isset(self::SECONDARY_TYPE_TAGS[$key])) {
                    $tags[] = self::SECONDARY_TYPE_TAGS[$key];
                }
            }
        }

        $haystacks = [
            strtolower($recording['title'] ?? ''),
            strtolower($recording['disambiguation'] ?? ''),
        ];
        foreach ($recording['releases'] ?? [] as $release) {
            $haystacks[] = strtolower($release['title'] ?? '');
        }

        $tags = array_merge($tags, $this->matchKeywordTags($haystacks));

        // MusicBrainz community folksonomy tags — lower confidence than the
        // structured signals above (anyone can apply these), kept as a
        // last-resort catch for whatever the other two signals both miss.
        foreach ($recording['tags'] ?? [] as $mbTag) {
            $name = strtolower($mbTag['name'] ?? '');
            if (str_contains($name, 'instrumental')) $tags[] = 'instrumental';
            if (str_contains($name, 'live'))         $tags[] = 'live';
            if (str_contains($name, 'acoustic'))     $tags[] = 'acoustic';
            if (str_contains($name, 'remix'))        $tags[] = 'remix';
        }

        return array_values(array_unique($tags));
    }

    /**
     * Local-only fallback for when MB has no match at all (searchRecording()
     * returns null) — real case (2026-08-08): tracks whose own titles
     * literally say "(live) BONUS TRACK" still got zero tags because nothing
     * downstream ever runs without a successful MB match first. When the
     * title already spells it out, no external lookup is needed to know
     * that. Same keyword vocabulary as detectSongTags(), just applied
     * directly to whatever local title text is on hand instead of MB's.
     *
     * @param string ...$texts any local text worth scanning — track title,
     *   song title, release title, etc.
     */
    public function detectTagsFromText(string ...$texts): array
    {
        return $this->matchKeywordTags(array_map('mb_strtolower', $texts));
    }

    /** @param string[] $haystacks already-lowercased text to scan */
    private function matchKeywordTags(array $haystacks): array
    {
        $tags = [];
        foreach (self::KEYWORD_PATTERNS as $tag => $keywords) {
            foreach ($keywords as $keyword) {
                foreach ($haystacks as $haystack) {
                    if (str_contains($haystack, $keyword)) {
                        $tags[] = $tag;
                        continue 3;
                    }
                }
            }
        }
        return $tags;
    }
}
