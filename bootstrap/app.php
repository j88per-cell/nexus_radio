<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        //
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        // DJ and show transitions every minute
        $schedule->command('shows:transition')->everyMinute()->withoutOverlapping(5);
        $schedule->command('djs:transition')->everyMinute();

        // Liquidsoap-stall watchdog — DISABLED 2026-08-30, same day it was
        // added: it checked only Laravel's own bookkeeping (a stuck PUSHED
        // record), not whether the actual audio had moved on, so a merely
        // late/dropped track-started webhook looked identical to a real
        // stall — it interrupted at least two perfectly good songs before
        // being caught. Re-enable once RadioWatchdog cross-checks the real
        // icecast stream state before ever restarting.
        // $schedule->command('radio:watchdog')->everyMinute()->withoutOverlapping(5);

        // Unconditional re-sync of Chorus's active graph — catches drift (e.g. a
        // chorus_daemon restart) that DjObserver's active-column-change hook can't see.
        $schedule->command('chorus:sync-active-dj')->everyFiveMinutes();

        // Mood state snapshots every 5 minutes — drives booth drift charts
        $schedule->command('moods:snapshot')->everyFiveMinutes();

        // Archive the month that just ended (play totals + top artists/genres)
        // before the dashboard's live calendar-month figures roll over to 0.
        $schedule->command('stats:archive-month')->monthlyOn(1, '00:30');

        // Catch new/changed genre tags (and any new library additions) from Navidrome —
        // this is the only source that actually populates release_genres; MusicBrainz
        // fetch jobs below don't touch genre at all, so without this it only ever gets
        // set once, at whatever moment a release was first imported.
        $schedule->command('import:navidrome')->weeklyOn(0, '03:30')->withoutOverlapping();

        // Roll up release genres to artist level
        $schedule->command('artists:rollup-genres')->monthlyOn(1, '02:00');

        // Re-fetch MusicBrainz data monthly
        $schedule->command('artists:fetch-musicbrainz')->monthlyOn(1, '03:00');
        $schedule->command('songs:fetch-musicbrainz')->monthlyOn(1, '04:00');

        // Re-fetch artist bios monthly
        $schedule->command('artists:fetch-bios')->monthlyOn(1, '05:00');

        // Flush script/prompt text on dj_segments older than 60 days
        $schedule->command('djs:flush-old-scripts --days=60')->monthlyOn(1, '06:00');

        // Dispatch lyrics fetch jobs hourly — jobs self-throttle at 5s per request
        $schedule->command('lyrics:dispatch --limit=100')->hourly();

        // Nightly loudness scan — 5 passes staggered through the night, skips already-analyzed songs
        foreach (['01:00', '02:30', '04:00', '05:30', '07:00'] as $time) {
            $schedule->command('songs:dispatch-loudness-analysis --limit=200')->dailyAt($time)->withoutOverlapping();
        }

        // Nightly librosa audio feature scan (tempo/key/energy) — low-traffic window,
        // skips already-analyzed songs. ~1000/night clears the ~4800-song backlog in 4-5 days.
        $schedule->command('songs:dispatch-audio-features-analysis --limit=1000')->dailyAt('02:00')->withoutOverlapping();

        // Dispatch song mood analysis every 30m 
        /*Schedule::command('lyrics:dispatch-mood-analysis --limit=175')
            ->everyThirtyMinutes()
            ->withoutOverlapping();*/
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
