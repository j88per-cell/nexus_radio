<?php

namespace App\Jobs\MusicBrainz;

use App\Models\Person;
use App\Services\MusicBrainzService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class FetchPersonFromMusicBrainz implements ShouldQueue
{
    use Queueable;

    public int $tries   = 2;
    public int $timeout = 30;
    public int $backoff = 8;

    public function __construct(public readonly int $personId) {}

    public function handle(MusicBrainzService $mb): void
    {
        $person = Person::findOrFail($this->personId);

        if (! $person->mb_id) {
            return;
        }

        // Already fetched recently — skip to avoid re-dispatch noise
        if ($person->mb_fetched_at && $person->mb_fetched_at->gt(now()->subHours(1))) {
            return;
        }

        $data = $mb->getArtist($person->mb_id);

        if (! $data) {
            Log::warning("FetchPersonFromMusicBrainz: no MB data for person {$person->id} ({$person->name})");
            $person->mb_fetched_at = now();
            $person->save();
            return;
        }

        $updates = ['mb_fetched_at' => now()];

        if (empty($person->gender) && ! empty($data['gender'])) {
            $updates['gender'] = strtolower($data['gender']);
        }

        if (empty($person->born) && ! empty($data['life-span']['begin'])) {
            $updates['born'] = $data['life-span']['begin'];
        }

        if (empty($person->died) && ! empty($data['life-span']['end'])) {
            $updates['died'] = $data['life-span']['end'];
        }

        $person->fill($updates);
        $person->save();

        $gained = array_filter(array_intersect_key($updates, array_flip(['gender', 'born', 'died'])));
        if (! empty($gained)) {
            Log::info("FetchPersonFromMusicBrainz: updated \"{$person->name}\"", $gained);
        }
    }
}
