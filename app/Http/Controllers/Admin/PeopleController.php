<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArtistMember;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PeopleController extends Controller
{
    public function index(Request $request)
    {
        $q = trim($request->search ?? '');

        $people = Person::when($q, fn($query) => $query->where('name', 'ilike', "%{$q}%"))
            ->withCount('artistMemberships')
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString()
            ->through(fn($p) => [
                'id'                 => $p->id,
                'name'               => $p->name,
                'slug'               => $p->slug,
                'born'               => $p->born?->format('Y'),
                'died'               => $p->died?->format('Y'),
                'primary_instrument' => $p->primary_instrument,
                'bio'                => $p->bio,
                'story'              => $p->story,
                'memberships_count'  => $p->artist_memberships_count,
            ]);

        return Inertia::render('Admin/People', [
            'people'  => $people,
            'filters' => ['search' => $q],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'               => 'required|string|max:255',
            'born'               => 'nullable|integer|min:1800|max:2100',
            'died'               => 'nullable|integer|min:1800|max:2100',
            'primary_instrument' => 'nullable|string|max:100',
            'bio'                => 'nullable|string',
            'story'              => 'nullable|string',
        ]);

        Person::create([
            'name'               => $validated['name'],
            'slug'               => Str::slug($validated['name']),
            'born'               => isset($validated['born']) ? "{$validated['born']}-01-01" : null,
            'died'               => isset($validated['died']) ? "{$validated['died']}-01-01" : null,
            'primary_instrument' => $validated['primary_instrument'] ?? null,
            'bio'                => $validated['bio'] ?? null,
            'story'              => $validated['story'] ?? null,
        ]);

        return back();
    }

    public function update(Request $request, Person $person)
    {
        $validated = $request->validate([
            'name'               => 'sometimes|required|string|max:255',
            'born'               => 'nullable|integer|min:1800|max:2100',
            'died'               => 'nullable|integer|min:1800|max:2100',
            'primary_instrument' => 'nullable|string|max:100',
            'bio'                => 'nullable|string',
            'story'              => 'nullable|string',
        ]);

        $person->update([
            'name'               => $validated['name'] ?? $person->name,
            'born'               => isset($validated['born']) ? "{$validated['born']}-01-01" : null,
            'died'               => isset($validated['died']) ? "{$validated['died']}-01-01" : null,
            'primary_instrument' => $validated['primary_instrument'] ?? null,
            'bio'                => $validated['bio'] ?? null,
            'story'              => $validated['story'] ?? null,
        ]);

        return back();
    }

    public function merge(Request $request, Person $loser)
    {
        $validated = $request->validate([
            'target_id' => "required|integer|exists:people,id|not_in:{$loser->id}",
        ]);

        $winnerId = (int) $validated['target_id'];
        $loserId  = $loser->id;

        DB::transaction(function () use ($loserId, $winnerId) {
            // Merge memberships — skip if winner already has same artist
            ArtistMember::where('person_id', $loserId)
                ->whereNotIn('artist_id', function ($q) use ($winnerId) {
                    $q->select('artist_id')->from('artist_members')->where('person_id', $winnerId);
                })
                ->update(['person_id' => $winnerId]);
            ArtistMember::where('person_id', $loserId)->delete();

            // Reassign release credits
            DB::table('release_credits')
                ->where('person_id', $loserId)
                ->whereNotIn('release_id', function ($q) use ($winnerId) {
                    $q->select('release_id')->from('release_credits')->where('person_id', $winnerId);
                })
                ->update(['person_id' => $winnerId]);
            DB::table('release_credits')->where('person_id', $loserId)->delete();

            // Reassign song credits
            DB::table('song_credits')
                ->where('person_id', $loserId)
                ->update(['person_id' => $winnerId]);

            // Reassign connections
            DB::table('connections')->where('from_type', 'person')->where('from_id', $loserId)->update(['from_id' => $winnerId]);
            DB::table('connections')->where('to_type', 'person')->where('to_id', $loserId)->update(['to_id' => $winnerId]);

            // Soft-delete the loser
            DB::table('people')->where('id', $loserId)->update(['deleted_at' => now()]);
        });

        return back()->with('success', "Merged into winner ID {$winnerId}.");
    }

    public function search(Request $request)
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
