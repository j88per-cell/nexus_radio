<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Shows — the primary entity
        Schema::create('shows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('theme')->nullable();
            // manual: fixed tracklist; auto: station picks from library
            $table->string('mode')->default('auto');
            // draft, active, live, done
            $table->string('status')->default('draft');
            // higher priority preempts lower; free play = 0
            $table->unsignedSmallInteger('priority')->default(0);

            // Scheduling
            // once, interval, daily, weekly
            $table->string('recurrence')->default('once');
            $table->dateTime('scheduled_at')->nullable();          // once: exact datetime
            $table->time('start_time')->nullable();                // daily/weekly: time of day
            $table->unsignedSmallInteger('duration_minutes')->nullable(); // slot window; null = run until tracks exhaust
            $table->json('recurrence_days')->nullable();           // weekly: [0=Mon … 6=Sun]
            $table->unsignedSmallInteger('interval_hours')->nullable();   // interval: every N hours

            // Runtime state
            $table->dateTime('next_run_at')->nullable();           // scheduler triggers on this
            $table->dateTime('last_run_at')->nullable();
            $table->dateTime('live_until')->nullable();            // set when show goes live

            $table->timestamps();
        });

        // Tracks assigned to a manual show
        Schema::create('show_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->constrained('shows')->cascadeOnDelete();
            $table->foreignId('track_id')->constrained('tracks')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->unsignedInteger('duration_seconds');
            $table->timestamps();

            $table->unique(['show_id', 'position']);
            $table->unique(['show_id', 'track_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('show_tracks');
        Schema::dropIfExists('shows');
    }
};
