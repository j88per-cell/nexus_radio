<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('song_signals', function (Blueprint $table) {
            // Deterministic, templated structural summary derived from MSAF
            // boundary detection + duration-weighted RMS/tempo per segment
            // (see scripts/audio_features/extract.py). Nullable and populated
            // gradually by AnalyzeAudioFeaturesJob alongside the rest of
            // audio_analyzed — callers must treat a missing note as "not
            // analyzed yet", not an error, since the full-library backfill
            // takes real time to complete.
            $table->text('shape_note')->nullable()->after('audio_analyzed');
            $table->json('shape_note_meta')->nullable()->after('shape_note');
        });
    }

    public function down(): void
    {
        Schema::table('song_signals', function (Blueprint $table) {
            $table->dropColumn(['shape_note', 'shape_note_meta']);
        });
    }
};
