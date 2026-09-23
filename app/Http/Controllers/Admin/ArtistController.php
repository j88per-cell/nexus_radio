<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\Genre;
use App\Models\Release;
use App\Models\Song;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ArtistController extends Controller
{
    public function index(Request $request)
    {
        $query = Artist::query()
            ->when($request->search, fn($q) => $q->where(function ($q2) use ($request) {
                $q2->where('name', 'like', "%{$request->search}%")
                   ->orWhere('origin', 'like', "%{$request->search}%");
            }))
            ->when($request->filter === 'no_bio', fn($q) => $q->whereNull('bio')->orWhere('bio', ''))
            ->when($request->filter === 'no_origin', fn($q) => $q->whereNull('origin')->orWhere('origin', ''))
            ->with('genres')
            ->withCount('releases')
            ->orderBy($request->sort === 'name' ? 'name' : 'name', 'asc');

        $artists = $query->with('influences')->paginate(50)->withQueryString()->through(fn($a) => [
            'id'             => $a->id,
            'name'           => $a->name,
            'origin'         => $a->origin,
            'formed_year'    => $a->formed_year,
            'disbanded_year' => $a->disbanded_year,
            'bio'            => $a->bio,
            'story'          => $a->story,
            'has_bio'        => ! empty($a->bio),
            'genres'         => $a->genres->map(fn($g) => ['id' => $g->id, 'name' => $g->name, 'primary' => (bool) $g->pivot->primary]),
            'genre'          => $a->genres->firstWhere('pivot.primary', true)?->name ?? $a->genres->first()?->name,
            'releases_count' => $a->releases_count,
            'influences'     => $a->influences->map(fn($i) => ['id' => $i->id, 'name' => $i->name]),
        ]);

        return Inertia::render('Admin/Artists', [
            'artists'   => $artists,
            'filters'   => $request->only(['search', 'filter']),
            'allGenres' => Genre::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function search(Request $request)
    {
        $q    = $request->q ?? '';
        $slug = \Illuminate\Support\Str::slug($q);

        $artists = Artist::where('name', 'ilike', "%{$q}%")
            ->orWhere('slug', 'like', "%{$slug}%")
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name']);

        return response()->json($artists);
    }

    public function merge(Request $request, Artist $loser)
    {
        $validated = $request->validate([
            'target_id' => "required|integer|exists:artists,id|not_in:{$loser->id}",
        ]);

        $winnerId = (int) $validated['target_id'];
        $loserId  = $loser->id;

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($loserId, $winnerId) {
                $now = now();

                // Reassign primary release artist
                \Illuminate\Support\Facades\DB::table('releases')
                    ->where('artist_id', $loserId)
                    ->update(['artist_id' => $winnerId]);

                // Reassign release_artists pivot
                \Illuminate\Support\Facades\DB::table('release_artists')
                    ->where('artist_id', $loserId)
                    ->whereNotIn('release_id', function ($q) use ($winnerId) {
                        $q->select('release_id')->from('release_artists')->where('artist_id', $winnerId);
                    })
                    ->update(['artist_id' => $winnerId]);
                \Illuminate\Support\Facades\DB::table('release_artists')
                    ->where('artist_id', $loserId)->delete();

                // Reassign songs
                \Illuminate\Support\Facades\DB::table('songs')
                    ->where('original_artist_id', $loserId)
                    ->update(['original_artist_id' => $winnerId]);

                // Merge genres
                \Illuminate\Support\Facades\DB::table('artist_genres')
                    ->where('artist_id', $loserId)
                    ->whereNotIn('genre_id', function ($q) use ($winnerId) {
                        $q->select('genre_id')->from('artist_genres')->where('artist_id', $winnerId);
                    })
                    ->update(['artist_id' => $winnerId]);
                \Illuminate\Support\Facades\DB::table('artist_genres')
                    ->where('artist_id', $loserId)->delete();

                // Merge members
                \Illuminate\Support\Facades\DB::table('artist_members')
                    ->where('artist_id', $loserId)
                    ->whereNotIn('person_id', function ($q) use ($winnerId) {
                        $q->select('person_id')->from('artist_members')->where('artist_id', $winnerId);
                    })
                    ->update(['artist_id' => $winnerId]);
                \Illuminate\Support\Facades\DB::table('artist_members')
                    ->where('artist_id', $loserId)->delete();

                // Clean up remaining pivots
                \Illuminate\Support\Facades\DB::table('artist_labels')->where('artist_id', $loserId)->delete();
                \Illuminate\Support\Facades\DB::table('artist_influences')
                    ->where('artist_id', $loserId)
                    ->orWhere('influenced_by_artist_id', $loserId)
                    ->delete();

                // Soft-delete the loser
                \Illuminate\Support\Facades\DB::table('artists')
                    ->where('id', $loserId)
                    ->update(['deleted_at' => $now]);
            });
        } catch (\Throwable $e) {
            Log::error("ArtistController::merge failed — {$e->getMessage()}", ['loser' => $loserId, 'winner' => $winnerId]);
            return back()->withErrors(['merge' => 'Merge failed: ' . $e->getMessage()]);
        }

        // Stamp audit note on winner
        $winner = Artist::find($winnerId);
        $mergeNote = "[Merged from: {$loser->name} (#{$loserId}) on " . now()->toDateString() . "]";
        \Illuminate\Support\Facades\DB::table('artists')
            ->where('id', $winnerId)
            ->update(['notes' => trim(($winner?->notes ? $winner->notes . "\n" : '') . $mergeNote)]);

        return redirect()->route('admin.artists');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'integer|exists:artists,id',
        ]);

        Artist::whereIn('id', $validated['ids'])->each(function (Artist $artist) {
            foreach ($artist->releases as $release) {
                $release->songs()->delete();
                $release->delete();
            }
            Song::where('original_artist_id', $artist->id)->delete();
            $artist->delete();
        });

        return redirect()->route('admin.artists');
    }

    public function storeInfluence(Request $request, Artist $artist)
    {
        $validated = $request->validate([
            'influence_id' => "required|integer|exists:artists,id|not_in:{$artist->id}",
        ]);

        $artist->influences()->syncWithoutDetaching([$validated['influence_id']]);

        return back();
    }

    public function destroyInfluence(Artist $artist, Artist $influence)
    {
        $artist->influences()->detach($influence->id);

        return back();
    }

    public function destroy(Artist $artist)
    {
        // Cascade soft-delete releases and their songs
        foreach ($artist->releases as $release) {
            $release->songs()->delete();
            $release->delete();
        }

        // Soft-delete any songs directly on the artist
        Song::where('original_artist_id', $artist->id)->delete();

        $artist->delete();

        return redirect()->route('admin.artists');
    }

    public function update(Request $request, Artist $artist)
    {
        $validated = $request->validate([
            'name'             => 'sometimes|required|string|max:255',
            'origin'           => 'sometimes|nullable|string|max:255',
            'formed_year'      => 'sometimes|nullable|integer|min:1800|max:2100',
            'disbanded_year'   => 'sometimes|nullable|integer|min:1800|max:2100',
            'bio'              => 'sometimes|nullable|string',
            'story'            => 'sometimes|nullable|string',
            'genre_ids'        => 'sometimes|nullable|array',
            'genre_ids.*'      => 'integer|exists:genres,id',
            'primary_genre_id' => 'sometimes|nullable|integer|exists:genres,id',
        ]);

        $artist->update(\Arr::except($validated, ['genre_ids', 'primary_genre_id']));

        if ($request->has('genre_ids')) {
            $primaryId = $validated['primary_genre_id'] ?? null;
            $syncData  = collect($validated['genre_ids'] ?? [])
                ->mapWithKeys(fn($id) => [$id => ['primary' => $id == $primaryId]])
                ->toArray();
            $artist->genres()->sync($syncData);
        }

        return back();
    }
}
