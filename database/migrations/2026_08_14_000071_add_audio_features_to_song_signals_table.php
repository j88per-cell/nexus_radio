<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('song_signals', function (Blueprint $table) {
            // Raw librosa output. tempo/energy above stay the derived 1-5 display
            // scale; these are the precise values the derivation is computed from.
            $table->float('tempo_bpm')->nullable()->after('loudness_analyzed');
            $table->float('energy_raw')->nullable()->after('tempo_bpm');
            $table->string('key')->nullable()->after('energy_raw');
            $table->float('danceability')->nullable()->after('key');
            $table->json('mood_tags')->nullable()->after('danceability');
            $table->json('embedding')->nullable()->after('mood_tags');
            $table->boolean('audio_analyzed')->default(false)->after('embedding');
        });
    }

    public function down(): void
    {
        Schema::table('song_signals', function (Blueprint $table) {
            $table->dropColumn([
                'tempo_bpm',
                'energy_raw',
                'key',
                'danceability',
                'mood_tags',
                'embedding',
                'audio_analyzed',
            ]);
        });
    }
};
