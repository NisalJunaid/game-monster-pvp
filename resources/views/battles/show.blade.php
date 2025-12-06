@extends('layouts.app')

@section('content')
@php
    $userId = auth()->id();
    $opponentId = $battle->player1_id === $userId ? $battle->player2_id : $battle->player1_id;
    $yourSide = $state['participants'][$userId] ?? null;
    $opponentSide = $state['participants'][$opponentId] ?? null;
    $yourActive = $yourSide['monsters'][$yourSide['active_index'] ?? 0] ?? null;
    $opponentActive = $opponentSide['monsters'][$opponentSide['active_index'] ?? 0] ?? null;
    $yourBench = collect($yourSide['monsters'] ?? [])->reject(fn($m, $i) => $i === ($yourSide['active_index'] ?? 0));
    $isYourTurn = ($state['next_actor_id'] ?? null) === $userId && $battle->status === 'active';
@endphp

<div class="space-y-5">
    <div class="bg-white shadow rounded-xl p-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Battle #{{ $battle->id }}</h1>
                <p class="text-gray-600">Status: {{ ucfirst($battle->status) }} | Seed: {{ $battle->seed }}</p>
                <p class="text-sm text-gray-500">{{ $battle->player1?->name }} vs {{ $battle->player2?->name }}</p>
                @if($battle->winner_user_id)
                    <p class="text-green-700 font-semibold">Winner: {{ $battle->winner?->name }}</p>
                @endif
            </div>
            <div class="text-right text-sm text-gray-600">
                <p>Next actor: {{ $state['next_actor_id'] ?? 'Unknown' }}</p>
                <p>Mode: {{ ucfirst($state['mode'] ?? 'ranked') }}</p>
            </div>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-4">
        <div class="bg-slate-900 text-white rounded-xl p-4 shadow-inner">
            <p class="text-xs uppercase tracking-wide text-slate-300">You</p>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold">{{ $yourActive['name'] ?? 'No fighter' }}</h2>
                    <p class="text-sm text-slate-300">Types: {{ implode(', ', $yourActive['type_names'] ?? []) ?: 'Neutral' }}</p>
                    @if($yourActive && $yourActive['status'])
                        <p class="text-amber-300 text-sm">Status: {{ ucfirst($yourActive['status']['name']) }}</p>
                    @endif
                </div>
                @if($yourActive)
                    @php($hpPercent = max(0, min(100, (int) floor(($yourActive['current_hp'] / max(1, $yourActive['max_hp'])) * 100))))
                    <div class="w-48">
                        <div class="text-right text-xs text-slate-300">HP {{ $yourActive['current_hp'] }} / {{ $yourActive['max_hp'] }}</div>
                        <div class="w-full bg-slate-700 rounded-full h-3">
                            <div class="h-3 rounded-full bg-emerald-400" style="width: {{ $hpPercent }}%"></div>
                        </div>
                    </div>
                @endif
            </div>

            @if($yourBench->isNotEmpty())
                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                    @foreach($yourBench as $monster)
                        <span class="px-2 py-1 rounded-full bg-slate-800 border border-slate-700">{{ $monster['name'] }} (HP {{ $monster['current_hp'] }})</span>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="bg-slate-100 rounded-xl p-4 border">
            <p class="text-xs uppercase tracking-wide text-gray-500">Opponent</p>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">{{ $opponentActive['name'] ?? 'Unknown' }}</h2>
                    <p class="text-sm text-gray-600">Types: {{ implode(', ', $opponentActive['type_names'] ?? []) ?: 'Neutral' }}</p>
                    @if($opponentActive && $opponentActive['status'])
                        <p class="text-amber-700 text-sm">Status: {{ ucfirst($opponentActive['status']['name']) }}</p>
                    @endif
                </div>
                @if($opponentActive)
                    @php($oppHpPercent = max(0, min(100, (int) floor(($opponentActive['current_hp'] / max(1, $opponentActive['max_hp'])) * 100))))
                    <div class="w-48">
                        <div class="text-right text-xs text-gray-600">HP {{ $opponentActive['current_hp'] }} / {{ $opponentActive['max_hp'] }}</div>
                        <div class="w-full bg-gray-200 rounded-full h-3">
                            <div class="h-3 rounded-full bg-rose-400" style="width: {{ $oppHpPercent }}%"></div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="bg-white shadow rounded-xl p-6 space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold">Battle commands</h3>
            <span class="text-sm {{ $isYourTurn ? 'text-emerald-600' : 'text-gray-500' }}">{{ $isYourTurn ? 'Your turn' : 'Waiting for opponent' }}</span>
        </div>

        @if($isYourTurn && $yourActive)
            <div class="grid md:grid-cols-2 gap-3">
                @foreach($yourActive['moves'] as $move)
                    <form method="POST" action="{{ route('battles.act', $battle) }}">
                        @csrf
                        <input type="hidden" name="type" value="move">
                        <input type="hidden" name="slot" value="{{ $move['slot'] }}">
                        <button class="w-full px-3 py-3 rounded-lg border border-gray-200 hover:border-emerald-400 hover:shadow text-left">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold">{{ $move['name'] }}</span>
                                <span class="text-xs uppercase text-gray-500">Slot {{ $move['slot'] }}</span>
                            </div>
                            <p class="text-sm text-gray-600">{{ ucfirst($move['category']) }} • {{ $move['type'] ?? 'Neutral' }} • Power {{ $move['power'] }}</p>
                        </button>
                    </form>
                @endforeach
            </div>

            @if($yourBench->isNotEmpty())
                <form method="POST" action="{{ route('battles.act', $battle) }}" class="flex items-center gap-2">
                    @csrf
                    <input type="hidden" name="type" value="swap">
                    <select name="monster_instance_id" class="border-gray-300 rounded">
                        @foreach($yourBench as $monster)
                            <option value="{{ $monster['id'] }}">Swap to {{ $monster['name'] }} (HP {{ $monster['current_hp'] }})</option>
                        @endforeach
                    </select>
                    <button class="px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-500">Swap</button>
                </form>
            @else
                <p class="text-xs text-gray-500">No reserve monsters available{{ ($yourActive['id'] ?? null) === 0 ? '—using martial arts move set.' : '.' }}</p>
            @endif
        @elseif($battle->status === 'active')
            <p class="text-sm text-gray-600">Waiting for opponent action...</p>
        @else
            <p class="text-sm text-gray-600">Battle complete.</p>
        @endif
    </div>

    <div class="bg-white shadow rounded-xl p-6">
        <h2 class="text-xl font-semibold mb-3">Turn Log</h2>
        @if(($state['log'] ?? []) === [])
            <p class="text-gray-600">No turns recorded yet.</p>
        @else
            <div class="space-y-3 text-sm">
                @foreach($state['log'] as $entry)
                    <div class="border rounded p-3 bg-gray-50">
                        <div class="flex items-center justify-between">
                            <p class="font-semibold">Turn {{ $entry['turn'] }} by User {{ $entry['actor_user_id'] }}</p>
                            <span class="text-xs text-gray-500">Action: {{ $entry['action']['type'] }} @if(($entry['action']['type'] ?? '') === 'move') (Slot {{ $entry['action']['slot'] }}) @endif</span>
                        </div>
                        <ul class="list-disc list-inside text-gray-600">
                            @foreach($entry['events'] as $event)
                                <li>{{ ucfirst($event['type']) }} - {{ json_encode($event) }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
