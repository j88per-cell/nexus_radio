<?php

namespace App\Console\Commands\Songs;

use App\Jobs\Songs\AnalyzeAudioFeaturesJob;
use App\Models\Song;
use Illuminate\Console\Command;

class DispatchAudioFeaturesAnalysis extends Command
{
    protected $signature = 'songs:dispatch-audio-features-analysis
                            {--limit=200 : Max songs to dispatch per run}
                            {--all : Re-analyze already-analyzed songs too}';

    protected $description = 'Dispatch AnalyzeAudioFeaturesJob for songs with a playable track but no librosa-derived audio features yet';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $eligible = Song::where('blocked', false)
            ->whereHas('tracks', fn($q) => $q->whereNotNull('navidrome_track_id'));

        // "Analyzed" now means audio_analyzed AND has a shape_note — songs
        // done under the pre-shape-note pipeline still need one more pass.
        $pending = (clone $eligible)
            ->when(! $this->option('all'), fn($q) => $q->whereDoesntHave('signal', fn($s) => $s->where('audio_analyzed', true)->whereNotNull('shape_note')));

        $total = $pending->count();
        $ids   = (clone $pending)->inRandomOrder()->limit($limit)->pluck('id');

        if ($ids->isEmpty()) {
            $this->info('All songs already have audio features. Use --all to force re-analysis.');
            return self::SUCCESS;
        }

        foreach ($ids as $songId) {
            AnalyzeAudioFeaturesJob::dispatch($songId);
        }

        $remaining = max(0, $total - $ids->count());
        $this->info("Dispatched {$ids->count()} audio features analysis jobs. ~{$remaining} remaining after this batch.");

        return self::SUCCESS;
    }
}
