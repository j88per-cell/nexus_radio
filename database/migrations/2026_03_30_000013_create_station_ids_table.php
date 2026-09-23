<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('station_ids', function (Blueprint $table) {
            $table->id();
            $table->text('script');
            $table->string('audio_path')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            // json array e.g. ["sign-on","promo","back-announce"]
            $table->json('tags')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('station_ids');
    }
};
