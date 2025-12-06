@extends('layouts.app')

@section('content')
<div class="space-y-4" id="encounter-live" data-update-url="{{ route('encounters.update') }}" data-user-id="{{ auth()->id() }}" data-encounter-url="{{ route('encounters.index') }}" data-resolve-template="{{ route('encounters.resolve', ['ticket' => '__ID__']) }}">
    <div class="bg-white shadow rounded p-6">
        <div class="flex items-center justify-between mb-2">
            <div>
                <h1 class="text-2xl font-bold">Encounters</h1>
                <p class="text-gray-600">Update your location to discover nearby monsters.</p>
            </div>
        </div>
        <div id="location-status" class="text-sm text-gray-600 mb-2"></div>
        <form method="POST" action="{{ route('encounters.update') }}" class="grid md:grid-cols-3 gap-4 items-end" id="location-form">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700">Latitude</label>
                <input id="lat" type="number" step="any" name="lat" value="{{ old('lat', $location?->lat) }}" required class="mt-1 w-full border-gray-300 rounded" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Longitude</label>
                <input id="lng" type="number" step="any" name="lng" value="{{ old('lng', $location?->lng) }}" required class="mt-1 w-full border-gray-300 rounded" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Accuracy (m)</label>
                <input id="accuracy" type="number" step="any" name="accuracy_m" value="{{ old('accuracy_m', $location?->accuracy_m ?? 10) }}" required class="mt-1 w-full border-gray-300 rounded" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Speed (m/s)</label>
                <input id="speed_mps" type="number" step="any" name="speed_mps" value="{{ old('speed_mps', $location?->speed_mps) }}" class="mt-1 w-full border-gray-300 rounded" />
            </div>
            <div class="md:col-span-2 flex items-center space-x-3">
                <button type="button" id="use-location" class="px-3 py-2 bg-gray-200 rounded border">Use Browser Location</button>
                <button class="px-4 py-2 bg-teal-600 text-white rounded hover:bg-teal-500">Update Location</button>
            </div>
            <input type="hidden" name="recorded_at" id="recorded_at" value="{{ old('recorded_at') }}">
        </form>
    </div>

    <div class="bg-white shadow rounded p-6">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-xl font-semibold">Current Encounter</h2>
            <span class="text-sm text-gray-500" id="encounter-expiry"></span>
        </div>
        <div id="encounter-card">
            @if($ticket)
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-bold">{{ $ticket->species->name }} (Lv {{ $ticket->rolled_level }})</p>
                        <p class="text-gray-500 text-sm">Zone: {{ $ticket->zone?->name ?? 'Unknown' }}</p>
                    </div>
                    <form method="POST" action="{{ route('encounters.resolve', $ticket) }}" data-resolve-form>
                        @csrf
                        <button class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-500">Resolve Capture</button>
                    </form>
                </div>
            @else
                <p class="text-gray-600">No encounters available. Update your location to search again.</p>
            @endif
        </div>
    </div>
</div>

<script>
    window.__INITIAL_ENCOUNTER__ = @json($ticket?->load(['species', 'zone']));
</script>
@endsection
