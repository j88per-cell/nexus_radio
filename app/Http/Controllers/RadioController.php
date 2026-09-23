<?php

namespace App\Http\Controllers;

use App\Jobs\Radio\PickNextTrackJob;
use App\Models\QueueItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class RadioController extends Controller
{
    /**
     * Polled by the frontend to show what's playing.
     */
    public function nowPlaying(): JsonResponse
    {
        $current = QueueItem::where('type', QueueItem::TYPE_SONG)
            ->whereIn('status', [QueueItem::STATUS_PLAYING, QueueItem::STATUS_PUSHED])
            ->with('track.song', 'track.release.artist')
            ->orderByRaw("CASE status WHEN 'playing' THEN 0 ELSE 1 END")
            ->orderBy('pushed_at')
            ->first();

        $history = QueueItem::where('status', QueueItem::STATUS_PLAYED)
            ->where('type', QueueItem::TYPE_SONG)
            ->with('track.song', 'track.release.artist')
            ->latest('played_at')
            ->limit(3)
            ->get();

        $next = QueueItem::where('status', QueueItem::STATUS_PENDING)
            ->where('type', QueueItem::TYPE_SONG)
            ->with('track.song', 'track.release.artist')
            ->orderBy('id')
            ->first();

        $trackData = fn($item) => $item?->track ? [
            'title'  => $item->track->song->title,
            'artist' => $item->track->release->artist->name,
            'album'  => $item->track->release->title,
            'year'   => $item->track->release->release_date?->year,
        ] : null;

        return response()->json([
            'current'  => $trackData($current),
            'history'  => $history->map($trackData)->filter()->values(),
            'upcoming' => $trackData($next),
        ]);
    }

    /**
     * Called by Liquidsoap (request.dynamic) to get the next audio path/URL.
     */
    public function next(): Response
    {
        // Mark previously playing item as played
        $previous = QueueItem::where('status', QueueItem::STATUS_PLAYING)->latest()->first();

        if ($previous) {
            $previous->update(['status' => QueueItem::STATUS_PLAYED]);
            Log::info('RadioController: finished track', ['track_id' => $previous->track_id]);
        }

        // Reclaim items stuck PUSHED with no confirming track-started webhook —
        // e.g. Liquidsoap failed to open/decode that specific URL. An item only
        // becomes PLAYING via the webhook above, so a silently-failed push is
        // never marked played and never eligible to be re-picked (it's not
        // PENDING) — a permanent black hole. 2026-08-30: this exact scenario
        // caused hours of dead air with the "now playing" admin display showing
        // a track the whole time, since PUSHED counts as "current" there too.
        $this->reclaimStalledPushedItems();

        $next = QueueItem::where('status', QueueItem::STATUS_PENDING)
            ->whereNotNull('audio_path')
            ->where('type', QueueItem::TYPE_SONG)
            ->orderByDesc('priority')
            ->orderBy('id')
            ->first();

        if (! $next) {
            Log::warning('RadioController: queue empty, picking synchronously');

            PickNextTrackJob::dispatchSync($previous);

            $next = QueueItem::where('status', QueueItem::STATUS_PENDING)
                ->where('type', QueueItem::TYPE_SONG)
                ->orderBy('id')
                ->first();
        }

        if (! $next) {
            Log::error('RadioController: still no item after sync pick');
            return response('', 200);
        }

        $next->update(['status' => QueueItem::STATUS_PUSHED, 'pushed_at' => now()]);

        Log::info('RadioController: serving', ['id' => $next->id, 'type' => $next->type]);

        $audioPath = $next->audio_path;

        // Inject ReplayGain annotation for songs with measured loudness so
        // Liquidsoap can normalize level without re-encoding.
        if ($next->isSong() && $next->track?->song?->signal?->loudness_analyzed) {
            $lufs   = (float) $next->track->song->signal->integrated_loudness;
            $gainDb = round(-18.0 - $lufs, 2);
            $gainDb = max(-12.0, min(12.0, $gainDb));
            $audioPath = 'annotate:replaygain_track_gain="' . $gainDb . ' dB":' . $audioPath;
        }

        // Top up the queue asynchronously. Exclude $next itself — it was just marked
        // PUSHED above, but it's what's about to play, not what's queued beyond that.
        $pendingCount = QueueItem::whereIn('status', [QueueItem::STATUS_PENDING, QueueItem::STATUS_PUSHED])
            ->where('id', '!=', $next->id)
            ->count();

        if ($pendingCount < config('radio.playback.queue_lookahead', 1)) {
            PickNextTrackJob::dispatch($next->isSong() ? $next : null);
        }

        return response($audioPath, 200)->header('Content-Type', 'text/plain');
    }

    // Age threshold, in seconds, past which a PUSHED item with no track-started
    // confirmation is considered stalled rather than legitimately about to start.
    // Liquidsoap's own request.resolve timeout (radio.liq) is 10s, so a healthy
    // push should reach PLAYING well inside that plus normal request overhead —
    // this needs real margin above that worst case, not just above a single
    // attempt.
    private const PUSHED_STALL_AGE = 30;

    /**
     * Reclaim items stuck PUSHED with no confirming track-started webhook —
     * Liquidsoap failed to open/decode that specific URL and silently gave up
     * on it without ever telling Laravel. Marked STALLED (not deleted) so
     * there's a record of what actually happened, distinct from a normal
     * PLAYED item.
     */
    private function reclaimStalledPushedItems(): void
    {
        QueueItem::where('status', QueueItem::STATUS_PUSHED)
            ->where('pushed_at', '<', now()->subSeconds(self::PUSHED_STALL_AGE))
            ->update(['status' => QueueItem::STATUS_STALLED]);
    }
}
