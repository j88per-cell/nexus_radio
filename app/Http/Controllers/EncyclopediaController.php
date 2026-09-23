<?php

namespace App\Http\Controllers;

use App\Models\Artist;
use App\Models\Connection;
use App\Models\Genre;
use App\Models\Person;
use App\Models\Release;
use App\Models\Song;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EncyclopediaController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim($request->q ?? '');

        $artists = [];
        $people  = [];

        if ($q !== '') {
            $artists = Artist::where('name', 'ilike', "%{$q}%")
                ->with('genres')
                ->withCount('releases')
                ->orderBy('name')
                ->limit(20)
                ->get()
                ->map(fn($a) => [
                    'id'             => $a->id,
                    'name'           => $a->name,
                    'slug'           => $a->slug,
                    'origin'         => $a->origin,
                    'formed_year'    => $a->formed_year,
                    'disbanded_year' => $a->disbanded_year,
                    'releases_count' => $a->releases_count,
                    'genre'          => $a->genres->firstWhere('pivot.primary', true)?->name ?? $a->genres->first()?->name,
                ]);

            $people = Person::where('name', 'ilike', "%{$q}%")
                ->withCount('artistMemberships')
                ->orderBy('name')
                ->limit(10)
                ->get()
                ->map(fn($p) => [
                    'id'    => $p->id,
                    'name'  => $p->name,
                    'slug'  => $p->slug,
                    'bands' => $p->artist_memberships_count,
                ]);
        }

        return Inertia::render('Encyclopedia/Index', [
            'query'   => $q,
            'artists' => $artists,
            'people'  => $people,
        ]);
    }

    public function artist(string $slug): Response
    {
        $artist = Artist::where('slug', $slug)->firstOrFail();

        $artist->load([
            'genres',
            'labels',
            'influences',
            'influencedArtists',
            'releases' => fn($q) => $q->with(['credits.person', 'genres', 'labels'])->orderBy('release_date'),
            'members'  => fn($q) => $q->with(['artistMemberships' => fn($q2) => $q2->where('artist_id', $artist->id)->with('instruments')])
                                      ->orderByPivot('start_year'),
        ]);

        // Split current vs alumni
        $currentMembers = $artist->members->filter(fn($p) => is_null($p->pivot->end_year))->values();
        $alumni         = $artist->members->filter(fn($p) => ! is_null($p->pivot->end_year))->values();

        // All connections involving this artist (either direction)
        $connectionsFrom = Connection::where('from_type', 'artist')->where('from_id', $artist->id)->get();
        $connectionsTo   = Connection::where('to_type', 'artist')->where('to_id', $artist->id)->get();

        $connections = $this->resolveConnections($connectionsFrom, $connectionsTo, 'artist', $artist->id);

        // Graph data for cytoscape
        $graph = $this->buildArtistGraph($artist, $currentMembers, $alumni, $connections);

        return Inertia::render('Encyclopedia/Artist', [
            'artist'         => [
                'id'             => $artist->id,
                'name'           => $artist->name,
                'slug'           => $artist->slug,
                'type'           => $artist->type,
                'formed_year'    => $artist->formed_year,
                'disbanded_year' => $artist->disbanded_year,
                'origin'         => $artist->origin,
                'bio'            => $artist->bio,
                'genres'         => $artist->genres->map(fn($g) => ['id' => $g->id, 'name' => $g->name, 'slug' => $g->slug, 'primary' => (bool) $g->pivot->primary]),
                'labels'         => $artist->labels->map(fn($l) => ['id' => $l->id, 'name' => $l->name, 'start_year' => $l->pivot->start_year, 'end_year' => $l->pivot->end_year]),
                'influences'     => $artist->influences->map(fn($a) => ['id' => $a->id, 'name' => $a->name, 'slug' => $a->slug]),
                'influenced'     => $artist->influencedArtists->map(fn($a) => ['id' => $a->id, 'name' => $a->name, 'slug' => $a->slug]),
            ],
            'currentMembers' => $this->formatMembers($currentMembers, $artist->id),
            'alumni'         => $this->formatMembers($alumni, $artist->id),
            'discography'    => $artist->releases->map(fn($r) => [
                'id'             => $r->id,
                'title'          => $r->title,
                'slug'           => $r->slug,
                'type'           => $r->type,
                'release_date'   => $r->release_date?->format('Y-m-d'),
                'year'           => $r->release_date?->year,
                'catalog_number' => $r->catalog_number,
                'labels'         => $r->labels->map(fn($l) => $l->name),
                'credits'        => $r->credits->map(fn($c) => [
                    'person_id'   => $c->person_id,
                    'person_name' => $c->person->name,
                    'person_slug' => $c->person->slug,
                    'role'        => $c->role,
                ]),
            ]),
            'connections'    => $connections,
            'graph'          => $graph,
        ]);
    }

    public function person(string $slug): Response
    {
        $person = Person::where('slug', $slug)->firstOrFail();

        $person->load([
            'artistMemberships.artist.genres',
            'artistMemberships.artist.releases',
            'artistMemberships.instruments',
            'releaseCredits.release.artist',
            'songCredits.song.releases.artist',
        ]);

        $connectionsFrom = Connection::where('from_type', 'person')->where('from_id', $person->id)->get();
        $connectionsTo   = Connection::where('to_type', 'person')->where('to_id', $person->id)->get();

        $connections = $this->resolveConnections($connectionsFrom, $connectionsTo, 'person', $person->id);

        // Group release credits by role
        $productionCredits = $person->releaseCredits
            ->groupBy('role')
            ->map(fn($credits, $role) => [
                'role'     => $role,
                'releases' => $credits->map(fn($c) => [
                    'id'    => $c->release->id,
                    'title' => $c->release->title,
                    'slug'  => $c->release->slug,
                    'year'  => $c->release->release_date?->year,
                    'artist_name' => $c->release->artist->name,
                    'artist_slug' => $c->release->artist->slug,
                ])->sortBy('year')->values(),
            ])
            ->values();

        return Inertia::render('Encyclopedia/Person', [
            'person' => [
                'id'                => $person->id,
                'name'              => $person->name,
                'slug'              => $person->slug,
                'born'              => $person->born?->format('Y'),
                'died'              => $person->died?->format('Y'),
                'bio'               => $person->bio,
                'story'             => $person->story,
                'primary_instrument'=> $person->primary_instrument,
            ],
            'memberships' => $person->artistMemberships->map(function ($m) {
                // Releases that overlap with this person's tenure
                $start = $m->start_year;
                $end   = $m->end_year;

                $releases = $m->artist->releases
                    ->filter(function ($r) use ($start, $end) {
                        $year = $r->release_date?->year;
                        if (! $year) return false;
                        if ($start && $year < $start) return false;
                        if ($end   && $year > $end)   return false;
                        return true;
                    })
                    ->sortBy(fn($r) => $r->release_date)
                    ->map(fn($r) => [
                        'id'    => $r->id,
                        'title' => $r->title,
                        'year'  => $r->release_date?->year,
                        'type'  => $r->type,
                        'slug'  => $r->slug,
                    ])
                    ->values();

                return [
                    'artist_id'        => $m->artist_id,
                    'artist_name'      => $m->artist->name,
                    'artist_slug'      => $m->artist->slug,
                    'artist_genre'     => $m->artist->genres->firstWhere('pivot.primary', true)?->name ?? $m->artist->genres->first()?->name,
                    'instruments'      => $m->instruments->pluck('name'),
                    'start_year'       => $m->start_year,
                    'end_year'         => $m->end_year,
                    'departure_reason' => $m->departure_reason,
                    'is_current'       => is_null($m->end_year),
                    'releases'         => $releases,
                ];
            })->sortByDesc('start_year')->values(),
            'productionCredits' => $productionCredits,
            'songCredits'       => $person->songCredits->map(fn($c) => [
                'role'      => $c->role,
                'song_id'   => $c->song_id,
                'song_title'=> $c->song->title,
            ])->sortBy('song_title')->values(),
            'connections' => $connections,
        ]);
    }

    public function genre(string $slug): Response
    {
        $genre = Genre::where('slug', $slug)->firstOrFail();

        $artists = $genre->artists()
            ->withCount('releases')
            ->orderBy('name')
            ->get()
            ->map(fn($a) => [
                'id'             => $a->id,
                'name'           => $a->name,
                'slug'           => $a->slug,
                'origin'         => $a->origin,
                'formed_year'    => $a->formed_year,
                'disbanded_year' => $a->disbanded_year,
                'releases_count' => $a->releases_count,
                'is_primary'     => (bool) $a->pivot->primary,
            ]);

        return Inertia::render('Encyclopedia/Genre', [
            'genre'   => [
                'id'          => $genre->id,
                'name'        => $genre->name,
                'slug'        => $genre->slug,
                'description' => $genre->description,
            ],
            'artists' => $artists,
        ]);
    }

    public function release(string $slug): Response
    {
        $release = Release::where('slug', $slug)
            ->with(['artist', 'labels', 'genres', 'credits.person'])
            ->firstOrFail();

        $release->load(['tracks' => fn($q) => $q->with(['song.originalArtist'])]);

        return Inertia::render('Encyclopedia/Release', [
            'release' => [
                'id'             => $release->id,
                'title'          => $release->title,
                'slug'           => $release->slug,
                'type'           => $release->type,
                'release_date'   => $release->release_date?->format('Y-m-d'),
                'year'           => $release->release_date?->year,
                'catalog_number' => $release->catalog_number,
                'description'    => $release->description,
                'artist'         => ['id' => $release->artist->id, 'name' => $release->artist->name, 'slug' => $release->artist->slug],
                'labels'         => $release->labels->map(fn($l) => $l->name),
                'genres'         => $release->genres->map(fn($g) => ['name' => $g->name, 'slug' => $g->slug]),
                'credits'        => $release->credits->map(fn($c) => [
                    'person_id'   => $c->person_id,
                    'person_name' => $c->person->name,
                    'person_slug' => $c->person->slug,
                    'role'        => $c->role,
                ]),
            ],
            'tracks' => $release->tracks->map(fn($t) => [
                'id'                   => $t->id,
                'position'             => $t->position,
                'disc'                 => $t->disc,
                'duration_formatted'   => $t->duration_formatted,
                'song_id'              => $t->song_id,
                'song_title'           => $t->song->title,
                'is_cover'             => $t->is_cover,
                'original_artist_name' => $t->song->originalArtist?->name,
                'original_artist_slug' => $t->song->originalArtist?->slug,
            ]),
        ]);
    }

    public function song(Song $song): Response
    {
        $song->load([
            'originalArtist',
            'credits.person',
            'mood',
            'tracks' => fn($q) => $q->with(['release.artist'])->join('releases', 'releases.id', '=', 'tracks.release_id')->orderBy('releases.release_date')->select('tracks.*'),
        ]);

        return Inertia::render('Encyclopedia/Song', [
            'song' => [
                'id'           => $song->id,
                'title'        => $song->title,
                'story'        => $song->story,
                'song_meaning' => $song->song_meaning,
                'mood'         => $song->mood?->name,
                'original_artist' => $song->originalArtist ? [
                    'id' => $song->originalArtist->id, 'name' => $song->originalArtist->name, 'slug' => $song->originalArtist->slug,
                ] : null,
                'credits' => $song->credits->map(fn($c) => [
                    'person_id'   => $c->person_id,
                    'person_name' => $c->person->name,
                    'person_slug' => $c->person->slug,
                    'role'        => $c->role,
                ]),
            ],
            'appearances' => $song->tracks->map(fn($t) => [
                'track_id'    => $t->id,
                'release_id'  => $t->release->id,
                'release_title' => $t->release->title,
                'release_slug'  => $t->release->slug,
                'release_type'  => $t->release->type,
                'year'          => $t->release->release_date?->year,
                'artist_name'   => $t->release->artist->name,
                'artist_slug'   => $t->release->artist->slug,
                'is_cover'      => $t->is_cover,
            ]),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function formatMembers($members, int $artistId): \Illuminate\Support\Collection
    {
        return $members->map(function ($p) use ($artistId) {
            $membership = $p->artistMemberships->firstWhere('artist_id', $artistId);
            return [
                'id'               => $p->id,
                'name'             => $p->name,
                'slug'             => $p->slug,
                'instruments'      => $membership?->instruments->pluck('name') ?? collect(),
                'start_year'       => $membership?->start_year,
                'end_year'         => $membership?->end_year,
                'departure_reason' => $membership?->departure_reason,
            ];
        });
    }

    private function resolveConnections($from, $to, string $selfType, int $selfId): array
    {
        $resolved = [];

        foreach ($from as $c) {
            $object = $this->loadEntity($c->to_type, $c->to_id);
            if (! $object) {
                continue;
            }
            $resolved[] = [
                'id'          => $c->id,
                'type'        => $c->type,
                'type_label'  => Connection::typeLabel($c->type),
                'description' => $c->description,
                'year'        => $c->year,
                'direction'   => 'from',
                'entity_type' => $c->to_type,
                'entity_id'   => $c->to_id,
                'entity_name' => $object['name'],
                'entity_slug' => $object['slug'] ?? null,
                'entity_href' => $this->entityHref($c->to_type, $object),
            ];
        }

        foreach ($to as $c) {
            $subject = $this->loadEntity($c->from_type, $c->from_id);
            if (! $subject) {
                continue;
            }
            $resolved[] = [
                'id'          => $c->id,
                'type'        => $c->type,
                'type_label'  => Connection::typeLabel($c->type),
                'description' => $c->description,
                'year'        => $c->year,
                'direction'   => 'to',
                'entity_type' => $c->from_type,
                'entity_id'   => $c->from_id,
                'entity_name' => $subject['name'],
                'entity_slug' => $subject['slug'] ?? null,
                'entity_href' => $this->entityHref($c->from_type, $subject),
            ];
        }

        return $resolved;
    }

    private function loadEntity(string $type, int $id): ?array
    {
        return match ($type) {
            'artist'  => Artist::find($id)?->only('id', 'name', 'slug'),
            'person'  => Person::find($id)?->only('id', 'name', 'slug'),
            'release' => Release::with('artist')->find($id) ? (function ($r) {
                return ['id' => $r->id, 'name' => $r->title, 'slug' => $r->slug, 'artist_slug' => $r->artist->slug ?? null];
            })(Release::with('artist')->find($id)) : null,
			'song'    => ($s = Song::find($id)) ? ['id' => $s->id, 'name' => $s->title, 'slug' => $s->slug ?? null] : null,
            default   => null,
        };
    }

    private function entityHref(string $type, array $entity): string
    {
        return match ($type) {
            'artist'  => '/encyclopedia/artists/' . $entity['slug'],
            'person'  => '/encyclopedia/people/' . $entity['slug'],
            'release' => '/encyclopedia/releases/' . $entity['slug'],
            'song'    => '/encyclopedia/songs/' . $entity['id'],
            default   => '#',
        };
    }

    private function buildArtistGraph(Artist $artist, $currentMembers, $alumni, array $connections): array
    {
        $nodes = [];
        $edges = [];

        // Center: the artist itself
        $nodes[] = ['data' => ['id' => "artist-{$artist->id}", 'label' => $artist->name, 'type' => 'center', 'href' => '']];

        // Genres
        foreach ($artist->genres as $genre) {
            $nodes[] = ['data' => ['id' => "genre-{$genre->id}", 'label' => $genre->name, 'type' => 'genre', 'href' => "/encyclopedia/genres/{$genre->slug}"]];
            $edges[]  = ['data' => ['source' => "artist-{$artist->id}", 'target' => "genre-{$genre->id}", 'label' => 'genre']];
        }

        // Current members
        foreach ($currentMembers as $p) {
            $nodes[] = ['data' => ['id' => "person-{$p['id']}", 'label' => $p['name'], 'type' => 'member', 'href' => "/encyclopedia/people/{$p['slug']}"]];
            $edges[]  = ['data' => ['source' => "artist-{$artist->id}", 'target' => "person-{$p['id']}", 'label' => 'member']];
        }

        // Alumni
        foreach ($alumni as $p) {
            $nodeId = "person-{$p['id']}";
            if (! collect($nodes)->firstWhere('data.id', $nodeId)) {
                $nodes[] = ['data' => ['id' => $nodeId, 'label' => $p['name'], 'type' => 'alumni', 'href' => "/encyclopedia/people/{$p['slug']}"]];
                $edges[]  = ['data' => ['source' => "artist-{$artist->id}", 'target' => $nodeId, 'label' => 'former member']];
            }
        }

        // Influences
        foreach ($artist->influences->take(5) as $inf) {
            $nodes[] = ['data' => ['id' => "artist-{$inf->id}", 'label' => $inf->name, 'type' => 'influence', 'href' => "/encyclopedia/artists/{$inf->slug}"]];
            $edges[]  = ['data' => ['source' => "artist-{$artist->id}", 'target' => "artist-{$inf->id}", 'label' => 'influenced by']];
        }

        // Custom connections
        foreach ($connections as $conn) {
            if ($conn['entity_type'] === 'artist') {
                $nodeId = "artist-{$conn['entity_id']}";
                if (! collect($nodes)->firstWhere('data.id', $nodeId)) {
                    $nodes[] = ['data' => ['id' => $nodeId, 'label' => $conn['entity_name'], 'type' => 'connected', 'href' => $conn['entity_href']]];
                }
                $edges[] = ['data' => ['source' => "artist-{$artist->id}", 'target' => $nodeId, 'label' => $conn['type_label']]];
            } elseif ($conn['entity_type'] === 'person') {
                $nodeId = "person-{$conn['entity_id']}";
                if (! collect($nodes)->firstWhere('data.id', $nodeId)) {
                    $nodes[] = ['data' => ['id' => $nodeId, 'label' => $conn['entity_name'], 'type' => 'person', 'href' => $conn['entity_href']]];
                }
                $edges[] = ['data' => ['source' => "artist-{$artist->id}", 'target' => $nodeId, 'label' => $conn['type_label']]];
            }
        }

        return compact('nodes', 'edges');
    }
}
