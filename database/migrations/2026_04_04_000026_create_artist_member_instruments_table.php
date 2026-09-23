<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artist_member_instruments', function (Blueprint $table) {
            $table->foreignId('artist_member_id')->constrained('artist_members')->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained('instruments')->cascadeOnDelete();
            $table->primary(['artist_member_id', 'instrument_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artist_member_instruments');
    }
};
