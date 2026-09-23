<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('song_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('song_id')->unique()->constrained('songs')->cascadeOnDelete();
            // 1-5 scale; stubbed pending AcousticBrainz/Essentia data audit
            $table->unsignedTinyInteger('energy')->nullable();
            $table->unsignedTinyInteger('tempo')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('song_signals');
    }
};
