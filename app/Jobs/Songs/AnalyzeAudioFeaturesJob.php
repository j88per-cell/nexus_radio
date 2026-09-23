<?php

namespace App\Jobs\Songs;

use App\Models\Song;
use App\Models\SongSignal;
use App\Services\NavidromeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AnalyzeAudioFeaturesJob implements ShouldQueue
{
    use Queueable;

    // Bumped from 300s — MSAF boundary detection adds real time on top of
    // the existing ffmpeg pull + tempo/key/energy pass.
    public int $timeout = 420;
    public int $tries   = 2;

    // 1-5 buckets for the display-scale song_signals.tempo/energy columns.
    // Heuristic thresholds tuned for a metal-leaning catalog, not a general
    // one — revisit if genres broaden.
    private const TEMPO_BUCKETS  = [90 => 1, 110 => 2, 130 => 3, 160 => 4];
    private const ENERGY_BUCKETS = [0.05 => 1, 0.10 => 2, 0.15 => 3, 0.25 => 4];

    public function __construct(public readonly int $songId) {}

    public function handle(NavidromeService $navidrome): void
    {
        $song  = Song::with('tracks')->find($this->songId);
        $track = $song?->tracks->first(fn($t) => ! empty($t->navidrome_track_id));

        if (! $track) {
            Log::warning("AnalyzeAudioFeaturesJob: no playable track for song #{$this->songId}");
            return;
        }

        $tmpFile = storage_path('app/tmp/audio-features-' . Str::uuid() . '.wav');
        @mkdir(dirname($tmpFile), recursive: true);

        $url     = $navidrome->streamUrl($track->navidrome_track_id);
        $escaped = escapeshellarg($url);
        $out     = escapeshellarg($tmpFile);
        shell_exec("ffmpeg -y -i {$escaped} -ac 1 -ar 22050 {$out} 2>&1");

        if (! file_exists($tmpFile) || filesize($tmpFile) === 0) {
            Log::error("AnalyzeAudioFeaturesJob: ffmpeg produced no audio for song #{$this->songId}");
            @unlink($tmpFile);
            return;
        }

        $python  = base_path('scripts/audio_features/venv/bin/python');
        $script  = base_path('scripts/audio_features/extract.py');
        $output  = shell_exec(escapeshellarg($python) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($tmpFile) . ' 2>/dev/null');

        @unlink($tmpFile);

        $data = json_decode((string) $output, true);

        if (! $data || ! isset($data['tempo_bpm'])) {
            Log::error("AnalyzeAudioFeaturesJob: extraction failed for song #{$this->songId}", [
                'raw' => $output,
            ]);
            return;
        }

        SongSignal::updateOrCreate(
            ['song_id' => $this->songId],
            [
                'tempo_bpm'       => $data['tempo_bpm'],
                'energy_raw'      => $data['energy'],
                'key'             => $data['key'],
                'tempo'           => $this->bucket($data['tempo_bpm'], self::TEMPO_BUCKETS),
                'energy'          => $this->bucket($data['energy'], self::ENERGY_BUCKETS),
                'audio_analyzed'  => true,
                // Best-effort in extract.py — null until the MSAF-based shape
                // pass succeeds for this track, never blocks the fields above.
                'shape_note'      => $data['shape_note'] ?? null,
                'shape_note_meta' => $data['shape_note_meta'] ?? null,
            ]
        );

        Log::info("AnalyzeAudioFeaturesJob: analyzed \"{$song->title}\"", $data);
    }

    private function bucket(float $value, array $thresholds): int
    {
        $bucket = 5;

        foreach ($thresholds as $ceiling => $candidate) {
            if ($value < $ceiling) {
                $bucket = $candidate;
                break;
            }
        }

        return $bucket;
    }
}
