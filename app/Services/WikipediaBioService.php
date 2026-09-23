<?php

namespace App\Services;

use App\Models\Artist;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WikipediaBioService
{
    private const SEARCH_URL  = 'https://en.wikipedia.org/w/api.php';
    private const EXTRACT_URL = 'https://en.wikipedia.org/w/api.php';

    /**
     * Fetch the Wikipedia extract for an artist and use it as a factual `bio`
     * when one isn't already set. Updates the artist record.
     * Marks bio_fetched = true regardless of outcome so we don't retry endlessly.
     */
    public function fetchForArtist(Artist $artist): void
    {
        if ($artist->bio_fetched) {
            return;
        }

        try {
            $title = $this->searchTitle($artist->name);

            if (! $title) {
                Log::info("WikipediaBioService: no Wikipedia page found for \"{$artist->name}\"");
                $artist->update(['bio_fetched' => true]);
                return;
            }

            $extract = $this->fetchExtract($title);

            if (! $extract) {
                Log::info("WikipediaBioService: empty extract for \"{$artist->name}\" (page: {$title})");
                $artist->update(['bio_fetched' => true]);
                return;
            }

            $updates = ['bio_fetched' => true];

            // Only fill bio if it's missing — trim the extract to a reasonable length
            if (empty($artist->bio)) {
                $updates['bio'] = mb_strlen($extract) > 1000
                    ? mb_substr($extract, 0, 1000) . '…'
                    : $extract;
            }

            $artist->update($updates);

            $fields = array_keys(array_diff_key($updates, ['bio_fetched' => true]));
            Log::info("WikipediaBioService: updated \"{$artist->name}\" from Wikipedia"
                . ($fields ? ' (' . implode(', ', $fields) . ')' : ' (no new fields)'));

        } catch (\Throwable $e) {
            Log::warning("WikipediaBioService: failed for \"{$artist->name}\" — {$e->getMessage()}");
            // Don't mark fetched on exception — allow retry
        }
    }

    /**
     * Search Wikipedia for the best-matching page title.
     * Appends "band" to improve disambiguation toward music artists.
     */
    private function searchTitle(string $artistName): ?string
    {
        $response = Http::timeout(10)->get(self::SEARCH_URL, [
            'action'  => 'query',
            'list'    => 'search',
            'srsearch' => "{$artistName} band",
            'format'  => 'json',
            'utf8'    => 1,
            'srlimit' => 3,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $results = $response->json('query.search') ?? [];

        if (empty($results)) {
            return null;
        }

        // Prefer an exact name match (case-insensitive) in the first few results
        foreach ($results as $result) {
            $pageTitle = $result['title'] ?? '';
            if (strcasecmp($pageTitle, $artistName) === 0) {
                return $pageTitle;
            }
        }

        // Fall back to the top result
        return $results[0]['title'] ?? null;
    }

    /**
     * Fetch the intro (lead section) text of a Wikipedia page.
     */
    private function fetchExtract(string $title): ?string
    {
        $response = Http::timeout(10)->get(self::EXTRACT_URL, [
            'action'      => 'query',
            'titles'      => $title,
            'prop'        => 'extracts',
            'exintro'     => true,
            'explaintext' => true,
            'format'      => 'json',
            'redirects'   => 1,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $pages = $response->json('query.pages') ?? [];
        $page  = reset($pages);

        $extract = $page['extract'] ?? null;

        // A missing/disambiguation page returns a very short extract or none
        if (! $extract || mb_strlen($extract) < 100) {
            return null;
        }

        return $extract;
    }
}
