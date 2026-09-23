<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\Person;
use App\Models\Release;
use App\Models\ReleaseCredit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReleaseCreditController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim($request->search ?? '');

        $releases = Release::with(['artist', 'credits.person'])
            ->when($q, fn($query) => $query->where('title', 'ilike', "%{$q}%")
                ->orWhereHas('artist', fn($q2) => $q2->where('name', 'ilike', "%{$q}%")))
            ->orderBy('release_date', 'desc')
            ->paginate(30)
            ->withQueryString()
            ->through(fn($r) => [
                'id'          => $r->id,
                'title'       => $r->title,
                'year'        => $r->release_date?->year,
                'type'        => $r->type,
                'artist_name' => $r->artist->name,
                'artist_slug' => $r->artist->slug,
                'credits'     => $r->credits->map(fn($c) => [
                    'id'          => $c->id,
                    'person_id'   => $c->person_id,
                    'person_name' => $c->person->name,
                    'role'        => $c->role,
                ]),
            ]);

        return Inertia::render('Admin/ReleaseCredits', [
            'releases' => $releases,
            'filters'  => ['search' => $q],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'release_id' => 'required|integer|exists:releases,id',
            'person_id'  => 'required|integer|exists:people,id',
            'role'       => 'required|string|max:100',
        ]);

        ReleaseCredit::firstOrCreate($validated);

        return back();
    }

    public function destroy(ReleaseCredit $releaseCredit)
    {
        $releaseCredit->delete();

        return back();
    }

    public function searchPeople(Request $request)
    {
        $q = $request->q ?? '';

        return response()->json(
            Person::where('name', 'ilike', "%{$q}%")
                ->orderBy('name')
                ->limit(10)
                ->get(['id', 'name', 'slug'])
        );
    }
}
