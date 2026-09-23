<?php

namespace App\Jobs\Radio;

use App\Models\PlayHistory;
use App\Models\QueueItem;
use App\Models\Show;
use App\Models\SongTag;
use App\Models\Track;
use App\Services\QueueService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PickNextTrackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly ?QueueItem $afterItem = null,
    ) {}

    public function handle(QueueService $queueService): void
    {
        // A manual show is live — don't backfill as long as show tracks are still pending.
        // Once the queue drains, fall through so free play can resume.
        if (Show::where('status', Show::STATUS_LIVE)->where('mode', Show::MODE_MANUAL)->exists()) {
            if (QueueItem::where('status', QueueItem::STATUS_PENDING)->exists()) {
                return;
            }
        }

        // Free play paused — let the queue drain without refilling.
        if (Cache::get('radio.free_play_paused')) {
            Log::info('PickNextTrackJob: free play paused, skipping refill');
            return;
        }

        $lock = Cache::lock('pick_next_track', 10);
        $lock->block(5);

        $track     = null;
        $queueItem = null;

        try {
            // Exclude $afterItem itself: the caller passes in the item it just marked
            // PUSHED (the one about to play right now), not something already queued
            // beyond it. Counting it here would mean this job — dispatched specifically
            // to top up the queue past that item — almost always sees pendingCount >= 1
            // from that same item and silently no-ops, leaving nothing queued until the
            // emergency sync-pick path in RadioController::next() has to cover the gap.
            $pendingCount = QueueItem::whereIn('status', [QueueItem::STATUS_PENDING, QueueItem::STATUS_PUSHED])
                ->when($this->afterItem, fn($q) => $q->where('id', '!=', $this->afterItem->id))
                ->count();
            $needsSong    = $pendingCount < config('radio.playback.queue_lookahead', 1);

            if (! $needsSong) {
                return;
            }

            $trackCooldown  = config('radio.playback.song_cooldown',   14400);
            $artistCooldown = config('radio.playback.artist_cooldown',  3600);

            $recentTrackIds  = PlayHistory::where('played_at', '>=', Carbon::now()->subSeconds($trackCooldown))
                ->pluck('track_id')->unique()->values();

            $recentArtistIds = PlayHistory::where('played_at', '>=', Carbon::now()->subSeconds($artistCooldown))
                ->with('track.release')->get()
                ->pluck('track.release.artist_id')->filter()->unique()->values();

            // Also exclude anything already pending or pushed in the queue
            $queuedItems = QueueItem::whereIn('status', [QueueItem::STATUS_PENDING, QueueItem::STATUS_PUSHED])
                ->whereNotNull('track_id')
                ->with('track.release')
                ->get();

            $queuedTrackIds  = $queuedItems->pluck('track_id');
            $queuedArtistIds = $queuedItems->pluck('track.release.artist_id')->filter()->unique();

            $excludeTrackIds  = $recentTrackIds->merge($queuedTrackIds)->unique();
            $excludeArtistIds = $recentArtistIds->merge($queuedArtistIds)->unique();

            $track = $this->pickTrack($excludeTrackIds, $excludeArtistIds);

            if (! $track) {
                Log::error('PickNextTrackJob: no eligible tracks found');
                return;
            }

            $queueItem = $queueService->enqueueSong($track);
        } finally {
            $lock->release();
        }

        if ($track && $queueItem) {
            Log::info('PickNextTrackJob: picked track', [
                'track_id'      => $track->id,
                'song'          => $track->song->title ?? '?',
                'queue_item_id' => $queueItem->id,
            ]);
        }
    }

    /**
     * Pick the next track: forced from the station's configured genre list (when set),
     * unblocked, weighted toward least-played/unplayed tracks so rotation stays fresh
     * rather than converging on a favorite handful.
     */
    private function pickTrack(Collection $excludeTrackIds, Collection $excludeArtistIds): ?Track
    {
        // The library has many duplicate/near-duplicate title entries (remasters, live
        // versions, compilations) under distinct track_ids, so the track_id-keyed cooldown
        // above lets the same song straight back through under a different id — this
        // backstop catches that by excluding titles that were just played, regardless of id.
        $recentTitlePrefixes = PlayHistory::orderByDesc('played_at')
            ->with('track.song')
            ->limit(5)
            ->get()
            ->map(fn($history) => $history->track?->song?->title)
            ->filter()
            ->map(fn($title) => mb_strtolower(mb_substr($title, 0, 8)))
            ->unique()
            ->values()
            ->all();

        $track = $this->pickLeastPlayed($excludeTrackIds, $excludeArtistIds, $recentTitlePrefixes);

        if (! $track) {
            $track = Track::whereNotNull('navidrome_track_id')
                ->whereNotIn('id', $excludeTrackIds)
                ->whereHas('release', function ($q) use ($excludeArtistIds) {
                    $q->where('blocked', false)->whereNotIn('artist_id', $excludeArtistIds);
                })
                ->whereHas('song', fn($q) => $q
                    ->where('blocked', false)
                    ->when($recentTitlePrefixes, fn($q2) => $q2
                        ->whereNotIn(DB::raw('LOWER(LEFT(title, 8))'), $recentTitlePrefixes))
                    ->whereDoesntHave('tags', fn($t) => $t->whereIn('tag', SongTag::FREE_PLAY_EXCLUDED))
                )
                ->inRandomOrder()
                ->first();
        }

        if (! $track) {
            // Relax artist cooldown and try again
            Log::warning('PickNextTrackJob: no track found with artist cooldown, relaxing');
            $track = Track::whereNotNull('navidrome_track_id')
                ->whereNotIn('id', $excludeTrackIds)
                ->whereHas('release', fn($q) => $q->where('blocked', false))
                ->whereHas('song', fn($q) => $q
                    ->where('blocked', false)
                    ->when($recentTitlePrefixes, fn($q2) => $q2
                        ->whereNotIn(DB::raw('LOWER(LEFT(title, 8))'), $recentTitlePrefixes))
                    ->whereDoesntHave('tags', fn($t) => $t->whereIn('tag', SongTag::FREE_PLAY_EXCLUDED))
                )
                ->inRandomOrder()
                ->first();
        }

        return $track;
    }

    /**
     * Forced picks from the station's configured genre list, unblocked, weighted
     * toward least-played/unplayed.
     */
    private function pickLeastPlayed(Collection $excludeTrackIds, Collection $excludeArtistIds, array $recentTitlePrefixes = []): ?Track
    {
        $genres = array_values(array_filter(config('radio.playback.genres', [])));

        $query = fn(bool $filterGenre) => Track::whereNotNull('navidrome_track_id')
            ->whereNotIn('id', $excludeTrackIds)
            ->whereHas('release', function ($q) use ($excludeArtistIds, $genres, $filterGenre) {
                $q->where('blocked', false)->whereNotIn('artist_id', $excludeArtistIds);
                if ($filterGenre && $genres) {
                    $q->whereHas('artist.genres', fn($g) => $g->whereIn('name', $genres));
                }
            })
            ->whereHas('song', fn($q) => $q
                ->where('blocked', false)
                ->when($recentTitlePrefixes, fn($q2) => $q2
                    ->whereNotIn(DB::raw('LOWER(LEFT(title, 8))'), $recentTitlePrefixes))
                ->whereDoesntHave('tags', fn($t) => $t->whereIn('tag', SongTag::FREE_PLAY_EXCLUDED))
            )
            ->withCount('playHistory')
            ->orderBy('play_history_count')
            ->limit(10);

        $candidates = $query(true)->get();

        if ($candidates->isEmpty()) {
            // Artist genre tagging may be incomplete across the catalog — don't let that
            // starve the pick pool. Fall back to the same eligibility rules minus the
            // genre filter.
            $candidates = $query(false)->get();
        }

        if ($candidates->isEmpty()) {
            return null;
        }

        $chosen = $candidates->random();

        Log::info('PickNextTrackJob: least-played pick', [
            'title'      => $chosen->song->title ?? '?',
            'play_count' => $chosen->play_history_count,
            'track_id'   => $chosen->id,
        ]);

        return $chosen;
    }
}
