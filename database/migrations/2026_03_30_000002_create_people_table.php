<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->date('born')->nullable();
            $table->date('died')->nullable();
            $table->string('primary_instrument')->nullable();
            $table->text('bio')->nullable();
            $table->text('story')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('ALTER TABLE people ADD COLUMN embedding vector(768)');
    }

    public function down(): void
    {
        Schema::dropIfExists('people');
    }
};
