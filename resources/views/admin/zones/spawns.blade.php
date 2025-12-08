@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">Spawn Entries for {{ $zone->name }}</h1>
            <p class="text-gray-600">Manage which species appear in this zone and quickly generate spawn tables.</p>
        </div>
        <a href="{{ route('admin.zones.map') }}" class="text-teal-600 underline">&larr; Back to Zones</a>
    </div>

    <div class="grid lg:grid-cols-2 gap-4">
        <div class="bg-white shadow rounded p-4 space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold">Zone details</h2>
                <span class="px-2 py-1 rounded text-xs {{ $zone->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600' }}">
                    {{ $zone->is_active ? 'Active' : 'Inactive' }}
                </span>
            </div>
            <div class="text-sm text-gray-700 space-y-1">
                <p><strong>Priority:</strong> {{ $zone->priority }}</p>
                <p><strong>Spawn strategy:</strong> {{ ucfirst($zone->spawn_strategy ?? 'manual') }}</p>
                @php($preferredTypes = collect($zone->spawn_rules['types'] ?? []))
                <p><strong>Preferred types:</strong> {{ $preferredTypes->isEmpty() ? 'None selected' : $types->whereIn('id', $preferredTypes)->pluck('name')->join(', ') }}</p>
                <p class="text-xs text-gray-500">Non-manual zones auto-generate spawn tables after each save so encounters always have monsters available.</p>
            </div>
        </div>

        <div class="bg-white shadow rounded p-4">
            <h2 class="text-xl font-semibold mb-3">Add spawn entry</h2>
            <form method="POST" action="{{ route('admin.zones.spawns.store', $zone) }}" class="space-y-3">
                @csrf
                <div class="grid md:grid-cols-2 gap-3">
                    <label class="text-sm font-medium text-gray-700">Species
                        <select name="species_id" class="w-full border rounded px-2 py-1" required>
                            <option value="">Select a species...</option>
                            @foreach($species as $s)
                                <option value="{{ $s->id }}">#{{ $s->id }} - {{ $s->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm font-medium text-gray-700">Weight
                        <input type="number" name="weight" value="50" min="1" class="w-full border rounded px-2 py-1" required>
                    </label>
                    <label class="text-sm font-medium text-gray-700">Min level
                        <input type="number" name="min_level" value="1" min="1" class="w-full border rounded px-2 py-1" required>
                    </label>
                    <label class="text-sm font-medium text-gray-700">Max level
                        <input type="number" name="max_level" value="5" min="1" class="w-full border rounded px-2 py-1" required>
                    </label>
                    <label class="text-sm font-medium text-gray-700 col-span-full">Rarity tier (optional)
                        <input type="text" name="rarity_tier" class="w-full border rounded px-2 py-1" placeholder="common">
                    </label>
                </div>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Add entry</button>
            </form>
        </div>

        <div class="bg-white shadow rounded p-4">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold mb-3">Bulk add spawn entries</h2>
                <button type="button" id="add-bulk-row" class="px-3 py-1 text-sm bg-gray-200 rounded">+ Add row</button>
            </div>
            <p class="text-sm text-gray-600 mb-2">Quickly add several species with their own weights and level ranges.</p>
            <form method="POST" action="{{ route('admin.zones.spawns.store-bulk', $zone) }}" class="space-y-3" id="bulk-form">
                @csrf
                <div class="space-y-2" id="bulk-rows">
                    <div class="grid md:grid-cols-5 gap-2 bulk-row">
                        <label class="text-sm font-medium text-gray-700 md:col-span-2">Species
                            <select name="entries[0][species_id]" class="w-full border rounded px-2 py-1" required>
                                <option value="">Select a species...</option>
                                @foreach($species as $s)
                                    <option value="{{ $s->id }}">#{{ $s->id }} - {{ $s->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-sm font-medium text-gray-700">Weight
                            <input type="number" name="entries[0][weight]" value="50" min="1" class="w-full border rounded px-2 py-1" required>
                        </label>
                        <label class="text-sm font-medium text-gray-700">Min lvl
                            <input type="number" name="entries[0][min_level]" value="1" min="1" class="w-full border rounded px-2 py-1" required>
                        </label>
                        <label class="text-sm font-medium text-gray-700">Max lvl
                            <input type="number" name="entries[0][max_level]" value="5" min="1" class="w-full border rounded px-2 py-1" required>
                        </label>
                        <label class="text-sm font-medium text-gray-700 md:col-span-3">Rarity tier (optional)
                            <input type="text" name="entries[0][rarity_tier]" class="w-full border rounded px-2 py-1" placeholder="common">
                        </label>
                    </div>
                </div>
                <button type="submit" class="px-4 py-2 bg-teal-600 text-white rounded">Save bulk entries</button>
            </form>
        </div>
    </div>

    <div class="bg-white shadow rounded p-4">
        <h2 class="text-xl font-semibold mb-3">Random spawn generator</h2>
        <p class="text-sm text-gray-600 mb-2">This generator respects zone-level preferred types when you leave the type selector empty.</p>
        <form method="POST" action="{{ route('admin.zones.spawns.generate', $zone) }}" class="space-y-3">
            @csrf
            <div class="grid md:grid-cols-3 gap-3">
                <label class="text-sm font-medium text-gray-700">Pool mode
                    <select name="pool_mode" class="w-full border rounded px-2 py-1">
                        <option value="any">Any species</option>
                        <option value="type_based">Type-based</option>
                        <option value="rarity_based">Rarity-based</option>
                    </select>
                </label>
                <label class="text-sm font-medium text-gray-700">Number of species
                    <input type="number" name="num_species" value="8" min="1" max="50" class="w-full border rounded px-2 py-1" required>
                </label>
                <label class="text-sm font-medium text-gray-700">Replace existing entries
                    <input type="checkbox" name="replace_existing" value="1" checked class="ml-2 align-middle">
                </label>
            </div>

            <div class="grid md:grid-cols-2 gap-3">
                <label class="text-sm font-medium text-gray-700">Types (for type-based mode)
                    <select name="types[]" multiple class="w-full border rounded px-2 py-1 h-32">
                        @foreach($types as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                    <span class="text-xs text-gray-500">Use Ctrl/Cmd+Click to select multiple types.</span>
                </label>
                <label class="text-sm font-medium text-gray-700">Rarity tiers (for rarity-based mode)
                    <input type="text" name="rarity_tiers[]" class="w-full border rounded px-2 py-1" placeholder="common, rare">
                    <span class="text-xs text-gray-500">Separate multiple tiers with commas.</span>
                </label>
            </div>

            <div class="grid md:grid-cols-4 gap-3">
                <label class="text-sm font-medium text-gray-700">Level min
                    <input type="number" name="level_min" value="1" min="1" class="w-full border rounded px-2 py-1" required>
                </label>
                <label class="text-sm font-medium text-gray-700">Level max
                    <input type="number" name="level_max" value="8" min="1" class="w-full border rounded px-2 py-1" required>
                </label>
                <label class="text-sm font-medium text-gray-700">Weight min
                    <input type="number" name="weight_min" value="10" min="1" class="w-full border rounded px-2 py-1" required>
                </label>
                <label class="text-sm font-medium text-gray-700">Weight max
                    <input type="number" name="weight_max" value="100" min="1" class="w-full border rounded px-2 py-1" required>
                </label>
            </div>

            <p class="text-xs text-gray-500">Weights will be normalized to a total of 1000 to keep encounter odds consistent.</p>
            <button type="submit" class="px-4 py-2 bg-teal-600 text-white rounded">Generate spawns</button>
        </form>
    </div>

    <div class="bg-white shadow rounded p-4">
        <h2 class="text-xl font-semibold mb-3">Existing entries</h2>
        <div class="overflow-x-auto">
            <table class="w-full border border-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="border p-2">ID</th>
                        <th class="border p-2">Species</th>
                        <th class="border p-2">Weight</th>
                        <th class="border p-2">Level range</th>
                        <th class="border p-2">Rarity</th>
                        <th class="border p-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($zone->spawnEntries as $entry)
                        <tr class="border-t">
                            <td class="border p-2">{{ $entry->id }}</td>
                            <td class="border p-2">{{ $entry->species->name ?? ('#'.$entry->species_id) }}</td>
                            <td class="border p-2">{{ $entry->weight }}</td>
                            <td class="border p-2">{{ $entry->min_level }} - {{ $entry->max_level }}</td>
                            <td class="border p-2">{{ $entry->rarity_tier ?? 'n/a' }}</td>
                            <td class="border p-2">
                                <div class="space-y-2">
                                    <form method="POST" action="{{ route('admin.zones.spawns.update', [$zone, $entry]) }}" class="space-y-2">
                                        @csrf
                                        @method('PUT')
                                        <div class="grid md:grid-cols-5 gap-2">
                                            <select name="species_id" class="border rounded px-2 py-1">
                                                @foreach($species as $s)
                                                    <option value="{{ $s->id }}" @selected($s->id === $entry->species_id)>
                                                        #{{ $s->id }} - {{ $s->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <input type="number" name="weight" value="{{ $entry->weight }}" min="1" class="border rounded px-2 py-1">
                                            <input type="number" name="min_level" value="{{ $entry->min_level }}" min="1" class="border rounded px-2 py-1">
                                            <input type="number" name="max_level" value="{{ $entry->max_level }}" min="1" class="border rounded px-2 py-1">
                                            <input type="text" name="rarity_tier" value="{{ $entry->rarity_tier }}" class="border rounded px-2 py-1">
                                        </div>
                                        <button type="submit" class="px-3 py-1 bg-teal-600 text-white rounded">Update</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.zones.spawns.destroy', [$zone, $entry]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-3 py-1 bg-red-600 text-white rounded">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-3 text-center text-gray-600">No entries yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
    const bulkRows = document.getElementById('bulk-rows');
    const addBulkRowButton = document.getElementById('add-bulk-row');

    if (addBulkRowButton) {
        addBulkRowButton.addEventListener('click', () => {
            const currentRows = bulkRows.querySelectorAll('.bulk-row');
            const index = currentRows.length;
            const template = currentRows[0].cloneNode(true);

            template.querySelectorAll('input, select').forEach((input) => {
                const name = input.getAttribute('name') || '';
                const newName = name.replace(/entries\[\d+\]/, `entries[${index}]`);
                input.setAttribute('name', newName);

                if (input.tagName === 'INPUT') {
                    input.value = input.type === 'number' ? input.defaultValue || input.value : '';
                }

                if (input.tagName === 'SELECT') {
                    input.selectedIndex = 0;
                }
            });

            bulkRows.appendChild(template);
        });
    }
</script>
@endsection
