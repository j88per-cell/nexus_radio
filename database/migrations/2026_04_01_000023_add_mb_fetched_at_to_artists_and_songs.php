<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            $table->timestamp('mb_fetched_at')->nullable()->after('bio_fetched');
        });

        Schema::table('songs', function (Blueprint $table) {
            $table->timestamp('mb_fetched_at')->nullable()->after('lyrics_fetched');
        });
    }

    public function down(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            $table->dropColumn('mb_fetched_at');
        });

        Schema::table('songs', function (Blueprint $table) {
            $table->dropColumn('mb_fetched_at');
        });
    }
};
