<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\Song;
use App\Models\SongTag;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SongController extends Controller
{
    public function index(Request $request): Response
    {
        $songs = Song::query()
            ->when($request->search, fn($q) => $q->where(function ($q2) use ($request) {
                $q2->whereRaw('LOWER(title) LIKE ?', ['%' . mb_strtolower($request->search) . '%']);
            }))
            ->when($request->artist, fn($q) => $q->whereHas('originalArtist', fn($q2) => $q2->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($request->artist) . '%'])))
            ->when($request->filter === 'no_lyrics',  fn($q) => $q->where(fn($q2) => $q2->whereNull('lyrics')->orWhere('lyrics', '')))
            ->when($request->filter === 'has_lyrics', fn($q) => $q->whereNotNull('lyrics')->where('lyrics', '!=', ''))
            ->when($request->filter === 'blocked',    fn($q) => $q->where('blocked', true))
            ->when($request->filter === 'no_shape_note',  fn($q) => $q->whereDoesntHave('signal', fn($q2) => $q2->whereNotNull('shape_note')))
            ->when($request->filter === 'has_shape_note', fn($q) => $q->whereHas('signal', fn($q2) => $q2->whereNotNull('shape_note')))
            ->with(['originalArtist', 'tags', 'tracks', 'signal'])
            ->orderBy('title')
            ->paginate(50)
            ->withQueryString()
            ->through(function ($s) {
                $track = $s->tracks->first();

                return [
                    'id'                 => $s->id,
                    'title'              => $s->title,
                    'artist'             => $s->originalArtist?->name,
                    'lyrics'             => $s->lyrics,
                    'has_lyrics'         => ! empty($s->lyrics),
                    'has_shape_note'     => $s->signal?->shape_note !== null,
                    'blocked'            => $s->blocked,
                    'duration_seconds'   => $track?->duration_seconds,
                    'tags'               => $s->tags->pluck('tag'),
                ];
            });

        return Inertia::render('Admin/Songs', [
            'songs'    => $songs,
            'filters'  => $request->only(['search', 'artist', 'filter']),
            'allTags'  => SongTag::ALL,
        ]);
    }

    public function update(Request $request, Song $song)
    {
        $data = $request->validate([
            'lyrics'           => 'nullable|string',
            'duration_seconds' => 'nullable|integer|min:0',
            'tags'             => 'nullable|array',
            'tags.*'           => 'in:' . implode(',', SongTag::ALL),
        ]);

        $song->update(['lyrics' => $data['lyrics'] ?? null]);

        if ($request->has('duration_seconds')) {
            $song->tracks()->first()?->update(['duration_seconds' => $data['duration_seconds']]);
        }

        if ($request->has('tags')) {
            $song->tags()->delete();
            $song->tags()->createMany(
                collect($data['tags'] ?? [])->map(fn($tag) => ['tag' => $tag])->all()
            );
        }

        return back();
    }

    public function toggleBlocked(Song $song)
    {
        $song->update(['blocked' => ! $song->blocked]);

        return back();
    }
}
