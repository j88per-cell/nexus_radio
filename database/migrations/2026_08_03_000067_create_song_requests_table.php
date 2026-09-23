<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('song_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('song_id')->constrained('songs')->cascadeOnDelete();
            $table->foreignId('track_id')->constrained('tracks')->cascadeOnDelete();
            $table->foreignId('queue_item_id')->nullable()->constrained('queue_items')->nullOnDelete();
            $table->string('requested_by');
            $table->timestamps();

            $table->index(['song_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('song_requests');
    }
};
