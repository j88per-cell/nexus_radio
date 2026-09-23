<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            // album, ep, single, live, compilation, demo
            $table->string('type')->default('album');
            $table->date('release_date')->nullable();
            $table->string('catalog_number')->nullable();
            // for live releases
            $table->string('event_name')->nullable();
            $table->string('venue')->nullable();
            $table->text('description')->nullable();
            $table->string('navidrome_album_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('ALTER TABLE releases ADD COLUMN embedding vector(768)');

        // additional artists on a release (features, compilations, splits)
        Schema::create('release_artists', function (Blueprint $table) {
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
            // primary, featuring, various
            $table->string('role')->default('featuring');

            $table->primary(['release_id', 'artist_id']);
        });

        Schema::create('release_labels', function (Blueprint $table) {
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();

            $table->primary(['release_id', 'label_id']);
        });

        Schema::create('release_genres', function (Blueprint $table) {
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->foreignId('genre_id')->constrained()->cascadeOnDelete();

            $table->primary(['release_id', 'genre_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_genres');
        Schema::dropIfExists('release_labels');
        Schema::dropIfExists('release_artists');
        Schema::dropIfExists('releases');
    }
};
