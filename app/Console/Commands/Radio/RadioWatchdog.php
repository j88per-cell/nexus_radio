<?php

namespace App\Console\Commands\Radio;

use App\Models\QueueItem;
use App\Models\RadioHealthEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * External safety net for Liquidsoap going quiet without ever calling
 * /api/radio/next again — the 2026-08-30 dead-air incidents showed
 * radio.liq's own internal stall detection isn't sufficient alone: it
 * depends on Liquidsoap's scheduler still running at all, which isn't
 * guaranteed.
 *
 * First version of this command checked only Laravel's own bookkeeping (a
 * PUSHED record sitting unconfirmed) and restarted on that alone — which
 * interrupted at least two perfectly good songs the same day, because a
 * merely dropped/delayed track-started webhook (audio fine, confirmation
 * lost) looks identical to a real stall from the DB's point of view alone.
 * This version cross-checks Icecast's actual current stream title before
 * ever restarting: if the title has moved on since the last check, the
 * audio is fine regardless of what the DB thinks, and only the bookkeeping
 * gets fixed. Only a title that's genuinely frozen across two consecutive
 * checks (~2 minutes), *combined with* a stuck DB record, is treated as a
 * real stall.
 */
class RadioWatchdog extends Command
{
    protected $signature   = 'radio:watchdog';
    protected $description = 'Detect a stalled Liquidsoap (stuck PUSHED item, no track-started, no stream progress) and restart it';

    // Real margin above RadioController::PUSHED_STALL_AGE (30s) — give the
    // normal in-band reclaim (triggered by Liquidsoap's own next /next call)
    // a chance to resolve this on its own first.
    private const WATCHDOG_STALL_AGE = 60;

    private const TITLE_CACHE_KEY = 'radio.watchdog.last_title';

    public function handle(): int
    {
        $stuck = QueueItem::whereIn('status', [QueueItem::STATUS_PUSHED, QueueItem::STATUS_STALLED])
            ->where('pushed_at', '<', now()->subSeconds(self::WATCHDOG_STALL_AGE))
            ->orderByDesc('pushed_at')
            ->first();

        if (! $stuck) {
            Cache::forget(self::TITLE_CACHE_KEY);
            return self::SUCCESS;
        }

        $currentTitle = $this->currentIcecastTitle();
        $lastSeenTitle = Cache::get(self::TITLE_CACHE_KEY);

        // No title to compare against (Icecast unreachable) — fall back to
        // the DB-only signal rather than never being able to recover at all.
        $titleHasMoved = $currentTitle !== null && $currentTitle !== $lastSeenTitle;

        if ($currentTitle !== null) {
            Cache::put(self::TITLE_CACHE_KEY, $currentTitle, now()->addMinutes(10));
        }

        if ($titleHasMoved) {
            // Audio actually advanced since last check — the DB record is
            // stale bookkeeping (a missed/delayed webhook), not a real
            // stall. Fix the record, leave the stream alone.
            $stuck->update(['status' => QueueItem::STATUS_PLAYED, 'played_at' => now()]);
            Log::info('RadioWatchdog: stream had already advanced despite stuck record, fixed bookkeeping without restarting', [
                'queue_item_id' => $stuck->id,
                'current_title' => $currentTitle,
            ]);
            return self::SUCCESS;
        }

        // Title unchanged since last check (or unavailable) AND the DB
        // independently thinks something's stuck — both signals agree,
        // this is a real stall.
        Log::warning('RadioWatchdog: Liquidsoap appears genuinely stalled, restarting', [
            'queue_item_id' => $stuck->id,
            'pushed_at'     => $stuck->pushed_at,
            'stalled_for'   => now()->diffInSeconds($stuck->pushed_at),
            'current_title' => $currentTitle,
        ]);

        RadioHealthEvent::create([
            'event'       => RadioHealthEvent::EVENT_WATCHDOG_RESTART,
            'occurred_at' => now(),
        ]);

        $stuck->update(['status' => QueueItem::STATUS_STALLED]);

        exec('supervisorctl restart liquidsoap 2>&1', $output, $code);
        $this->info($code === 0
            ? 'Liquidsoap restarted.'
            : 'supervisorctl restart failed: ' . implode(' ', $output));

        return self::SUCCESS;
    }

    private function currentIcecastTitle(): ?string
    {
        try {
            $response = Http::timeout(5)->get('http://127.0.0.1:12000/status-json.xsl');
            if (! $response->successful()) {
                return null;
            }

            $sources = $response->json('icestats.source');
            $source  = isset($sources[0]) ? $sources[0] : $sources; // single source isn't wrapped in an array
            return $source['title'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('RadioWatchdog: could not reach Icecast for title check', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
