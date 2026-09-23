<?php

namespace App\Console\Commands\Songs;

use App\Jobs\Songs\AnalyzeLoudnessJob;
use App\Models\Song;
use Illuminate\Console\Command;

class DispatchLoudnessAnalysis extends Command
{
    protected $signature = 'songs:dispatch-loudness-analysis
                            {--limit=200 : Max songs to dispatch per run}
                            {--all : Re-analyze already-analyzed songs too}';

    protected $description = 'Dispatch AnalyzeLoudnessJob for songs with a playable track but no loudness data yet';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $eligible = Song::whereHas('tracks', fn($q) => $q->whereNotNull('navidrome_track_id'));

        $pending = (clone $eligible)
            ->when(! $this->option('all'), fn($q) => $q->whereDoesntHave('signal', fn($s) => $s->where('loudness_analyzed', true)));

        $total = $pending->count();
        $ids   = (clone $pending)->inRandomOrder()->limit($limit)->pluck('id');

        if ($ids->isEmpty()) {
            $this->info('All songs already have loudness data. Use --all to force re-analysis.');
            return self::SUCCESS;
        }

        foreach ($ids as $songId) {
            AnalyzeLoudnessJob::dispatch($songId);
        }

        $remaining = max(0, $total - $ids->count());
        $this->info("Dispatched {$ids->count()} loudness analysis jobs. ~{$remaining} remaining after this batch.");

        return self::SUCCESS;
    }
}
