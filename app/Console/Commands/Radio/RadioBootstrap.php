<?php

namespace App\Console\Commands\Radio;

use App\Jobs\Radio\PickNextTrackJob;
use App\Models\QueueItem;
use Illuminate\Console\Command;

class RadioBootstrap extends Command
{
    protected $signature = 'radio:bootstrap
                            {--seed=3 : Number of songs to pre-queue}';

    protected $description = 'Seed the queue to get the radio started';

    public function handle(): int
    {
        // Clear any stale pending/pushed items from a previous session
        $cleared = QueueItem::whereIn('status', [
            QueueItem::STATUS_PENDING,
            QueueItem::STATUS_PUSHED,
        ])->delete();

        if ($cleared) {
            $this->info("Cleared {$cleared} stale queue items.");
        }

        $seed = (int) $this->option('seed');
        $this->info("Pre-queuing {$seed} songs...");

        for ($i = 0; $i < $seed; $i++) {
            // No DJ segments during bootstrap — no "current song" context yet
            PickNextTrackJob::dispatchSync();
        }

        $seeded = QueueItem::where('status', QueueItem::STATUS_PENDING)->count();
        $this->info("Done. {$seeded} items queued and ready.");

        return Command::SUCCESS;
    }
}
