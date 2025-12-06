<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ZoneSpawnEntryRequest;
use App\Models\Zone;
use App\Models\ZoneSpawnEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class AdminZoneSpawnController extends Controller
{
    public function index(Zone $zone): View
    {
        $zone->load(['spawnEntries.species']);

        return view('admin.zones.spawns', compact('zone'));
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
}
