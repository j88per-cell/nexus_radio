<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NavidromeService
{
    private array $baseParams;
    private string $baseUrl;

    public function __construct()
    {
        $cfg = config('services.navidrome');

        $this->baseUrl = rtrim($cfg['url'], '/') . '/rest';

        $this->baseParams = [
            'u' => $cfg['user'],
            'p' => $cfg['pass'],
            'v' => $cfg['version'] ?? '1.16.1',
            'c' => $cfg['client'] ?? 'nexus-radio',
            'f' => 'json',
        ];
    }

    private function get(string $endpoint, array $params = []): array
    {
        $response = Http::timeout(30)
            ->get("{$this->baseUrl}/{$endpoint}", array_merge($this->baseParams, $params));

        $body = $response->json();

        if (($body['subsonic-response']['status'] ?? '') !== 'ok') {
            $error = $body['subsonic-response']['error'] ?? [];
            throw new \RuntimeException(
                "Navidrome error [{$error['code']}]: {$error['message']}"
            );
        }

        return $body['subsonic-response'];
    }

    /**
     * Build the Liquidsoap-ready stream URL for a track by its Navidrome ID.
     */
    public function streamUrl(string $navidromeId): string
    {
        return $this->baseUrl . '/stream?' . http_build_query(
            array_merge($this->baseParams, [
                'id' => $navidromeId,
                'format' => 'mp3',
                'maxBitRate' => 192,
            ])
        );
    }

    public function getSong(string $navidromeId): ?array
    {
        try {
            $response = $this->get('getSong', ['id' => $navidromeId]);
            return $response['song'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function startScan(): array
    {
        return $this->get('startScan');
    }

    public function getScanStatus(): array
    {
        return $this->get('getScanStatus');
    }

    /**
     * Fetch all albums via getArtists → getArtist, so untagged albums
     * (no genre) are included — getAlbumList2 silently skips them.
     */
    public function getAllAlbums(): array
    {
        $response = $this->get('getArtists');
        $indices  = $response['artists']['index'] ?? [];

        $artistIds = [];
        foreach ($indices as $index) {
            foreach ($index['artist'] ?? [] as $artist) {
                if (isset($artist['id'])) {
                    $artistIds[] = $artist['id'];
                }
            }
        }

        if (empty($artistIds)) {
            return [];
        }

        $albums  = [];
        $chunks  = array_chunk($artistIds, 20);

        foreach ($chunks as $chunk) {
            $urls = array_map(
                fn($id) => "{$this->baseUrl}/getArtist?" . http_build_query(
                    array_merge($this->baseParams, ['id' => $id])
                ),
                $chunk
            );

            $responses = Http::pool(function (Pool $pool) use ($urls) {
                foreach ($urls as $url) {
                    $pool->timeout(30)->get($url);
                }
            });

            foreach ($responses as $response) {
                if (! $response->successful()) continue;
                $body = $response->json();
                if (($body['subsonic-response']['status'] ?? '') !== 'ok') continue;
                $artistAlbums = $body['subsonic-response']['artist']['album'] ?? [];
                $albums       = array_merge($albums, $artistAlbums);
            }
        }

        return $albums;
    }

    /**
     * Fetch all songs from all albums in parallel.
     */
    public function getAllSongs(): array
    {
        $albums = $this->getAllAlbums();

        if (empty($albums)) {
            return [];
        }

        $albumUrls = array_map(
            fn($album) => "{$this->baseUrl}/getAlbum?" . http_build_query(
                array_merge($this->baseParams, ['id' => $album['id']])
            ),
            $albums
        );

        $songs  = [];
        $chunks = array_chunk($albumUrls, 20);

        foreach ($chunks as $chunk) {
            $responses = Http::pool(function (Pool $pool) use ($chunk) {
                foreach ($chunk as $url) {
                    $pool->timeout(30)->get($url);
                }
            });

            foreach ($responses as $response) {
                if (! $response->successful()) continue;

                $body = $response->json();
                if (($body['subsonic-response']['status'] ?? '') !== 'ok') continue;

                $albumSongs = $body['subsonic-response']['album']['song'] ?? [];
                $songs      = array_merge($songs, $albumSongs);
            }
        }

        return $songs;
    }
}
