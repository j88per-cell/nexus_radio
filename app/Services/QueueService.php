<?php

namespace App\Services;

use App\Models\QueueItem;
use App\Models\Track;
use Illuminate\Support\Facades\Log;

class QueueService
{
    public function __construct(private NavidromeService $navidrome) {}

    /**
     * Enqueue a song, automatically inserting its required predecessor
     * song (e.g. an intro track) immediately before it if one is set
     * and isn't already the last pending song in the queue.
     *
     * Prefers the real local file_path (2026-08-30: backfilled for
     * 4909/5249 tracks — see music:backfill-file-paths) over Navidrome's
     * HTTP stream — Liquidsoap's request.create() already handles a local
     * path transparently, the same way DJ segment audio (a local TTS wav)
     * always has. Falls back to Navidrome only for tracks not yet
     * backfilled, so nothing breaks for the remaining minority.
     */
    public function enqueueSong(Track $track, ?string $streamUrl = null): QueueItem
    {
        $streamUrl ??= $track->file_path
            ?? ($track->navidrome_track_id ? $this->navidrome->streamUrl($track->navidrome_track_id) : null);

        $this->enqueuePredecessorIfNeeded($track);

        return QueueItem::create([
            'type'       => QueueItem::TYPE_SONG,
            'status'     => QueueItem::STATUS_PENDING,
            'track_id'   => $track->id,
            'audio_path' => $streamUrl,
        ]);
    }

    private function enqueuePredecessorIfNeeded(Track $track): void
    {
        $predecessorSongId = $track->song?->requires_predecessor_song_id;

        if (! $predecessorSongId) {
            return;
        }

        $lastPending = QueueItem::where('status', QueueItem::STATUS_PENDING)
            ->where('type', QueueItem::TYPE_SONG)
            ->with('track')
            ->orderBy('id', 'desc')
            ->first();

        if ($lastPending?->track?->song_id === $predecessorSongId) {
            return;
        }

        $predecessorTrack = Track::whereNotNull('navidrome_track_id')
            ->where('song_id', $predecessorSongId)
            ->first();

        if (! $predecessorTrack) {
            Log::warning('QueueService: required predecessor song has no playable track', [
                'predecessor_song_id' => $predecessorSongId,
                'track_id'            => $track->id,
            ]);
            return;
        }

        QueueItem::create([
            'type'       => QueueItem::TYPE_SONG,
            'status'     => QueueItem::STATUS_PENDING,
            'track_id'   => $predecessorTrack->id,
            'audio_path' => $this->navidrome->streamUrl($predecessorTrack->navidrome_track_id),
        ]);
    }
}
