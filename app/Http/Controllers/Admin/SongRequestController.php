<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SongRequest;
use App\Models\Track;
use App\Services\QueueService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class SongRequestController extends Controller
{
    /** How long a song is off-limits for another request after it's been requested. */
    private const COOLDOWN_HOURS = 12;

    public function store(Request $request, QueueService $queueService)
    {
        $data = $request->validate([
            'track_id'     => 'required|integer|exists:tracks,id',
            'requested_by' => 'required|string|max:100',
        ]);

        $track = Track::with('song', 'release.artist')->findOrFail($data['track_id']);

        if (! $track->navidrome_track_id) {
            throw ValidationException::withMessages([
                'track_id' => 'This track has no playable audio.',
            ]);
        }

        $lastRequestedAt = SongRequest::where('song_id', $track->song_id)
            ->where('created_at', '>', Carbon::now()->subHours(self::COOLDOWN_HOURS))
            ->max('created_at');

        if ($lastRequestedAt) {
            $availableAt = Carbon::parse($lastRequestedAt)->addHours(self::COOLDOWN_HOURS);
            throw ValidationException::withMessages([
                'track_id' => "\"{$track->song->title}\" was already requested — it's on cooldown until {$availableAt->format('g:ia')}.",
            ]);
        }

        $requestedBy = trim($data['requested_by']);

        $queueItem = $queueService->enqueueSong($track);

        SongRequest::create([
            'song_id'      => $track->song_id,
            'track_id'     => $track->id,
            'queue_item_id' => $queueItem->id,
            'requested_by' => $requestedBy,
        ]);

        return back()->with('status', "Queued \"{$track->song->title}\" — requested by {$requestedBy}.");
    }
}
