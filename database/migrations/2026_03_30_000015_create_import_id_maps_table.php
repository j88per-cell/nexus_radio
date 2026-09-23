<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_id_maps', function (Blueprint $table) {
            $table->string('entity_type', 50);
            $table->string('old_id', 255);
            $table->unsignedBigInteger('new_id');

            $table->primary(['entity_type', 'old_id']);
            $table->index(['entity_type', 'new_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_id_maps');
    }
};
