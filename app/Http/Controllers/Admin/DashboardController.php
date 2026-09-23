<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlayHistory;
use App\Models\QueueItem;
use App\Services\LiquidsoapService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // Now playing
        $playing = QueueItem::whereIn('status', [QueueItem::STATUS_PLAYING, QueueItem::STATUS_PUSHED])
            ->with(['track.song', 'track.release.artist', 'track.release.genres'])
            ->orderByRaw("CASE status WHEN 'playing' THEN 0 ELSE 1 END")
            ->latest('pushed_at')
            ->first();

        // Queue — next 5 pending items
        $queue = QueueItem::whereIn('status', [QueueItem::STATUS_PENDING, QueueItem::STATUS_PUSHED])
            ->with(['track.song', 'track.release.artist'])
            ->orderBy('id', 'asc')
            ->limit(5)
            ->get()
            ->map(fn($item) => [
                'id'     => $item->id,
                'type'   => $item->type,
                'status' => $item->status,
                'song'   => $item->track ? [
                    'title'  => $item->track->song?->title,
                    'artist' => $item->track->release?->artist?->name,
                ] : null,
            ]);

        // Station health
        $liquidsoapConnected = false;
        $queueDepth          = 0;
        try {
            $ls                  = app(LiquidsoapService::class);
            $queueDepth          = $ls->queueLength();
            $liquidsoapConnected = true;
        } catch (\Throwable) {}

        $lastActivity = QueueItem::whereIn('status', [QueueItem::STATUS_PLAYING, QueueItem::STATUS_PLAYED])
            ->latest('updated_at')
            ->value('updated_at');

        // Play stats — today/week/month are true calendar periods, so they read as 0
        // right after their boundary rolls over instead of drifting as a rolling window.
        $today   = PlayHistory::whereDate('played_at', today())->count();
        $week    = PlayHistory::where('played_at', '>=', now()->startOfWeek())->count();
        $month   = PlayHistory::where('played_at', '>=', now()->startOfMonth())->count();
        $allTime = PlayHistory::count();

        $topArtists = PlayHistory::join('tracks', 'play_history.track_id', '=', 'tracks.id')
            ->join('releases', 'tracks.release_id', '=', 'releases.id')
            ->join('artists', 'releases.artist_id', '=', 'artists.id')
            ->select('artists.name as artist', DB::raw('count(*) as count'))
            ->where('play_history.played_at', '>=', now()->startOfMonth())
            ->whereNull('artists.deleted_at')
            ->groupBy('artists.id', 'artists.name')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        $topGenres = PlayHistory::join('tracks', 'play_history.track_id', '=', 'tracks.id')
            ->join('releases', 'tracks.release_id', '=', 'releases.id')
            ->join('release_genres', 'releases.id', '=', 'release_genres.release_id')
            ->join('genres', 'release_genres.genre_id', '=', 'genres.id')
            ->select('genres.name as genre', DB::raw('count(*) as count'))
            ->where('play_history.played_at', '>=', now()->startOfMonth())
            ->groupBy('genres.id', 'genres.name')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        return Inertia::render('Admin/Dashboard', [
            'nowPlaying' => $playing?->track ? [
                'title'    => $playing->track->song?->title,
                'artist'   => $playing->track->release?->artist?->name,
                'album'    => $playing->track->release?->title,
                'year'     => $playing->track->release?->release_date?->year,
                'genre'    => $playing->track->release?->genres->first()?->name,
                'duration' => $playing->track->duration_seconds,
                'type'     => $playing->type,
            ] : null,
            'queue'  => $queue,
            'health' => [
                'liquidsoap_connected' => $liquidsoapConnected,
                'queue_depth'          => $queueDepth,
                'last_activity_at'     => $lastActivity,
            ],
            'stats' => [
                'today'       => $today,
                'week'        => $week,
                'month'       => $month,
                'all_time'    => $allTime,
                'top_artists' => $topArtists,
                'top_genres'  => $topGenres,
            ],
        ]);
    }
}
