<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('song_signals', function (Blueprint $table) {
            $table->float('integrated_loudness')->nullable()->after('tempo')->comment('LUFS (integrated)');
            $table->float('true_peak')->nullable()->after('integrated_loudness')->comment('dBTP');
            $table->float('loudness_range')->nullable()->after('true_peak')->comment('LRA in LU');
            $table->boolean('loudness_analyzed')->default(false)->after('loudness_range');
        });
    }

    public function down(): void
    {
        Schema::table('song_signals', function (Blueprint $table) {
            $table->dropColumn(['integrated_loudness', 'true_peak', 'loudness_range', 'loudness_analyzed']);
        });
    }
};
