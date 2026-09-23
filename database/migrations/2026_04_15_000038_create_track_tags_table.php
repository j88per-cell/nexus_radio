<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Purge song_tags and reset its sequence
        DB::table('song_tags')->truncate();
        DB::statement("ALTER SEQUENCE song_tags_id_seq RESTART WITH 1");

        Schema::create('track_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            // instrumental, live, acoustic, holiday, bonus, remix
            $table->string('tag');
            $table->timestamps();

            $table->unique(['track_id', 'tag']);
            $table->index('tag');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('track_tags');
    }
};
