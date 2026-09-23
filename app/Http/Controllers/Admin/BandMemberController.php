<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\ArtistMember;
use App\Models\Instrument;
use App\Models\Person;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BandMemberController extends Controller
{
    public function index(Request $request)
    {
        $q = trim($request->search ?? '');

        $artists = Artist::when($q, fn($query) => $query->where('name', 'ilike', "%{$q}%"))
            ->withCount('members')
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString()
            ->through(fn($a) => [
                'id'           => $a->id,
                'name'         => $a->name,
                'slug'         => $a->slug,
                'members_count'=> $a->members_count,
            ]);

        return Inertia::render('Admin/BandMembers', [
            'artists'     => $artists,
            'filters'     => ['search' => $q],
            'instruments' => Instrument::orderBy('category')->orderBy('name')->get(['id', 'name', 'category']),
        ]);
    }

    public function members(Artist $artist)
    {
        $artist->load([
            'members' => fn($q) => $q->with(['artistMemberships' => fn($q2) => $q2->where('artist_id', $artist->id)->with('instruments')])
                ->orderByPivot('start_year'),
        ]);

        return response()->json(
            $artist->members->map(function ($p) use ($artist) {
                $membership = $p->artistMemberships->firstWhere('artist_id', $artist->id);
                return [
                    'membership_id'    => $membership?->id,
                    'person_id'        => $p->id,
                    'person_name'      => $p->name,
                    'person_slug'      => $p->slug,
                    'start_year'       => $membership?->start_year,
                    'end_year'         => $membership?->end_year,
                    'departure_reason' => $membership?->departure_reason,
                    'notes'            => $membership?->notes,
                    'instrument_ids'   => $membership?->instruments->pluck('id') ?? [],
                    'instruments'      => $membership?->instruments->pluck('name') ?? [],
                    'is_current'       => is_null($membership?->end_year),
                ];
            })
        );
    }

    public function store(Request $request, Artist $artist)
    {
        $validated = $request->validate([
            'person_id'        => 'required|integer|exists:people,id',
            'start_year'       => 'nullable|integer|min:1900|max:2100',
            'end_year'         => 'nullable|integer|min:1900|max:2100',
            'departure_reason' => 'nullable|string|in:death,quit,fired,hiatus,project_ended',
            'notes'            => 'nullable|string|max:1000',
            'instrument_ids'   => 'nullable|array',
            'instrument_ids.*' => 'integer|exists:instruments,id',
        ]);

        // Prevent duplicate membership
        $existing = ArtistMember::where('artist_id', $artist->id)
            ->where('person_id', $validated['person_id'])
            ->first();

        if ($existing) {
            return back()->withErrors(['person_id' => 'This person is already a member of this band.']);
        }

        $membership = ArtistMember::create([
            'artist_id'        => $artist->id,
            'person_id'        => $validated['person_id'],
            'start_year'       => $validated['start_year'] ?? null,
            'end_year'         => $validated['end_year'] ?? null,
            'departure_reason' => $validated['departure_reason'] ?? null,
            'notes'            => $validated['notes'] ?? null,
        ]);

        if (!empty($validated['instrument_ids'])) {
            $membership->instruments()->sync($validated['instrument_ids']);
        }

        return back();
    }

    public function update(Request $request, ArtistMember $membership)
    {
        $validated = $request->validate([
            'start_year'       => 'nullable|integer|min:1900|max:2100',
            'end_year'         => 'nullable|integer|min:1900|max:2100',
            'departure_reason' => 'nullable|string|in:death,quit,fired,hiatus,project_ended',
            'notes'            => 'nullable|string|max:1000',
            'instrument_ids'   => 'nullable|array',
            'instrument_ids.*' => 'integer|exists:instruments,id',
        ]);

        $membership->update([
            'start_year'       => $validated['start_year'] ?? null,
            'end_year'         => $validated['end_year'] ?? null,
            'departure_reason' => $validated['departure_reason'] ?? null,
            'notes'            => $validated['notes'] ?? null,
        ]);

        $membership->instruments()->sync($validated['instrument_ids'] ?? []);

        return back();
    }

    public function destroy(ArtistMember $membership)
    {
        $membership->instruments()->detach();
        $membership->delete();

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
