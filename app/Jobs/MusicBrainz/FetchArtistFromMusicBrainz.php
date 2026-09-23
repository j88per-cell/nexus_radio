<?php

namespace App\Jobs\MusicBrainz;

use App\Models\Artist;
use App\Models\ArtistMember;
use App\Models\Instrument;
use App\Models\Person;
use App\Services\MusicBrainzService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FetchArtistFromMusicBrainz implements ShouldQueue
{
    use Queueable;

    public int $tries   = 2;
    public int $timeout = 30;

    // 4 seconds between jobs to respect MusicBrainz rate limit
    public int $backoff = 4;

    public function __construct(public readonly int $artistId) {}

    public function handle(MusicBrainzService $mb): void
    {
        $artist = Artist::findOrFail($this->artistId);

        $searchName = $artist->name;

        // Skip placeholder names from untagged imports
        if (preg_match('/^\[.*\]$/', $searchName)) {
            Log::info("MusicBrainz: skipping placeholder artist \"{$searchName}\"");
            $artist->mb_fetched_at = now();
            $artist->save();
            return;
        }

        $result = $mb->searchArtist($searchName);

        if (! $result) {
            Log::info("MusicBrainz: no artist result for \"{$artist->name}\"");
            $artist->mb_fetched_at = now();
            $artist->save();
            return;
        }

        // Only fill fields that aren't already set
        $updates = ['mb_fetched_at' => now()];

        if (empty($artist->origin) && ! empty($result['country'])) {
            $updates['origin'] = $result['country'];
        }
        if (empty($artist->formed_year) && ! empty($result['life-span']['begin'])) {
            $updates['formed_year'] = (int) substr($result['life-span']['begin'], 0, 4);
        }
        if (empty($artist->disbanded_year) && ! empty($result['life-span']['end'])) {
            $updates['disbanded_year'] = (int) substr($result['life-span']['end'], 0, 4);
        }

        $artist->fill($updates);
        $artist->save();

        // Pull members from relations if it's a group
        if (($result['type'] ?? '') === 'Group' && ! empty($result['id'])) {
            sleep(1); // respect MB 1 req/sec between the search and detail calls
            $this->syncMembers($artist, $result['id'], $mb);
        }

        Log::info("MusicBrainz: fetched artist \"{$artist->name}\"");
    }

    private function instrumentCategory(string $name): string
    {
        $lower = strtolower($name);

        if (str_contains($lower, 'vocal') || str_contains($lower, 'voice') || $lower === 'choir') {
            return 'vocals';
        }
        if (in_array($lower, ['guitar', 'bass', 'banjo', 'mandolin', 'ukulele', 'sitar', 'lute', 'harp'])) {
            return 'strings';
        }
        if (in_array($lower, ['keyboards', 'piano', 'organ', 'synthesizer', 'accordion'])) {
            return 'keys';
        }
        if (in_array($lower, ['drums', 'percussion', 'drum machine', 'djembe', 'bongos', 'timpani'])) {
            return 'percussion';
        }
        if (in_array($lower, ['saxophone', 'trumpet', 'trombone', 'flute', 'clarinet', 'oboe', 'horn', 'tuba'])) {
            return 'brass/woodwind';
        }

        return 'other';
    }

    private function syncMembers(Artist $artist, string $mbid, MusicBrainzService $mb): void
    {
        $detail = $mb->getArtist($mbid);

        if (! $detail) {
            return;
        }

        foreach ($detail['relations'] ?? [] as $rel) {
            if (($rel['type'] ?? '') !== 'member of band') {
                continue;
            }

            $memberData = $rel['artist'] ?? null;
            if (! $memberData || empty($memberData['name'])) {
                continue;
            }

            $name   = $memberData['name'];
            $slug   = Str::slug($name);
            $mbid   = $memberData['id'] ?? null;
            $gender = isset($memberData['gender']) ? strtolower($memberData['gender']) : null;

            $person = Person::firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'mb_id' => $mbid, 'gender' => $gender]
            );

            // Fill any missing fields on an existing record
            $updates = [];
            if ($person->mb_id === null && $mbid !== null) {
                $updates['mb_id'] = $mbid;
            }
            if ($person->gender === null && $gender !== null) {
                $updates['gender'] = $gender;
            }
            if (! empty($updates)) {
                $person->update($updates);
            }

            $startYear  = isset($rel['begin']) ? (int) substr($rel['begin'], 0, 4) : null;
            $endYear    = isset($rel['end'])   ? (int) substr($rel['end'],   0, 4) : null;
            $attributes = $rel['attributes'] ?? [];

            $membership = ArtistMember::firstOrCreate(
                ['artist_id' => $artist->id, 'person_id' => $person->id],
                ['start_year' => $startYear, 'end_year' => $endYear]
            );

            if (! empty($attributes)) {
                $instrumentIds = collect($attributes)->map(function (string $attr) {
                    $slug = Str::slug($attr);

                    return Instrument::firstOrCreate(
                        ['slug' => $slug],
                        ['name' => $attr, 'category' => $this->instrumentCategory($attr)]
                    )->id;
                });

                $membership->instruments()->syncWithoutDetaching($instrumentIds->all());
            }
        }
    }
}
