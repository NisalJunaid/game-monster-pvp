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

<div class="flex flex-col gap-4 min-h-[100svh] bg-slate-50"
     data-battle-live
     data-battle-id="{{ $battle->id }}"
     data-user-id="{{ $viewerId }}"
     data-battle-status="{{ $battle->status }}"
     data-winner-id="{{ $battle->winner_user_id }}"
     data-act-url="{{ route('battles.act', $battle) }}"
     data-refresh-url="{{ route('battles.show', $battle) }}">

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

    <div class="bg-white shadow rounded-xl p-4 sm:p-6">
        <div class="flex items-center justify-between gap-4">
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
            <div class="flex items-center gap-3">
                <button type="button" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border text-sm font-medium text-gray-700 hover:bg-gray-50 lg:hidden" data-battle-log-toggle aria-expanded="false">
                    <span class="i-mdi-format-list-bulleted text-lg"></span>
                    <span>Log</span>
                </button>
                <div class="flex items-center gap-3">
                    @include('partials.live_badge')
                    <div class="text-right text-sm text-gray-600">
                        <p>Next actor: <span data-next-actor>{{ $state['next_actor_id'] ?? 'Unknown' }}</span></p>
                        <p>Mode: <span data-battle-mode>{{ ucfirst($state['mode'] ?? 'ranked') }}</span></p>
                        <p class="text-xs text-gray-500" data-battle-live-status>Connecting to live battle feed...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="flex-1 flex flex-col gap-4 lg:grid lg:grid-cols-2">
        <div class="bg-slate-100 rounded-xl p-3 sm:p-4 border order-1" data-side="opponent">
            <p class="text-xs uppercase tracking-wide text-gray-500">Opponent</p>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900" data-monster-name="opponent">{{ $opponentActive['name'] ?? 'No fighter' }}</h2>
                    <p class="text-sm text-gray-600" data-monster-types="opponent">Types: {{ implode(', ', $opponentActive['type_names'] ?? []) ?: 'Neutral' }}</p>
                    <p class="text-amber-700 text-sm {{ $opponentActive && $opponentActive['status'] ? '' : 'hidden' }}" data-monster-status="opponent">
                        @if($opponentActive && $opponentActive['status'])
                            Status: {{ ucfirst($opponentActive['status']['name']) }}
                        @endif
                    </p>
                </div>
                <div class="w-40 sm:w-48" data-monster-hp-container="opponent">
                    @if($opponentActive)
                        @php($oppHpPercent = max(0, min(100, (int) floor(($opponentActive['current_hp'] / max(1, $opponentActive['max_hp'])) * 100))))
                        <div class="text-right text-xs text-gray-600" data-monster-hp-text="opponent">HP {{ $opponentActive['current_hp'] }} / {{ $opponentActive['max_hp'] }}</div>
                        <div class="w-full bg-gray-200 rounded-full h-3">
                            <div class="h-3 rounded-full bg-rose-400 transition-[width] duration-500 ease-out" data-monster-hp-bar="opponent" style="width: {{ $oppHpPercent }}%"></div>
                        </div>
                    @else
                        <p class="text-xs text-gray-600">No active combatant.</p>
                    @endif
                </div>
            </div>

            <div class="mt-3 flex flex-wrap gap-2 text-xs" data-bench-list="opponent">
                @foreach(collect($opponentSide['monsters'] ?? [])->reject(fn ($m, $i) => $i === ($opponentSide['active_index'] ?? 0)) as $monster)
                    <span class="px-2 py-1 rounded-full bg-gray-200 border border-gray-300">{{ $monster['name'] }} (HP {{ $monster['current_hp'] }})</span>
                @endforeach
            </div>
        </div>

        <div class="bg-slate-900 text-white rounded-xl p-3 sm:p-4 shadow-inner order-2" data-side="you">
            <p class="text-xs uppercase tracking-wide text-slate-300">You</p>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold" data-monster-name="you">{{ $yourActive['name'] ?? 'No fighter' }}</h2>
                    <p class="text-sm text-slate-300" data-monster-types="you">Types: {{ implode(', ', $yourActive['type_names'] ?? []) ?: 'Neutral' }}</p>
                    <p class="text-amber-300 text-sm {{ $yourActive && $yourActive['status'] ? '' : 'hidden' }}" data-monster-status="you">
                        @if($yourActive && $yourActive['status'])
                            Status: {{ ucfirst($yourActive['status']['name']) }}
                        @endif
                    </p>
                </div>
                <div class="w-40 sm:w-48" data-monster-hp-container="you">
                    @if($yourActive)
                        @php($hpPercent = max(0, min(100, (int) floor(($yourActive['current_hp'] / max(1, $yourActive['max_hp'])) * 100))))
                        <div class="text-right text-xs text-slate-300" data-monster-hp-text="you">HP {{ $yourActive['current_hp'] }} / {{ $yourActive['max_hp'] }}</div>
                        <div class="w-full bg-slate-700 rounded-full h-3">
                            <div class="h-3 rounded-full bg-emerald-400 transition-[width] duration-500 ease-out" data-monster-hp-bar="you" style="width: {{ $hpPercent }}%"></div>
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
    </div>

    <div class="bg-gradient-to-t from-slate-900 via-slate-900 to-slate-800 text-white rounded-t-2xl lg:rounded-xl p-4 sm:p-6 shadow-xl relative mt-auto ring-1 ring-slate-700/40 transition-opacity duration-200 sticky bottom-0 left-0 right-0 lg:static" data-battle-commands>
        <div class="absolute inset-0 flex flex-col items-center justify-center gap-3 text-white/90 opacity-0 scale-95 pointer-events-none transition duration-200 ease-out" data-battle-waiting-overlay>
            <div class="h-10 w-10 border-4 border-emerald-400 border-t-transparent rounded-full animate-spin" aria-hidden="true"></div>
            <p class="text-lg font-semibold" data-battle-waiting-message>Waiting for opponent...</p>
        </div>

        <div class="space-y-4" data-battle-commands-body>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <h3 class="text-lg font-semibold">Battle commands</h3>
                    <span class="text-xs text-emerald-200 rounded-full border border-emerald-400/60 px-2 py-0.5 hidden" data-battle-commands-locked-hint>Locked</span>
                </div>
                <span class="text-sm {{ $isYourTurn ? 'text-emerald-200' : 'text-slate-200/80' }}" data-turn-indicator>{{ $isYourTurn ? 'Your turn' : 'Waiting for opponent' }}</span>
            </div>

            <div class="mt-2 hidden" data-turn-timer>
                <div class="flex items-center justify-between text-xs text-slate-200/80 mb-1">
                    <span data-turn-timer-label>Opponent turn timer</span>
                </div>

                <div class="w-full h-2 bg-slate-800 rounded-full overflow-hidden">
                    <div
                        class="h-2 bg-amber-400 transition-[width] duration-100"
                        style="width: 100%;"
                        data-turn-timer-bar
                    ></div>
                </div>

                <p class="mt-1 text-xs text-amber-200 hidden" data-turn-timer-expired>
                    Time expired — waiting for server…
                </p>
            </div>

            @if($isYourTurn && $yourActive)
                <div class="grid grid-cols-2 gap-3">
                    @foreach($yourActive['moves'] as $move)
                        <form method="POST" action="{{ route('battles.act', $battle) }}" data-battle-action-form>
                            @csrf
                            <input type="hidden" name="type" value="move">
                            <input type="hidden" name="slot" value="{{ $move['slot'] }}">
                            <button class="w-full h-full min-h-[84px] px-3 py-3 rounded-lg border border-slate-700/60 bg-white/10 hover:border-emerald-300 hover:bg-emerald-300/10 hover:shadow-md text-left transition-transform duration-150 active:scale-[0.98]" data-move-slot="{{ $move['slot'] }}">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-semibold">{{ $move['name'] }}</span>
                                    <span class="text-[11px] uppercase text-slate-200/80">Slot {{ $move['slot'] }}</span>
                                </div>
                                <p class="text-sm text-slate-200/80">{{ ucfirst($move['category']) }} • {{ $move['type'] ?? 'Neutral' }} • Power {{ $move['power'] }}</p>
                            </button>
                        </form>
                    @endforeach
                </div>

                @if($yourBench->isNotEmpty())
                    <form method="POST" action="{{ route('battles.act', $battle) }}" class="flex flex-col sm:flex-row gap-2 items-stretch sm:items-center" data-battle-action-form data-battle-swap-form>
                        @csrf
                        <input type="hidden" name="type" value="swap">
                        <select name="monster_instance_id" class="border-gray-300 rounded px-2 py-2 text-gray-900 flex-1">
                            @foreach($yourBench as $monster)
                                <option value="{{ $monster['id'] }}">Swap to {{ $monster['name'] }} (HP {{ $monster['current_hp'] }})</option>
                            @endforeach
                        </select>
                        <button class="px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-500 transition-transform duration-150 active:scale-[0.98]">Swap</button>
                    </form>
                @else
                    <p class="text-xs text-slate-200/80">No reserve monsters available{{ ($yourActive['id'] ?? null) === 0 ? '—using martial arts move set.' : '.' }}</p>
                @endif
            @elseif($battle->status === 'active')
                <p class="text-sm text-slate-200/80">Waiting for opponent action...</p>
            @else
                <p class="text-sm text-slate-200/80">Battle complete.</p>
            @endif
        </div>
    </div>

    <div class="bg-white shadow rounded-xl p-4 sm:p-6 hidden lg:block" data-battle-log-wrapper>
        <div data-battle-log>
            <h2 class="text-xl font-semibold mb-3">Turn Log</h2>
            @if(($state['log'] ?? []) === [])
                <p class="text-gray-600">No turns recorded yet.</p>
            @else
                <div class="space-y-3 text-sm">
                    @foreach($state['log'] as $entry)
                        <div class="border rounded p-3 bg-gray-50">
                            <div class="flex items-center justify-between">
                                <p class="font-semibold">Turn {{ $entry['turn'] }} by {{ $players[$entry['actor_user_id']] ?? 'User'.$entry['actor_user_id'] }}</p>
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
</div>
