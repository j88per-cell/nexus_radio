<?php

namespace App\Jobs\Songs;

use App\Models\Song;
use App\Models\SongSignal;
use App\Services\NavidromeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class AnalyzeLoudnessJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;
    public int $tries   = 2;

    public function __construct(public readonly int $songId) {}

    public function handle(NavidromeService $navidrome): void
    {
        $song  = Song::with('tracks')->find($this->songId);
        $track = $song?->tracks->first(fn($t) => ! empty($t->navidrome_track_id));

        if (! $track) {
            Log::warning("AnalyzeLoudnessJob: no playable track for song #{$this->songId}");
            return;
        }

        $url     = $navidrome->streamUrl($track->navidrome_track_id);
        $escaped = escapeshellarg($url);
        $output  = shell_exec("ffmpeg -i {$escaped} -af loudnorm=print_format=json -f null - 2>&1");

        if (! $output) {
            Log::error("AnalyzeLoudnessJob: ffmpeg produced no output for song #{$this->songId}");
            return;
        }

        // loudnorm JSON always contains "input_i" — match that block specifically
        // to avoid grabbing an earlier {…} from ffmpeg's stream/container info
        if (! preg_match('/\{[^{}]*"input_i"[^{}]*\}/s', $output, $matches)) {
            Log::error("AnalyzeLoudnessJob: loudnorm JSON not found for song #{$this->songId}", [
                'tail' => substr($output, -500),
            ]);
            return;
        }

        $data = json_decode($matches[0], true);

        if (! $data || ! isset($data['input_i'])) {
            Log::error("AnalyzeLoudnessJob: json_decode failed for song #{$this->songId}", [
                'raw' => $matches[0],
            ]);
            return;
        }

        SongSignal::updateOrCreate(
            ['song_id' => $this->songId],
            [
                'integrated_loudness' => (float) $data['input_i'],
                'true_peak'           => (float) ($data['input_tp'] ?? 0),
                'loudness_range'      => (float) ($data['input_lra'] ?? 0),
                'loudness_analyzed'   => true,
            ]
        );

        Log::info("AnalyzeLoudnessJob: analyzed \"{$song->title}\"", [
            'lufs' => $data['input_i'],
            'peak' => $data['input_tp'] ?? null,
            'lra'  => $data['input_lra'] ?? null,
        ]);
    }
}
