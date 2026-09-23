<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('release_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            // producer, engineer, mixer, mastering, artwork, photography, co_producer, etc.
            $table->string('role');
            $table->timestamps();

            $table->unique(['release_id', 'person_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_credits');
    }
};
