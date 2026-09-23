<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Pairs each "Hall of the Mountain King" song with the "Prelude to Madness"
     * song on the same release, since Savatage duplicates both songs across
     * several album/live versions and the pairing must stay per-release.
     */
    public function up(): void
    {
        $pairs = DB::table('songs as hall')
            ->join('tracks as hall_track', 'hall_track.song_id', '=', 'hall.id')
            ->join('tracks as prelude_track', 'prelude_track.release_id', '=', 'hall_track.release_id')
            ->join('songs as prelude', 'prelude.id', '=', 'prelude_track.song_id')
            ->where('hall.title', 'Hall of the Mountain King')
            ->where('prelude.title', 'Prelude to Madness')
            ->select('hall.id as hall_id', 'prelude.id as prelude_id')
            ->distinct()
            ->get();

        foreach ($pairs as $pair) {
            DB::table('songs')
                ->where('id', $pair->hall_id)
                ->update(['requires_predecessor_song_id' => $pair->prelude_id]);
        }
    }

    public function down(): void
    {
        DB::table('songs')
            ->where('title', 'Hall of the Mountain King')
            ->update(['requires_predecessor_song_id' => null]);
    }
};
