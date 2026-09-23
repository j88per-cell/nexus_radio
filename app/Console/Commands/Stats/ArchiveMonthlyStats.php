<?php

namespace App\Console\Commands\Stats;

use App\Models\MonthlyStat;
use App\Models\PlayHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ArchiveMonthlyStats extends Command
{
    protected $signature   = 'stats:archive-month {--month=} {--year=}';
    protected $description = 'Archive a month\'s play totals and top artists/genres into monthly_stats before the dashboard\'s live calendar-month figures roll over.';

    public function handle(): int
    {
        $target = Carbon::now();
        if ($this->option('month') && $this->option('year')) {
            $target = Carbon::create((int) $this->option('year'), (int) $this->option('month'), 1);
        } else {
            // Default: the month that just ended, since this runs on the 1st.
            $target = $target->subMonthNoOverflow();
        }

        $start = $target->copy()->startOfMonth();
        $end   = $target->copy()->endOfMonth();

        $totalPlays = PlayHistory::whereBetween('played_at', [$start, $end])->count();

        $topArtists = PlayHistory::join('tracks', 'play_history.track_id', '=', 'tracks.id')
            ->join('releases', 'tracks.release_id', '=', 'releases.id')
            ->join('artists', 'releases.artist_id', '=', 'artists.id')
            ->select('artists.name as artist', DB::raw('count(*) as count'))
            ->whereBetween('play_history.played_at', [$start, $end])
            ->whereNull('artists.deleted_at')
            ->groupBy('artists.id', 'artists.name')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->toArray();

        $topGenres = PlayHistory::join('tracks', 'play_history.track_id', '=', 'tracks.id')
            ->join('releases', 'tracks.release_id', '=', 'releases.id')
            ->join('release_genres', 'releases.id', '=', 'release_genres.release_id')
            ->join('genres', 'release_genres.genre_id', '=', 'genres.id')
            ->select('genres.name as genre', DB::raw('count(*) as count'))
            ->whereBetween('play_history.played_at', [$start, $end])
            ->groupBy('genres.id', 'genres.name')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->toArray();

        MonthlyStat::updateOrCreate(
            ['year' => $start->year, 'month' => $start->month],
            [
                'total_plays' => $totalPlays,
                'top_artists' => $topArtists,
                'top_genres'  => $topGenres,
            ]
        );

        $this->info("Archived {$start->format('F Y')}: {$totalPlays} plays.");

        return self::SUCCESS;
    }
}
