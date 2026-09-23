<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('radio_health_events', function (Blueprint $table) {
            $table->id();
            // 'stalled' or 'recovered' — Liquidsoap's periodic health check
            // reports a transition, not every poll, so consecutive rows
            // always alternate.
            $table->string('event', 16);
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radio_health_events');
    }
};
