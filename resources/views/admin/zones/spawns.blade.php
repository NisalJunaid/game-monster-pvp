<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Zone Spawn Entries</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 1rem; }
        h1 { margin-bottom: 0.5rem; }
        .status { margin-bottom: 1rem; padding: 0.5rem 0.75rem; background: #ecfdf3; color: #047857; border: 1px solid #bbf7d0; border-radius: 6px; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { border: 1px solid #e5e7eb; padding: 0.5rem; text-align: left; }
        form { margin-bottom: 1rem; }
        input, select { padding: 0.35rem; }
        .actions { display: flex; gap: 0.5rem; align-items: center; }
    </style>
</head>
<body>
    <h1>Spawn Entries for {{ $zone->name }}</h1>
    <p><a href="{{ route('admin.zones.map') }}">&larr; Back to Zones</a></p>

    @if(session('status'))
        <div class="status">{{ session('status') }}</div>
    @endif

    <h2>Create Entry</h2>
    <form method="POST" action="{{ route('admin.zones.spawns.store', $zone) }}">
        @csrf
        <div class="actions">
            <label>Species ID <input type="number" name="species_id" required></label>
            <label>Weight <input type="number" name="weight" value="1" required></label>
            <label>Min Lv <input type="number" name="min_level" value="1" required></label>
            <label>Max Lv <input type="number" name="max_level" value="5" required></label>
            <label>Rarity <input type="text" name="rarity_tier" placeholder="common"></label>
            <button type="submit">Add</button>
        </div>
    </form>

    <h2>Existing Entries</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Species</th>
                <th>Weight</th>
                <th>Level Range</th>
                <th>Rarity</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($zone->spawnEntries as $entry)
                <tr>
                    <td>{{ $entry->id }}</td>
                    <td>{{ $entry->species->name ?? ('#'.$entry->species_id) }}</td>
                    <td>{{ $entry->weight }}</td>
                    <td>{{ $entry->min_level }} - {{ $entry->max_level }}</td>
                    <td>{{ $entry->rarity_tier ?? 'n/a' }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.zones.spawns.update', [$zone, $entry]) }}" style="margin-bottom:0.5rem;">
                            @csrf
                            @method('PUT')
                            <div class="actions">
                                <input type="number" name="species_id" value="{{ $entry->species_id }}" required>
                                <input type="number" name="weight" value="{{ $entry->weight }}" required>
                                <input type="number" name="min_level" value="{{ $entry->min_level }}" required>
                                <input type="number" name="max_level" value="{{ $entry->max_level }}" required>
                                <input type="text" name="rarity_tier" value="{{ $entry->rarity_tier }}">
                                <button type="submit">Update</button>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('admin.zones.spawns.destroy', [$zone, $entry]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">No entries yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
