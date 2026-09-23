<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('play_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->timestamp('played_at');

            $table->index('played_at');
            $table->index('track_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('play_history');
    }
};
