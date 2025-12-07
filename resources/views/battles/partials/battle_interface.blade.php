@php
    $viewerId = $viewerId ?? auth()->id();
    $state = $state ?? $battle->meta_json;
    $participants = $state['participants'] ?? [];
    $yourSide = $participants[$viewerId] ?? null;
    $opponentId = $battle->player1_id === $viewerId ? $battle->player2_id : $battle->player1_id;
    $opponentSide = $participants[$opponentId] ?? null;
    $yourActive = $yourSide['monsters'][$yourSide['active_index'] ?? 0] ?? null;
    $opponentActive = $opponentSide['monsters'][$opponentSide['active_index'] ?? 0] ?? null;
    $yourBench = collect($yourSide['monsters'] ?? [])->reject(fn ($m, $i) => $i === ($yourSide['active_index'] ?? 0));
    $isYourTurn = ($state['next_actor_id'] ?? null) === $viewerId && $battle->status === 'active';
    $players = [
        $battle->player1_id => $battle->player1?->name ?? 'Player '.$battle->player1_id,
        $battle->player2_id => $battle->player2?->name ?? 'Player '.$battle->player2_id,
    ];
@endphp

<script type="application/json" data-battle-initial-state>
    {!! json_encode([
        'battle' => [
            'id' => $battle->id,
            'status' => $battle->status,
            'seed' => $battle->seed,
            'mode' => $state['mode'] ?? 'ranked',
            'player1_id' => $battle->player1_id,
            'player2_id' => $battle->player2_id,
            'winner_user_id' => $battle->winner_user_id,
        ],
        'players' => $players,
        'state' => $state,
        'viewer_id' => $viewerId,
    ]) !!}
</script>

<div class="space-y-5" data-battle-live
     data-battle-id="{{ $battle->id }}"
     data-user-id="{{ $viewerId }}"
     data-battle-status="{{ $battle->status }}"
     data-winner-id="{{ $battle->winner_user_id }}"
     data-act-url="{{ route('battles.act', $battle) }}"
     data-refresh-url="{{ route('battles.show', $battle) }}">
    <div class="bg-white shadow rounded-xl p-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Battle #{{ $battle->id }}</h1>
                <p class="text-gray-600">Status: <span data-battle-status-text>{{ ucfirst($battle->status) }}</span> | Seed: {{ $battle->seed }}</p>
                <p class="text-sm text-gray-500">{{ $battle->player1?->name }} vs {{ $battle->player2?->name }}</p>
                @if($battle->winner_user_id)
                    <p class="text-green-700 font-semibold" data-battle-winner>Winner: {{ $battle->winner?->name }}</p>
                @else
                    <p class="text-green-700 font-semibold hidden" data-battle-winner></p>
                @endif
            </div>
            <div class="text-right text-sm text-gray-600">
                <p>Next actor: <span data-next-actor>{{ $state['next_actor_id'] ?? 'Unknown' }}</span></p>
                <p>Mode: <span data-battle-mode>{{ ucfirst($state['mode'] ?? 'ranked') }}</span></p>
                <p class="text-xs text-gray-500" data-battle-live-status>Connecting to live battle feed...</p>
            </div>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-4">
        <div class="bg-slate-900 text-white rounded-xl p-4 shadow-inner" data-side="you">
            <p class="text-xs uppercase tracking-wide text-slate-300">You</p>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold" data-monster-name="you">{{ $yourActive['name'] ?? 'No fighter' }}</h2>
                    <p class="text-sm text-slate-300" data-monster-types="you">Types: {{ implode(', ', $yourActive['type_names'] ?? []) ?: 'Neutral' }}</p>
                    <p class="text-amber-300 text-sm {{ $yourActive && $yourActive['status'] ? '' : 'hidden' }}" data-monster-status="you">
                        @if($yourActive && $yourActive['status'])
                            Status: {{ ucfirst($yourActive['status']['name']) }}
                        @endif
                    </p>
                </div>
                <div class="w-48" data-monster-hp-container="you">
                    @if($yourActive)
                        @php($hpPercent = max(0, min(100, (int) floor(($yourActive['current_hp'] / max(1, $yourActive['max_hp'])) * 100))))
                        <div class="text-right text-xs text-slate-300" data-monster-hp-text="you">HP {{ $yourActive['current_hp'] }} / {{ $yourActive['max_hp'] }}</div>
                        <div class="w-full bg-slate-700 rounded-full h-3">
                            <div class="h-3 rounded-full bg-emerald-400" data-monster-hp-bar="you" style="width: {{ $hpPercent }}%"></div>
                        </div>
                    @else
                        <p class="text-xs text-slate-300">No active combatant.</p>
                    @endif
                </div>
            </div>

            <div class="mt-3 flex flex-wrap gap-2 text-xs" data-bench-list="you">
                @foreach($yourBench as $monster)
                    <span class="px-2 py-1 rounded-full bg-slate-800 border border-slate-700">{{ $monster['name'] }} (HP {{ $monster['current_hp'] }})</span>
                @endforeach
            </div>
        </div>

        <div class="bg-slate-100 rounded-xl p-4 border" data-side="opponent">
            <p class="text-xs uppercase tracking-wide text-gray-500">Opponent</p>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900" data-monster-name="opponent">{{ $opponentActive['name'] ?? 'Unknown' }}</h2>
                    <p class="text-sm text-gray-600" data-monster-types="opponent">Types: {{ implode(', ', $opponentActive['type_names'] ?? []) ?: 'Neutral' }}</p>
                    <p class="text-amber-700 text-sm {{ $opponentActive && $opponentActive['status'] ? '' : 'hidden' }}" data-monster-status="opponent">
                        @if($opponentActive && $opponentActive['status'])
                            Status: {{ ucfirst($opponentActive['status']['name']) }}
                        @endif
                    </p>
                </div>
                <div class="w-48" data-monster-hp-container="opponent">
                    @if($opponentActive)
                        @php($oppHpPercent = max(0, min(100, (int) floor(($opponentActive['current_hp'] / max(1, $opponentActive['max_hp'])) * 100))))
                        <div class="text-right text-xs text-gray-600" data-monster-hp-text="opponent">HP {{ $opponentActive['current_hp'] }} / {{ $opponentActive['max_hp'] }}</div>
                        <div class="w-full bg-gray-200 rounded-full h-3">
                            <div class="h-3 rounded-full bg-rose-400" data-monster-hp-bar="opponent" style="width: {{ $oppHpPercent }}%"></div>
                        </div>
                    @else
                        <p class="text-xs text-gray-600">No active combatant.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white shadow rounded-xl p-6 space-y-4" data-battle-commands>
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold">Battle commands</h3>
            <span class="text-sm {{ $isYourTurn ? 'text-emerald-600' : 'text-gray-500' }}" data-turn-indicator>{{ $isYourTurn ? 'Your turn' : 'Waiting for opponent' }}</span>
        </div>

        @if($isYourTurn && $yourActive)
            <div class="grid md:grid-cols-2 gap-3">
                @foreach($yourActive['moves'] as $move)
                    <form method="POST" action="{{ route('battles.act', $battle) }}" data-battle-action-form>
                        @csrf
                        <input type="hidden" name="type" value="move">
                        <input type="hidden" name="slot" value="{{ $move['slot'] }}">
                        <button class="w-full px-3 py-3 rounded-lg border border-gray-200 hover:border-emerald-400 hover:shadow text-left" data-move-slot="{{ $move['slot'] }}">
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
                <form method="POST" action="{{ route('battles.act', $battle) }}" class="flex items-center gap-2" data-battle-action-form>
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

    <div class="bg-white shadow rounded-xl p-6" data-battle-log>
        <h2 class="text-xl font-semibold mb-3">Turn Log</h2>
        @if(($state['log'] ?? []) === [])
            <p class="text-gray-600">No turns recorded yet.</p>
        @else
            <div class="space-y-3 text-sm">
                @foreach($state['log'] as $entry)
                    <div class="border rounded p-3 bg-gray-50">
                        <div class="flex items-center justify-between">
                            <p class="font-semibold">Turn {{ $entry['turn'] }} by {{ $players[$entry['actor_user_id']] ?? 'User '.$entry['actor_user_id'] }}</p>
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
