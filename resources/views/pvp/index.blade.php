@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="bg-gradient-to-r from-indigo-600 via-teal-500 to-emerald-500 text-white rounded-xl p-6 shadow-lg">
        <div class="flex items-center justify-between">
            <div>
                <p class="uppercase text-xs tracking-widest opacity-80">Ranked ladder</p>
                <h1 class="text-3xl font-bold">Battle Lobby</h1>
                <p class="text-sm opacity-80">Live socket matchmaking finds opponents near your rating.</p>
            </div>
            <div class="text-right">
                <p class="text-sm opacity-80">MMR</p>
                <p class="text-3xl font-semibold">{{ number_format($pvpProfile->mmr ?? 1000) }}</p>
                <p class="text-xs opacity-80">Record: {{ $pvpProfile->wins }}W / {{ $pvpProfile->losses }}L</p>
            </div>
        </div>
    </div>

    @include('pvp._lobby_fragment', [
        'queueEntry' => $queueEntry,
        'searchTimeout' => $searchTimeout,
        'currentWindow' => $currentWindow,
        'queueCount' => $queueCount,
        'activeBattleId' => $activeBattleId,
    ])

    <div class="bg-white shadow rounded-xl p-6">
        <h2 class="text-xl font-semibold mb-3">Recent battle</h2>
        @if($latestBattle)
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-700">Battle #{{ $latestBattle->id }} ({{ ucfirst($latestBattle->status) }})</p>
                    <p class="text-sm text-gray-600">Players: {{ $latestBattle->player1?->name }} vs {{ $latestBattle->player2?->name }}</p>
                </div>
                <a class="px-3 py-2 bg-teal-600 text-white rounded hover:bg-teal-500" href="{{ route('battles.show', $latestBattle) }}">Open battle</a>
            </div>
        @else
            <p class="text-gray-600">No battles yet. Queue up to start one.</p>
        @endif
    </div>
</div>
@endsection
