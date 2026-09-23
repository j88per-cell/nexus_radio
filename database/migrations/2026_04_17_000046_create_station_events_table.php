<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('station_events', function (Blueprint $table) {
            $table->id();
            $table->text('description');
            // positive, negative, neutral
            $table->string('mood_impact', 16);
            $table->unsignedTinyInteger('magnitude');
            $table->float('probability')->default(0.05);
            $table->boolean('active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('station_events');
    }
};
