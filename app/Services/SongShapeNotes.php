<?php

namespace App\Services;

use App\Models\Track;

/**
 * Folds MSAF-derived structural "shape notes" (song_signals.shape_note) into
 * the free-text mood field Chorus's /transition and /hybrid_transition read.
 * Null-safe throughout: most songs won't have a shape note until the
 * AnalyzeAudioFeaturesJob backfill (songs:dispatch-audio-features-analysis)
 * reaches them, and that's expected to take real time to complete — see the
 * 2026-09-12 chat log for the live A/B test that validated feeding this into
 * `mood` actually changes DJ output (both hermes3:8b and, on Lexi's real
 * voice model, dolphin-mistral), not just in theory.
 */
class SongShapeNotes
{
    public static function forTransition(?Track $fromTrack, ?Track $toTrack): ?string
    {
        $parts = [];

        if ($note = $fromTrack?->song?->signal?->shape_note) {
            $parts[] = "Outgoing track's energy: {$note}";
        }

        if ($note = $toTrack?->song?->signal?->shape_note) {
            $parts[] = "Incoming track's energy: {$note}";
        }

        return $parts ? implode(' ', $parts) : null;
    }

    public static function appendToMood(string $mood, ?Track $fromTrack, ?Track $toTrack): string
    {
        $shapeText = self::forTransition($fromTrack, $toTrack);

        if (! $shapeText) {
            return $mood;
        }

        return trim($mood . ' ' . $shapeText);
    }
}
