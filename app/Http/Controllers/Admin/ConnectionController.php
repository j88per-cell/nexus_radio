<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\Connection;
use App\Models\Person;
use App\Models\Release;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConnectionController extends Controller
{
    public function index(): Response
    {
        $connections = Connection::latest()
            ->paginate(50)
            ->through(fn($c) => [
                'id'          => $c->id,
                'from_type'   => $c->from_type,
                'from_id'     => $c->from_id,
                'to_type'     => $c->to_type,
                'to_id'       => $c->to_id,
                'type'        => $c->type,
                'type_label'  => Connection::typeLabel($c->type),
                'description' => $c->description,
                'year'        => $c->year,
                'from_name'   => $this->entityName($c->from_type, $c->from_id),
                'to_name'     => $this->entityName($c->to_type, $c->to_id),
            ]);

        return Inertia::render('Admin/Connections', [
            'connections' => $connections,
            'types'       => Connection::allTypes(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_type'   => 'required|in:artist,person',
            'from_id'     => 'required|integer',
            'to_type'     => 'required|in:artist,person,release,song',
            'to_id'       => 'required|integer',
            'type'        => 'required|string|in:' . implode(',', Connection::allTypes()),
            'description' => 'nullable|string|max:1000',
            'year'        => 'nullable|integer|min:1900|max:2100',
        ]);

        Connection::create($validated);

        return back();
    }

    public function destroy(Connection $connection)
    {
        $connection->delete();

        return back();
    }

    // Typeahead: searches artists and people together
    public function search(Request $request)
    {
        $q = $request->q ?? '';

        $artists = Artist::whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($q) . '%'])->limit(8)->get(['id', 'name', 'slug'])
            ->map(fn($a) => ['id' => $a->id, 'name' => $a->name, 'slug' => $a->slug, 'type' => 'artist']);

        $people = Person::whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($q) . '%'])->limit(8)->get(['id', 'name', 'slug'])
            ->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug, 'type' => 'person']);

        return response()->json($artists->concat($people)->sortBy('name')->values());
    }

    public function searchReleases(Request $request)
    {
        $q = $request->q ?? '';

        $releases = Release::whereRaw('LOWER(title) LIKE ?', ['%' . mb_strtolower($q) . '%'])
            ->with('artist')
            ->limit(10)
            ->get()
            ->map(fn($r) => ['id' => $r->id, 'name' => "{$r->artist->name} — {$r->title}", 'slug' => $r->slug, 'type' => 'release']);

        return response()->json($releases);
    }

    private function entityName(string $type, int $id): string
    {
        return match ($type) {
            'artist'  => Artist::find($id)?->name ?? "Unknown artist #{$id}",
            'person'  => Person::find($id)?->name ?? "Unknown person #{$id}",
            'release' => Release::with('artist')->find($id)?->title ?? "Unknown release #{$id}",
            default   => "#{$id}",
        };
    }
}
