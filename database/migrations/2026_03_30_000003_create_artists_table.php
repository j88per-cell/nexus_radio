<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            // band, solo, supergroup, project
            $table->string('type')->default('band');
            $table->smallInteger('formed_year')->nullable();
            $table->smallInteger('disbanded_year')->nullable();
            $table->string('origin')->nullable();
            $table->text('bio')->nullable();
            $table->text('story')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artists');
    }
};
