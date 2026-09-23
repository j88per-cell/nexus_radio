<?php

namespace App\Jobs\MusicBrainz;

use App\Models\Song;
use App\Models\TrackTag;
use App\Services\MusicBrainzService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class FetchSongFromMusicBrainz implements ShouldQueue
{
    use Queueable;

    public int $tries   = 2;
    public int $timeout = 30;
    public int $backoff = 4;

    public function __construct(public readonly int $songId) {}

    public function handle(MusicBrainzService $mb): void
    {
        $song = Song::with('tracks.release.artist')->findOrFail($this->songId);

        foreach ($song->tracks as $track) {
            $this->processTrack($track, $song, $mb);
        }

        $song->mb_fetched_at = now();
        $song->save();
    }

    private function processTrack($track, $song, MusicBrainzService $mb): void
    {
        // Prefer lookup by MB recording ID — accurate
        if ($track->mb_recording_id) {
            $recording = $mb->getRecording($track->mb_recording_id);
        } else {
            // Fall back to search by title + artist
            $artistName = $song->originalArtist?->name
                ?? $track->release?->artist?->name;

            if (! $artistName) {
                return;
            }

            $recording = $mb->searchRecording($song->title, $artistName);
        }

        // Always clean existing tags for this track before re-applying
        TrackTag::where('track_id', $track->id)->delete();

        if (! $recording) {
            // No MB match at all — but the local title/release text may
            // already say everything we need (e.g. "Head Over Heels (live)
            // BONUS TRACK"), so don't give up on tags just because the
            // external lookup failed. No MB call needed for this — it's
            // already sitting right there in our own data.
            $localTags = $mb->detectTagsFromText($song->title, $track->release?->title ?? '');
            foreach ($localTags as $tag) {
                TrackTag::create(['track_id' => $track->id, 'tag' => $tag]);
            }

            Log::info("MusicBrainz: no recording found for \"{$song->title}\" (track {$track->id})"
                . ($localTags ? ' — tagged from local title: ' . implode(', ', $localTags) : ''));
            return;
        }

        if (! $track->mb_recording_id && ! empty($recording['id'])) {
            $track->mb_recording_id = $recording['id'];
        }

        if (! empty($recording['length'])) {
            $track->mb_duration_seconds = (int) round($recording['length'] / 1000);
        }

        $track->save();

        $tags = $mb->detectSongTags($recording);

        foreach ($tags as $tag) {
            TrackTag::create([
                'track_id' => $track->id,
                'tag'      => $tag,
            ]);
        }

        if ($tags) {
            Log::info("MusicBrainz: tagged \"{$song->title}\" (track {$track->id}) — " . implode(', ', $tags));
        } else {
            Log::info("MusicBrainz: no tags for \"{$song->title}\" (track {$track->id})");
        }
    }
}
