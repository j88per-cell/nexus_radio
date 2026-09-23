<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('genres', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('artist_genres', function (Blueprint $table) {
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('genre_id')->constrained()->cascadeOnDelete();
            $table->boolean('primary')->default(false);

            $table->primary(['artist_id', 'genre_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artist_genres');
        Schema::dropIfExists('genres');
    }
};
