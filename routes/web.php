<?php

use App\Http\Controllers\Admin\ArtistController;
use App\Http\Controllers\Admin\BandMemberController;
use App\Http\Controllers\Admin\ConnectionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LiveController;
use App\Http\Controllers\Admin\PeopleController;
use App\Http\Controllers\Admin\ReleaseCreditController;
use App\Http\Controllers\Admin\ReleaseController;
use App\Http\Controllers\Admin\ShowController;
use App\Http\Controllers\Admin\SongController;
use App\Http\Controllers\Admin\SongRequestController;
use App\Http\Controllers\EncyclopediaController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'streamUrl' => config('radio.stream_url'),
    ]);
});

Route::get('/dashboard', fn() => redirect('/admin/dashboard'))->middleware('auth')->name('dashboard');

// Encyclopedia — public
Route::prefix('encyclopedia')->name('encyclopedia.')->group(function () {
    Route::get('/',                       [EncyclopediaController::class, 'index'])->name('index');
    Route::get('/artists/{slug}',         [EncyclopediaController::class, 'artist'])->name('artist');
    Route::get('/people/{slug}',          [EncyclopediaController::class, 'person'])->name('person');
    Route::get('/genres/{slug}',          [EncyclopediaController::class, 'genre'])->name('genre');
    Route::get('/releases/{slug}',        [EncyclopediaController::class, 'release'])->name('release');
    Route::get('/songs/{song}',           [EncyclopediaController::class, 'song'])->name('song');
});

Route::prefix('admin')->middleware('auth')->name('admin.')->group(function () {
    Route::get('/', fn() => redirect('/admin/dashboard'));
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('requests', [SongRequestController::class, 'store'])->name('requests.store');

    Route::get('artists', [ArtistController::class, 'index'])->name('artists');
    Route::get('release-credits', [ReleaseCreditController::class, 'index'])->name('release-credits');
    Route::post('release-credits', [ReleaseCreditController::class, 'store'])->name('release-credits.store');
    Route::delete('release-credits/{releaseCredit}', [ReleaseCreditController::class, 'destroy'])->name('release-credits.destroy');
    Route::get('release-credits/search-people', [ReleaseCreditController::class, 'searchPeople'])->name('release-credits.search-people');

    Route::get('connections', [ConnectionController::class, 'index'])->name('connections');
    Route::post('connections', [ConnectionController::class, 'store'])->name('connections.store');
    Route::delete('connections/{connection}', [ConnectionController::class, 'destroy'])->name('connections.destroy');
    Route::get('connections/search', [ConnectionController::class, 'search'])->name('connections.search');
    Route::get('connections/search-releases', [ConnectionController::class, 'searchReleases'])->name('connections.search-releases');
    Route::get('artists/search', [ArtistController::class, 'search'])->name('artists.search');
    Route::patch('artists/{artist}', [ArtistController::class, 'update'])->name('artists.update');
    Route::post('artists/{artist}/merge', [ArtistController::class, 'merge'])->name('artists.merge');
    Route::post('artists/{artist}/influences', [ArtistController::class, 'storeInfluence'])->name('artists.influences.store');
    Route::delete('artists/{artist}/influences/{influence}', [ArtistController::class, 'destroyInfluence'])->name('artists.influences.destroy');
    Route::delete('artists/{artist}', [ArtistController::class, 'destroy'])->name('artists.destroy');
    Route::post('artists/bulk-destroy', [ArtistController::class, 'bulkDestroy'])->name('artists.bulk-destroy');

    Route::get('band-members', [BandMemberController::class, 'index'])->name('band-members');
    Route::get('band-members/search-people', [BandMemberController::class, 'searchPeople'])->name('band-members.search-people');
    Route::get('band-members/{artist}/members', [BandMemberController::class, 'members'])->name('band-members.members');
    Route::post('band-members/{artist}', [BandMemberController::class, 'store'])->name('band-members.store');
    Route::patch('band-members/membership/{membership}', [BandMemberController::class, 'update'])->name('band-members.update');
    Route::delete('band-members/membership/{membership}', [BandMemberController::class, 'destroy'])->name('band-members.destroy');

    Route::get('people', [PeopleController::class, 'index'])->name('people');
    Route::post('people', [PeopleController::class, 'store'])->name('people.store');
    Route::get('people/search', [PeopleController::class, 'search'])->name('people.search');
    Route::patch('people/{person}', [PeopleController::class, 'update'])->name('people.update');
    Route::post('people/{person}/merge', [PeopleController::class, 'merge'])->name('people.merge');

    Route::get('shows', [ShowController::class, 'index'])->name('shows');
    Route::post('shows', [ShowController::class, 'store'])->name('shows.store');
    Route::patch('shows/{show}', [ShowController::class, 'update'])->name('shows.update');
    Route::delete('shows/{show}', [ShowController::class, 'destroy'])->name('shows.destroy');
    Route::get('shows/search-tracks',      [ShowController::class, 'searchTracks'])->name('shows.search-tracks');
    Route::get('shows/browser/artists',    [ShowController::class, 'browseArtists'])->name('shows.browse.artists');
    Route::get('shows/browser/releases',   [ShowController::class, 'browseReleases'])->name('shows.browse.releases');
    Route::get('shows/browser/tracks',     [ShowController::class, 'browseTracks'])->name('shows.browse.tracks');
    Route::get('shows/{show}/tracks',      [ShowController::class, 'tracks'])->name('shows.tracks');
    Route::put('shows/{show}/tracks',      [ShowController::class, 'syncTracks'])->name('shows.tracks.sync');
    Route::post('shows/{show}/start',      [ShowController::class, 'start'])->name('shows.start');
    Route::post('shows/{show}/stop',       [ShowController::class, 'stop'])->name('shows.stop');
    Route::post('shows/free-play/pause',   [ShowController::class, 'pauseFreePlay'])->name('shows.free-play.pause');
    Route::post('shows/free-play/resume',  [ShowController::class, 'resumeFreePlay'])->name('shows.free-play.resume');

    Route::get('live', [LiveController::class, 'index'])->name('live');
    Route::post('live/mute', [LiveController::class, 'mute'])->name('live.mute');
    Route::post('live/unmute', [LiveController::class, 'unmute'])->name('live.unmute');

    Route::get('songs', [SongController::class, 'index'])->name('songs');
    Route::patch('songs/{song}', [SongController::class, 'update'])->name('songs.update');
    Route::post('songs/{song}/toggle-blocked', [SongController::class, 'toggleBlocked'])->name('songs.toggle-blocked');

    Route::get('releases', [ReleaseController::class, 'index'])->name('releases');
    Route::post('releases/{release}/toggle-blocked', [ReleaseController::class, 'toggleBlocked'])->name('releases.toggle-blocked');

    Route::get('queue', function () {
        $queue = \App\Models\QueueItem::with(['track.song', 'track.release.artist'])
            ->whereIn('status', [
                \App\Models\QueueItem::STATUS_PENDING,
                \App\Models\QueueItem::STATUS_PUSHED,
                \App\Models\QueueItem::STATUS_PLAYING,
            ])
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->map(fn($item) => [
                'id'         => $item->id,
                'type'       => $item->type,
                'status'     => $item->status,
                'audio_path' => $item->audio_path,
                'song'       => $item->track ? [
                    'title'    => $item->track->song?->title,
                    'artist'   => $item->track->release?->artist?->name,
                    'duration' => $item->track->duration_seconds,
                ] : null,
            ]);

        $liveShow = \App\Models\Show::where('status', \App\Models\Show::STATUS_LIVE)->first();

        return Inertia::render('Admin/Queue', [
            'queue'    => $queue,
            'liveShow' => $liveShow ? ['id' => $liveShow->id, 'name' => $liveShow->name, 'mode' => $liveShow->mode] : null,
        ]);
    })->name('queue');

    Route::post('queue/reset', function () {
        // End any live manual show
        \App\Models\Show::where('status', \App\Models\Show::STATUS_LIVE)
            ->where('mode', \App\Models\Show::MODE_MANUAL)
            ->each(function ($show) {
                $show->update([
                    'status'     => $show->recurrence === \App\Models\Show::RECURRENCE_ONCE
                        ? \App\Models\Show::STATUS_DONE
                        : \App\Models\Show::STATUS_ACTIVE,
                    'live_until' => null,
                ]);
            });

        // Wipe pending + stuck pushed items
        \App\Models\QueueItem::whereIn('status', [
            \App\Models\QueueItem::STATUS_PENDING,
            \App\Models\QueueItem::STATUS_PUSHED,
        ])->delete();

        // Kick free play
        \App\Jobs\Radio\PickNextTrackJob::dispatchSync();

        return redirect()->route('admin.queue');
    })->name('queue.reset');

    Route::get('settings', function () {
        return Inertia::render('Admin/Settings', [
            'settings' => [
                'stream_url'   => config('radio.stream_url'),
                'station_name' => config('radio.station_name', 'Nexus Radio'),
            ],
        ]);
    })->name('settings');

    Route::patch('settings', function () {
        // TODO: persist settings
        return back();
    })->name('settings.update');
});

require __DIR__.'/auth.php';
