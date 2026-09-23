<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_items', function (Blueprint $table) {
            $table->id();
            // song, dj, ad
            $table->string('type')->default('song');
            // pending → pushed → playing → played
            $table->string('status')->default('pending');
            $table->foreignId('track_id')->nullable()->constrained('tracks')->nullOnDelete();
            $table->string('audio_path')->nullable();
            $table->timestamp('pushed_at')->nullable();
            $table->timestamp('played_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_items');
    }
};
