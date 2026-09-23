<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\Radio\PickNextTrackJob;
use App\Models\Artist;
use App\Models\Genre;
use App\Models\QueueItem;
use Illuminate\Support\Facades\Cache;
use App\Models\Release;
use App\Models\Show;
use App\Models\ShowTrack;
use App\Models\Track;
use App\Services\QueueService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ShowController extends Controller
{
    public function index()
    {
        $shows = Show::orderByDesc('priority')
            ->orderBy('name')
            ->get()
            ->map(fn($show) => $this->showData($show));

        $genres = Genre::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Shows', [
            'shows'          => $shows,
            'genres'         => $genres,
            'freePlayPaused' => (bool) Cache::get('radio.free_play_paused'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validate($request);

        $show = Show::create($data);
        $show->update(['next_run_at' => $show->computeNextRunAt()]);

        return redirect()->route('admin.shows');
    }

    public function update(Request $request, Show $show)
    {
        if ($this->isLocked($show)) {
            return back()->withErrors(['locked' => 'This show is locked — edits are not allowed within 1 hour of air time.']);
        }

        $data = $this->validate($request, $show);
        $show->update($data);
        $show->update(['next_run_at' => $show->fresh()->computeNextRunAt()]);

        return redirect()->route('admin.shows');
    }

    public function destroy(Show $show)
    {
        $show->delete();

        return redirect()->route('admin.shows');
    }

    /**
     * Browse artists for the track picker, with optional filters:
     *   q        – keyword matched against name, bio, story
     *   genre    – genre id
     *   year_from / year_to – filter by release year range (any release by artist)
     */
    public function browseArtists(Request $request)
    {
        $q        = trim($request->input('q', ''));
        $genre    = $request->input('genre');
        $yearFrom = $request->input('year_from');
        $yearTo   = $request->input('year_to');

        $artists = Artist::query()
            ->when($genre, fn($query) =>
                $query->whereHas('genres', fn($g) => $g->where('genres.id', $genre))
            )
            ->when($q, fn($query) =>
                $query->where(fn($sub) =>
                    $sub->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($q) . '%'])
                        ->orWhereRaw('LOWER(bio) LIKE ?', ['%' . mb_strtolower($q) . '%'])
                        ->orWhereRaw('LOWER(story) LIKE ?', ['%' . mb_strtolower($q) . '%'])
                )
            )
            ->when($yearFrom, fn($query) =>
                $query->whereHas('releases', fn($r) =>
                    $r->where('release_date', '>=', "{$yearFrom}-01-01")
                )
            )
            ->when($yearTo, fn($query) =>
                $query->whereHas('releases', fn($r) =>
                    $r->where('release_date', '<=', "{$yearTo}-12-31")
                )
            )
            ->orderBy('name')
            ->limit(300)
            ->get(['id', 'name']);

        return response()->json($artists);
    }

    /**
     * Return releases for a given artist_id.
     */
    public function browseReleases(Request $request)
    {
        $artistId = $request->input('artist_id');

        $releases = Release::where('artist_id', $artistId)
            ->orderByRaw('release_date IS NULL')
            ->orderBy('release_date')
            ->orderBy('title')
            ->get(['id', 'title', 'release_date', 'type'])
            ->map(fn($r) => [
                'id'    => $r->id,
                'title' => $r->title,
                'year'  => $r->release_date ? Carbon::parse($r->release_date)->year : null,
                'type'  => $r->type,
            ]);

        return response()->json($releases);
    }

    /**
     * Return tracks for a given release_id.
     */
    public function browseTracks(Request $request)
    {
        $releaseId = $request->input('release_id');

        $tracks = Track::join('songs', 'tracks.song_id', '=', 'songs.id')
            ->where('tracks.release_id', $releaseId)
            ->orderBy('tracks.disc')
            ->orderBy('tracks.position')
            ->select(
                'tracks.id',
                'tracks.duration_seconds',
                'tracks.position',
                'tracks.disc',
                'songs.title as song_title'
            )
            ->get();

        return response()->json($tracks);
    }

    /**
     * Search tracks by song title / artist / album for the manual show track picker.
     * Joins through the full song → track → release → artist hierarchy.
     */
    public function searchTracks(Request $request)
    {
        $q = trim($request->input('q', ''));

        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $tracks = Track::join('songs', 'tracks.song_id', '=', 'songs.id')
            ->join('releases', 'tracks.release_id', '=', 'releases.id')
            ->join('artists', 'releases.artist_id', '=', 'artists.id')
            ->where(function ($query) use ($q) {
                $query->whereRaw('LOWER(songs.title) LIKE ?', ['%' . mb_strtolower($q) . '%'])
                      ->orWhereRaw('LOWER(artists.name) LIKE ?', ['%' . mb_strtolower($q) . '%'])
                      ->orWhereRaw('LOWER(releases.title) LIKE ?', ['%' . mb_strtolower($q) . '%']);
            })
            ->select(
                'tracks.id',
                'tracks.duration_seconds',
                'songs.title as song_title',
                'artists.name as artist_name',
                'releases.title as album_title',
                'releases.release_date'
            )
            ->limit(30)
            ->get()
            ->map(fn($t) => [
                'id'               => $t->id,
                'song_title'       => $t->song_title,
                'artist_name'      => $t->artist_name,
                'album_title'      => $t->album_title,
                'year'             => $t->release_date ? Carbon::parse($t->release_date)->year : null,
                'duration_seconds' => $t->duration_seconds,
            ]);

        return response()->json($tracks);
    }

    /**
     * Replace the tracklist for a manual show.
     * Expects: { tracks: [{track_id, position, duration_seconds}] }
     */
    public function syncTracks(Request $request, Show $show)
    {
        if ($this->isLocked($show)) {
            return response()->json(['error' => 'Show is locked within 1 hour of air time.'], 423);
        }

        $request->validate([
            'tracks'                    => 'array',
            'tracks.*.track_id'         => 'required|integer|exists:tracks,id',
            'tracks.*.position'         => 'required|integer|min:0',
            'tracks.*.duration_seconds' => 'required|integer|min:0',
        ]);

        $show->tracks()->delete();

        foreach ($request->input('tracks', []) as $row) {
            ShowTrack::create([
                'show_id'          => $show->id,
                'track_id'         => $row['track_id'],
                'position'         => $row['position'],
                'duration_seconds' => $row['duration_seconds'],
            ]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Return full show data including tracks for the edit panel.
     */
    public function tracks(Show $show)
    {
        $tracks = $show->tracks()
            ->join('tracks', 'show_tracks.track_id', '=', 'tracks.id')
            ->join('songs', 'tracks.song_id', '=', 'songs.id')
            ->join('releases', 'tracks.release_id', '=', 'releases.id')
            ->join('artists', 'releases.artist_id', '=', 'artists.id')
            ->select(
                'show_tracks.id',
                'show_tracks.track_id',
                'show_tracks.position',
                'show_tracks.duration_seconds',
                'songs.title as song_title',
                'artists.name as artist_name',
                'releases.title as album_title'
            )
            ->orderBy('show_tracks.position')
            ->get();

        return response()->json($tracks);
    }

    public function pauseFreePlay()
    {
        Cache::put('radio.free_play_paused', true);
        Log::info('ShowController: free play paused');
        return redirect()->route('admin.shows');
    }

    public function resumeFreePlay()
    {
        Cache::forget('radio.free_play_paused');
        Log::info('ShowController: free play resumed');
        PickNextTrackJob::dispatch();
        return redirect()->route('admin.shows');
    }

    public function start(Show $show, QueueService $queueService)
    {
        $now = Carbon::now();

        // End any currently live show first
        $live = Show::where('status', Show::STATUS_LIVE)->first();
        if ($live) {
            $this->endShow($live, $now);
        }

        // Wipe pending queue so show takes over immediately
        QueueItem::where('status', QueueItem::STATUS_PENDING)->delete();

        if ($show->mode === Show::MODE_MANUAL) {
            $tracks = $show->tracks()->with('track')->get();
            if ($show->shuffle) {
                $tracks = $tracks->shuffle();
            }
            foreach ($tracks as $showTrack) {
                if (! $showTrack->track) {
                    continue;
                }
                $queueService->enqueueSong($showTrack->track);
            }
        } else {
            PickNextTrackJob::dispatch();
        }

        $liveUntil = null;
        if ($show->duration_minutes) {
            $liveUntil = $now->copy()->addMinutes($show->duration_minutes);
        } elseif ($show->mode === Show::MODE_MANUAL) {
            $totalSeconds = $show->tracks->sum('duration_seconds');
            $drops        = max(0, $show->tracks->count() - 1);
            $liveUntil    = $now->copy()->addSeconds($totalSeconds + $drops * 35);
        }

        $show->update([
            'status'      => Show::STATUS_LIVE,
            'last_run_at' => $now,
            'live_until'  => $liveUntil,
            'next_run_at' => $show->computeNextRunAt($now),
        ]);

        Cache::forget('radio.free_play_paused');
        Log::info("ShowController: manually started \"{$show->name}\"");

        return redirect()->route('admin.shows');
    }

    public function stop(Show $show)
    {
        if ($show->status !== Show::STATUS_LIVE) {
            return redirect()->route('admin.shows');
        }

        $now = Carbon::now();
        $this->endShow($show, $now);

        Log::info("ShowController: manually stopped \"{$show->name}\"");

        return redirect()->route('admin.shows');
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function endShow(Show $show, Carbon $now): void
    {
        if ($show->recurrence === Show::RECURRENCE_ONCE) {
            $show->update(['status' => Show::STATUS_DONE, 'live_until' => null]);
        } else {
            $show->update(['status' => Show::STATUS_ACTIVE, 'live_until' => null]);
        }

        if (QueueItem::where('status', QueueItem::STATUS_PENDING)->count() === 0) {
            PickNextTrackJob::dispatch();
        }
    }

    private function showData(Show $show): array
    {
        return [
            'id'               => $show->id,
            'name'             => $show->name,
            'description'      => $show->description,
            'theme'            => $show->theme,
            'mode'             => $show->mode,
            'shuffle'          => $show->shuffle,
            'status'           => $show->status,
            'priority'         => $show->priority,
            'recurrence'       => $show->recurrence,
            'scheduled_at'     => $show->scheduled_at
                                    ? $show->scheduled_at->setTimezone(config('app.schedule_timezone', 'America/Denver'))->format('Y-m-d\TH:i')
                                    : null,
            'start_time'       => $show->start_time,
            'duration_minutes' => $show->duration_minutes,
            'recurrence_days'  => $show->recurrence_days,
            'interval_hours'   => $show->interval_hours,
            'next_run_at'      => $show->next_run_at?->setTimezone(config('app.schedule_timezone', 'America/Denver'))->toIso8601String(),
            'last_run_at'      => $show->last_run_at?->setTimezone(config('app.schedule_timezone', 'America/Denver'))->toIso8601String(),
            'locked'           => $this->isLocked($show),
        ];
    }

    /**
     * Draft shows are never locked. Active/live automated shows lock 1 hour before next_run_at.
     */
    private function isLocked(Show $show): bool
    {
        if ($show->status === Show::STATUS_DRAFT) {
            return false;
        }

        if ($show->mode === Show::MODE_MANUAL) {
            return false;
        }

        return $show->next_run_at && $show->next_run_at->diffInMinutes(Carbon::now(), false) > -65;
    }

    private function validate(Request $request, ?Show $existing = null): array
    {
        $rules = [
            'name'             => 'required|string|max:255',
            'description'      => 'nullable|string',
            'theme'            => 'nullable|string',
            'mode'             => 'required|in:manual,auto',
            'shuffle'          => 'boolean',
            'status'           => 'required|in:draft,active,live,done',
            'priority'         => 'required|integer|min:0|max:100',
            'recurrence'       => 'required|in:once,interval,daily,weekly',
            'scheduled_at'     => 'nullable|date',
            'start_time'       => 'nullable|date_format:H:i',
            'duration_minutes' => 'nullable|integer|min:1',
            'recurrence_days'  => 'nullable|array',
            'recurrence_days.*'=> 'integer|min:0|max:6',
            'interval_hours'   => 'nullable|integer|min:1',
        ];

        $data = $request->validate($rules);

        // Parse scheduled_at as the local schedule timezone, store as UTC
        if (! empty($data['scheduled_at'])) {
            $data['scheduled_at'] = Carbon::createFromFormat(
                'Y-m-d\TH:i',
                substr($data['scheduled_at'], 0, 16),
                config('app.schedule_timezone', 'America/Denver')
            )->utc();
        }

        return $data;
    }
}
