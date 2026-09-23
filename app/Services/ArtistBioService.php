<?php

namespace App\Services;

use App\Models\Artist;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ArtistBioService
{
    private const BASE_URL = 'https://www.theaudiodb.com/api/v1/json/2/search.php';

    /**
     * Fetch and store bio data for an artist from TheAudioDB.
     * Marks bio_fetched = true regardless of outcome so we never retry.
     * Does NOT overwrite existing bio/origin/formed_year if already set.
     */
    public function fetchForArtist(Artist $artist): void
    {
        if ($artist->bio_fetched) {
            return;
        }

        $searchName = $artist->name;

        try {
            $response = Http::timeout(10)->get(self::BASE_URL, ['s' => $searchName]);

            if ($response->successful()) {
                $data = $response->json('artists.0') ?? null;

                $updates = ['bio_fetched' => true];

                if ($data) {
                    if (empty($artist->bio) && ! empty($data['strBiographyEN'])) {
                        $updates['bio'] = trim($data['strBiographyEN']);
                    }
                    if (empty($artist->origin) && ! empty($data['strCountry'])) {
                        $updates['origin'] = $data['strCountry'];
                    }
                    if (empty($artist->formed_year) && ! empty($data['intFormedYear'])) {
                        $updates['formed_year'] = (int) $data['intFormedYear'];
                    }

                    Log::info("ArtistBioService: fetched data for \"{$artist->name}\"" . (isset($updates['bio']) ? ' (bio)' : ' (no bio)'));
                } else {
                    Log::info("ArtistBioService: no results for \"{$artist->name}\" ({$response->status()})");
                }

                $artist->fill($updates);
                $artist->save();
            } else {
                Log::info("ArtistBioService: {$response->status()} for \"{$artist->name}\"");
                $artist->bio_fetched = true;
                $artist->save();
            }
        } catch (\Throwable $e) {
            Log::warning("ArtistBioService: fetch failed for \"{$artist->name}\" — {$e->getMessage()}");
            // Don't mark fetched on exception — allow retry
        }
    }
}
