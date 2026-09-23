<?php

namespace App\Http\Controllers;

use App\Jobs\Radio\PickNextTrackJob;
use App\Models\PlayHistory;
use App\Models\QueueItem;
use App\Models\RadioHealthEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Called by Liquidsoap source.on_track when a track starts playing.
     *
     * Liquidsoap sends track metadata as an array of [key, value] pairs.
     * We use 'initial_uri' to match back to the queue item's audio_path.
     */
    public function trackStarted(Request $request): Response
    {
        // Liquidsoap sends [[key, value], ...] pairs as the root payload
        $raw  = $request->all();
        $pairs = isset($raw['meta']) ? $raw['meta'] : $raw;
        $meta = collect($pairs)
            ->mapWithKeys(fn($pair) => is_array($pair) && isset($pair[0]) ? [$pair[0] => $pair[1] ?? null] : [])
            ->all();

        $path = $meta['initial_uri']
            ?? $request->input('uri')
            ?? $meta['filename']
            ?? $request->input('filename')
            ?? $request->input('path');

        // Strip Liquidsoap annotate: prefix (e.g. annotate:replaygain_track_gain="X dB":http://...)
        if ($path && preg_match('/^annotate:[^:]+:(.+)$/', $path, $m)) {
            $path = $m[1];
        }

        Log::info('Webhook: track-started', ['resolved_path' => $path]);

        $playingItem = null;

        if ($path) {
            $playingItem = QueueItem::where('status', QueueItem::STATUS_PUSHED)
                ->where('audio_path', $path)
                ->orderBy('pushed_at')
                ->first();

            if ($playingItem) {
                $playingItem->update([
                    'status'    => QueueItem::STATUS_PLAYING,
                    'played_at' => now(),
                ]);

                if ($playingItem->isSong() && $playingItem->track_id) {
                    PlayHistory::create([
                        'track_id'  => $playingItem->track_id,
                        'played_at' => now(),
                    ]);
                }
            }
        }

        // Mark any other previously-playing items as played
        QueueItem::where('status', QueueItem::STATUS_PLAYING)
            ->when($playingItem, fn($q) => $q->where('id', '!=', $playingItem->id))
            ->update(['status' => QueueItem::STATUS_PLAYED]);

        // Top up queue if needed
        $lookahead    = config('radio.playback.queue_lookahead', 1);
        $pendingCount = QueueItem::whereIn('status', [QueueItem::STATUS_PENDING, QueueItem::STATUS_PUSHED])->count();

        if ($pendingCount < $lookahead) {
            PickNextTrackJob::dispatch($playingItem);
        }

        return response('ok', 200);
    }

    /**
     * Called by Liquidsoap's periodic health-check thread (radio.liq) when
     * the main `radio` source transitions between ready and not-ready.
     *
     * Exists because the 2026-08-26 dead-air incident had no record of the
     * stall anywhere except a person happening to be awake and listening —
     * this makes "did the stream actually stall, when, and how often" a
     * queryable question instead of a matter of who was around to notice.
     */
    public function radioHealth(Request $request): Response
    {
        $event = $request->input('event');

        if (! in_array($event, [RadioHealthEvent::EVENT_STALLED, RadioHealthEvent::EVENT_RECOVERED], true)) {
            return response('invalid event', 422);
        }

        $event === RadioHealthEvent::EVENT_STALLED
            ? Log::warning("Webhook: radio-health — stalled")
            : Log::info("Webhook: radio-health — recovered");

        RadioHealthEvent::create([
            'event'       => $event,
            'occurred_at' => now(),
        ]);

        return response('ok', 200);
    }
}
