<?php

namespace App\Jobs\MusicBrainz;

use App\Models\Person;
use App\Services\MusicBrainzService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SearchPersonOnMusicBrainz implements ShouldQueue
{
    use Queueable;

    public int $tries   = 2;
    public int $timeout = 30;
    public int $backoff = 8;

    public function __construct(public readonly int $personId) {}

    public function handle(MusicBrainzService $mb): void
    {
        $person = Person::findOrFail($this->personId);

        if ($person->mb_id) {
            return;
        }

        $result = $mb->searchPerson($person->name);

        if (! $result || empty($result['id'])) {
            Log::info("SearchPersonOnMusicBrainz: no match for \"{$person->name}\"");
            return;
        }

        $updates = ['mb_id' => $result['id']];

        if (empty($person->gender) && ! empty($result['gender'])) {
            $updates['gender'] = strtolower($result['gender']);
        }

        if (empty($person->born) && ! empty($result['life-span']['begin'])) {
            $updates['born'] = $result['life-span']['begin'];
        }

        if (empty($person->died) && ! empty($result['life-span']['end'])) {
            $updates['died'] = $result['life-span']['end'];
        }

        $person->fill($updates);
        $person->save();

        Log::info("SearchPersonOnMusicBrainz: matched \"{$person->name}\"", [
            'mb_id'  => $result['id'],
            'gender' => $updates['gender'] ?? '(not in result)',
        ]);
    }
}
