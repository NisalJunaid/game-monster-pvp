<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ZoneSpawnEntryRequest;
use App\Http\Requests\Admin\ZoneSpawnGenerateRequest;
use App\Domain\Encounters\ZoneSpawnGenerator;
use App\Models\MonsterSpecies;
use App\Models\Type;
use App\Models\Zone;
use App\Models\ZoneSpawnEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class AdminZoneSpawnController extends Controller
{
    public function __construct(private readonly ZoneSpawnGenerator $generator)
    {
    }

    public function index(Zone $zone): View
    {
        $zone->load(['spawnEntries.species']);

        $species = MonsterSpecies::orderBy('name')->get();
        $types = Type::orderBy('name')->get();

        return view('admin.zones.spawns', compact('zone', 'species', 'types'));
    }

    public function store(ZoneSpawnEntryRequest $request, Zone $zone): RedirectResponse
    {
        $zone->spawnEntries()->create($request->validated());

        return redirect()->route('admin.zones.spawns.index', $zone)->with('status', 'Spawn entry created.');
    }

    public function update(ZoneSpawnEntryRequest $request, Zone $zone, ZoneSpawnEntry $spawnEntry): RedirectResponse
    {
        if ($spawnEntry->zone_id !== $zone->id) {
            abort(404);
        }

        $spawnEntry->update($request->validated());

        return redirect()->route('admin.zones.spawns.index', $zone)->with('status', 'Spawn entry updated.');
    }

    public function destroy(Zone $zone, ZoneSpawnEntry $spawnEntry): RedirectResponse
    {
        if ($spawnEntry->zone_id !== $zone->id) {
            abort(404);
        }

        $spawnEntry->delete();

        return redirect()->route('admin.zones.spawns.index', $zone)->with('status', 'Spawn entry removed.');
    }

    public function generate(ZoneSpawnGenerateRequest $request, Zone $zone): RedirectResponse
    {
        $rules = $request->validated();
        $replace = $request->boolean('replace_existing', true);

        $zoneTypes = $zone->spawn_rules['types'] ?? [];
        if (empty($rules['types']) && ! empty($zoneTypes)) {
            $rules['types'] = $zoneTypes;
            $rules['pool_mode'] = $rules['pool_mode'] === 'any' ? 'type_based' : $rules['pool_mode'];
        }

        $generated = $this->generator->generate($zone, $rules, $replace);

        $message = $generated->isEmpty()
            ? 'No species matched the generator filters.'
            : 'Spawn entries generated successfully.';

        return redirect()->route('admin.zones.spawns.index', $zone)->with('status', $message);
    }
}
