<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Release;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReleaseController extends Controller
{
    public function index(Request $request): Response
    {
        $releases = Release::query()
            ->when($request->search, fn($q) => $q->whereRaw('LOWER(title) LIKE ?', ['%' . mb_strtolower($request->search) . '%']))
            ->when($request->artist, fn($q) => $q->whereHas('artist', fn($q2) => $q2->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($request->artist) . '%'])))
            ->when($request->filter === 'blocked', fn($q) => $q->where('blocked', true))
            ->with('artist')
            ->orderBy('title')
            ->paginate(50)
            ->withQueryString()
            ->through(fn($r) => [
                'id'           => $r->id,
                'title'        => $r->title,
                'slug'         => $r->slug,
                'artist'       => $r->artist?->name,
                'type'         => $r->type,
                'release_date' => $r->release_date?->format('Y'),
                'blocked'      => $r->blocked,
            ]);

        return Inertia::render('Admin/Releases', [
            'releases' => $releases,
            'filters'  => $request->only(['search', 'artist', 'filter']),
        ]);
    }

    public function toggleBlocked(Release $release)
    {
        $release->update(['blocked' => ! $release->blocked]);

        return back();
    }
}
