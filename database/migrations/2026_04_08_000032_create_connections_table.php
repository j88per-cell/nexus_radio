<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connections', function (Blueprint $table) {
            $table->id();

            // Subject: who/what this connection is FROM
            $table->string('from_type'); // 'artist' | 'person'
            $table->unsignedBigInteger('from_id');

            // Object: who/what this connection is TO
            $table->string('to_type'); // 'artist' | 'person' | 'release' | 'song'
            $table->unsignedBigInteger('to_id');

            // toured_with | guest_appearance | tribute_song | named_after |
            // co_written | side_project | split_from | formed_from | collaboration | other
            $table->string('type');

            $table->text('description')->nullable();
            $table->unsignedSmallInteger('year')->nullable();

            $table->timestamps();

            $table->index(['from_type', 'from_id']);
            $table->index(['to_type', 'to_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connections');
    }
};
