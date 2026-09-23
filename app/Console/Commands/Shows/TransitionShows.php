<?php

namespace App\Console\Commands\Shows;

use App\Jobs\Radio\PickNextTrackJob;
use App\Models\QueueItem;
use App\Models\Show;
use App\Services\QueueService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class TransitionShows extends Command
{
    protected $signature   = 'shows:transition';
    protected $description = 'End any overdue live shows and start any newly due scheduled shows.';

    public function handle(QueueService $queueService): int
    {
        $now = Carbon::now();

        // ── 1. End any live show whose window has closed ──────────────────────
        $ending = Show::where('status', Show::STATUS_LIVE)
            ->whereNotNull('live_until')
            ->where('live_until', '<=', $now)
            ->get();

        foreach ($ending as $show) {
            $this->info("Ending overdue show: \"{$show->name}\"");
            $this->endShow($show, $now);
        }

        // ── 2. Start any due active show (highest priority wins) ──────────────
        // Load 5 minutes early so the queue clears before the show's true air time.
        $due = Show::where('status', Show::STATUS_ACTIVE)
            ->where('next_run_at', '<=', $now->copy()->addMinutes(5))
            ->orderByDesc('priority')
            ->orderBy('next_run_at')
            ->first();

        if (! $due) {
            $next = Show::where('status', Show::STATUS_ACTIVE)
                ->whereNotNull('next_run_at')
                ->orderBy('next_run_at')
                ->first();
            $msg = $next
                ? "No show due. Next: \"{$next->name}\" at {$next->next_run_at}"
                : 'No active shows scheduled.';
            $this->info($msg);
            Log::info("TransitionShows: {$msg}");
            return self::SUCCESS;
        }

        $currentlyLive = Show::where('status', Show::STATUS_LIVE)->first();

        if ($currentlyLive && $currentlyLive->priority >= $due->priority) {
            $msg = "Skipping \"{$due->name}\" — \"{$currentlyLive->name}\" is live at equal/higher priority ({$currentlyLive->priority}).";
            $this->info($msg);
            Log::info("TransitionShows: {$msg}");
            return self::SUCCESS;
        }

        if ($currentlyLive) {
            $this->endShow($currentlyLive, $now);
        }

        $this->startShow($due, $now, $queueService);

        return self::SUCCESS;
    }

    private function startShow(Show $show, Carbon $now, QueueService $queueService): void
    {
        $this->info("Starting \"{$show->name}\" (priority {$show->priority}, mode {$show->mode})");
        Log::info("TransitionShows: starting \"{$show->name}\" (priority {$show->priority})");

        // Clean break: wipe all pending queue items so the show takes over immediately
        // after the current song finishes (PUSHED/PLAYING items run to completion naturally)
        QueueItem::where('status', QueueItem::STATUS_PENDING)->delete();

        // For manual shows: push the tracklist as queue items with Navidrome stream URLs
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
            // auto: let PickNextTrackJob refill the queue
            PickNextTrackJob::dispatch();
        }

        // Compute live_until
        $liveUntil = null;
        if ($show->duration_minutes) {
            $liveUntil = $now->copy()->addMinutes($show->duration_minutes);
        } elseif ($show->mode === Show::MODE_MANUAL) {
            $totalSeconds = $show->tracks->sum('duration_seconds');
            // Add ~35s per DJ drop between tracks as a rough buffer
            $drops        = max(0, $show->tracks->count() - 1);
            $liveUntil    = $now->copy()->addSeconds($totalSeconds + $drops * 35);
        }

        $show->update([
            'status'       => Show::STATUS_LIVE,
            'last_run_at'  => $now,
            'live_until'   => $liveUntil,
            'next_run_at'  => $show->computeNextRunAt($now),
        ]);
    }

    private function endShow(Show $show, Carbon $now): void
    {
        Log::info("TransitionShows: ending \"{$show->name}\"");

        if ($show->recurrence === Show::RECURRENCE_ONCE) {
            $show->update([
                'status'     => Show::STATUS_DONE,
                'live_until' => null,
            ]);
        } else {
            // Repeating shows go back to active; next_run_at was already set at start time
            $show->update([
                'status'     => Show::STATUS_ACTIVE,
                'live_until' => null,
            ]);
        }

        // Kick the queue back to life if it's now empty
        $pending = QueueItem::where('status', QueueItem::STATUS_PENDING)->count();
        if ($pending === 0) {
            PickNextTrackJob::dispatch();
        }
    }
}
