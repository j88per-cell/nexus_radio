<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            // When file_path was last confirmed to actually exist on disk —
            // distinct from file_path itself being non-null, since a path
            // can go stale (file moved/renamed/deleted) without anything
            // clearing it automatically. Null means "never confirmed."
            $table->timestamp('path_confirmed_at')->nullable()->after('file_path');
        });
    }

    public function down(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->dropColumn('path_confirmed_at');
        });
    }
};
