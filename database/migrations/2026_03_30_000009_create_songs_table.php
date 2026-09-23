<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('songs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            // the artist who originally wrote/performed this song
            // null = traditional/unknown
            $table->foreignId('original_artist_id')->nullable()->constrained('artists')->nullOnDelete();
            $table->text('lyrics')->nullable();
            $table->text('story')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('ALTER TABLE songs ADD COLUMN embedding vector(768)');

        Schema::create('song_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('song_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            // writer, composer, lyricist, producer
            $table->string('role');
            $table->timestamps();

            $table->unique(['song_id', 'person_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('song_credits');
        Schema::dropIfExists('songs');
    }
};
