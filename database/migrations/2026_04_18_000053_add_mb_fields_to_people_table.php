<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->string('mb_id')->nullable()->unique()->after('slug');
            $table->timestamp('mb_fetched_at')->nullable()->after('mb_id');
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn(['mb_id', 'mb_fetched_at']);
        });
    }
};
