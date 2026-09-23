<?php

namespace App\Console\Commands\Radio;

use App\Models\QueueItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RadioRestart extends Command
{
    protected $signature = 'radio:restart
                            {--clear-queue : Also clear all pending/pushed queue items}
                            {--seed=3 : Number of songs to pre-queue on start}';

    protected $description = 'Stop and restart Liquidsoap (Supervisor-managed) and reseed the queue';

    public function handle(): int
    {
        // Liquidsoap runs under Supervisor (autorestart=true, as of 2026-08-30) —
        // supervisorctl is the only correct way to stop/start it now. A bare
        // pkill/nohup here would either get immediately relaunched out from
        // under this command, or start a second process fighting the
        // Supervisor-managed one over the same icecast ports.
        exec('supervisorctl stop liquidsoap 2>&1', $stopOutput, $stopCode);
        $this->info($stopCode === 0 ? 'Liquidsoap stopped.' : 'Liquidsoap was not running.');

        // Clean up in-flight state so the UI doesn't show a stale now-playing
        // track. STALLED, not PLAYED — it didn't finish, it was cut off.
        QueueItem::whereIn('status', [QueueItem::STATUS_PLAYING, QueueItem::STATUS_PUSHED])
            ->update(['status' => QueueItem::STATUS_STALLED]);

        Cache::forget('radio.free_play_paused');

        if ($this->option('clear-queue')) {
            $cleared = QueueItem::whereIn('status', [
                QueueItem::STATUS_PENDING,
                QueueItem::STATUS_PUSHED,
                QueueItem::STATUS_PLAYING,
            ])->delete();
            $this->info("Queue cleared ({$cleared} items removed).");
        }

        $this->call('radio:bootstrap', ['--seed' => $this->option('seed')]);

        exec('supervisorctl start liquidsoap 2>&1', $startOutput, $startCode);
        $this->info($startCode === 0 ? 'Liquidsoap started.' : 'Liquidsoap failed to start: ' . implode(' ', $startOutput));

        return Command::SUCCESS;
    }
}
