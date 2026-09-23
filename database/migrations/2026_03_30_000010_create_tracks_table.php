<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->foreignId('song_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->unsignedTinyInteger('disc')->default(1);
            $table->unsignedInteger('duration_seconds')->nullable();
            // cover is derivable: track.song.original_artist_id !== release.artist_id
            $table->string('navidrome_track_id')->nullable()->unique();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['release_id', 'disc', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracks');
    }
};
