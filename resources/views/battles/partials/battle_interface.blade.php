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

<div class="min-h-[100svh] max-h-[100svh] overflow-hidden flex flex-col bg-gradient-to-b from-sky-100 to-emerald-50 relative"
     data-battle-live
     data-battle-id="{{ $battle->id }}"
     data-user-id="{{ $viewerId }}"
     data-battle-status="{{ $battle->status }}"
     data-winner-id="{{ $battle->winner_user_id }}"
     data-act-url="{{ route('battles.act', $battle) }}"
     data-refresh-url="{{ route('battles.show', $battle) }}">
    <div class="absolute inset-0 pointer-events-none bg-[radial-gradient(ellipse_at_top,_rgba(255,255,255,0.55),_transparent_55%)]"></div>

    <div class="absolute inset-0 z-50 flex flex-col items-center justify-center gap-3 text-slate-900 opacity-0 scale-95 pointer-events-none transition duration-200 ease-out"
         data-battle-waiting-overlay>
        <div class="h-12 w-12 border-4 border-emerald-500 border-t-transparent rounded-full animate-spin" aria-hidden="true"></div>
        <p class="text-lg font-semibold" data-battle-waiting-message>Waiting for opponent...</p>
    </div>

    <div class="relative flex-1 overflow-hidden px-3 pt-10 pb-2">
        <div class="absolute top-3 left-4 flex items-center gap-2 text-xs text-slate-600">
            @include('partials.live_badge')
            <span data-battle-live-status>Connecting to live battle feed...</span>
        </div>

        <button type="button" data-battle-menu-button
                class="absolute top-3 right-4 inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/80 border border-white/70 shadow hover:bg-white transition">
            <span class="sr-only">Open battle menu</span>
            <span class="i-mdi-menu text-xl text-slate-800"></span>
        </button>

        <div class="relative h-full w-full flex flex-col">
            <div class="flex-1 flex flex-col">
                <div class="flex-1 flex flex-col justify-between">
                    <div class="flex items-center justify-between gap-3">
                        <div class="max-w-[70%] sm:max-w-xs bg-white/80 backdrop-blur-md border border-white/60 rounded-xl px-3 py-2 shadow" data-side="opponent">
                            <p class="text-[10px] uppercase tracking-wide text-slate-500">Opponent</p>
                            <div class="flex items-start justify-between gap-3">
                                <div class="space-y-1">
                                    <p class="text-lg font-semibold text-slate-900" data-monster-name="opponent">{{ $opponentActive['name'] ?? 'No fighter' }}</p>
                                    <p class="text-[11px] text-slate-500 opacity-60" data-monster-types="opponent">Types: {{ implode(', ', $opponentActive['type_names'] ?? []) ?: 'Neutral' }}</p>
                                    <p class="text-xs text-amber-700 {{ $opponentActive && $opponentActive['status'] ? '' : 'hidden' }}" data-monster-status="opponent">
                                        @if($opponentActive && $opponentActive['status'])
                                            Status: {{ ucfirst($opponentActive['status']['name']) }}
                                        @endif
                                    </p>
                                </div>
                                <div class="w-28 sm:w-40" data-monster-hp-container="opponent">
                                    @if($opponentActive)
                                        @php($oppHpPercent = max(0, min(100, (int) floor(($opponentActive['current_hp'] / max(1, $opponentActive['max_hp'])) * 100))))
                                        <div class="text-right text-[11px] text-slate-600" data-monster-hp-text="opponent">HP {{ $opponentActive['current_hp'] }} / {{ $opponentActive['max_hp'] }}</div>
                                        <div class="w-full bg-slate-200 rounded-full h-2.5">
                                            <div class="h-2.5 rounded-full bg-rose-400 transition-[width] duration-500 ease-out" data-monster-hp-bar="opponent" style="width: {{ $oppHpPercent }}%"></div>
                                        </div>
                                    @else
                                        <p class="text-xs text-gray-600">No active combatant.</p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="relative w-32 h-32 sm:w-40 sm:h-40 flex items-center justify-center">
                            <div class="absolute inset-0 rounded-full bg-white/50 blur-3xl"></div>
                            <div class="relative h-28 w-28 sm:h-32 sm:w-32 rounded-full bg-gradient-to-br from-slate-200 to-emerald-100 border border-white/70 shadow-inner flex items-center justify-center text-slate-800 font-semibold text-sm">
                                Opponent
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <div class="relative w-32 h-32 sm:w-40 sm:h-40 flex items-center justify-center order-2">
                            <div class="absolute inset-0 rounded-full bg-emerald-200/70 blur-3xl"></div>
                            <div class="relative h-28 w-28 sm:h-32 sm:w-32 rounded-full bg-gradient-to-br from-emerald-200 to-emerald-100 border border-emerald-50 shadow-inner flex items-center justify-center text-emerald-900 font-semibold text-sm">
                                You
                            </div>
                        </div>

                        <div class="max-w-[70%] sm:max-w-xs bg-slate-900/90 backdrop-blur-md border border-slate-800 rounded-xl px-3 py-2 shadow-lg order-1" data-side="you">
                            <p class="text-[10px] uppercase tracking-wide text-emerald-200">You</p>
                            <div class="flex items-start justify-between gap-3">
                                <div class="space-y-1">
                                    <p class="text-lg font-semibold text-white" data-monster-name="you">{{ $yourActive['name'] ?? 'No fighter' }}</p>
                                    <p class="text-[11px] text-slate-300/80 opacity-60" data-monster-types="you">Types: {{ implode(', ', $yourActive['type_names'] ?? []) ?: 'Neutral' }}</p>
                                    <p class="text-xs text-amber-200 {{ $yourActive && $yourActive['status'] ? '' : 'hidden' }}" data-monster-status="you">
                                        @if($yourActive && $yourActive['status'])
                                            Status: {{ ucfirst($yourActive['status']['name']) }}
                                        @endif
                                    </p>
                                </div>
                                <div class="w-28 sm:w-40" data-monster-hp-container="you">
                                    @if($yourActive)
                                        @php($hpPercent = max(0, min(100, (int) floor(($yourActive['current_hp'] / max(1, $yourActive['max_hp'])) * 100))))
                                        <div class="text-right text-[11px] text-slate-200/80" data-monster-hp-text="you">HP {{ $yourActive['current_hp'] }} / {{ $yourActive['max_hp'] }}</div>
                                        <div class="w-full bg-slate-700 rounded-full h-2.5">
                                            <div class="h-2.5 rounded-full bg-emerald-400 transition-[width] duration-500 ease-out" data-monster-hp-bar="you" style="width: {{ $hpPercent }}%"></div>
                                        </div>
                                    @else
                                        <p class="text-xs text-slate-200/80">No active combatant.</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="absolute inset-0 bg-slate-900/40 opacity-0 pointer-events-none transition-opacity" data-battle-menu-backdrop></div>
    <div class="absolute top-0 right-0 h-full w-72 max-w-[80%] bg-white shadow-2xl translate-x-full transition-transform duration-200 flex flex-col" data-battle-menu-drawer>
        <div class="p-4 border-b flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-900">Battle Menu</h2>
            <button type="button" data-battle-menu-close class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-slate-700 hover:bg-slate-200">
                <span class="sr-only">Close menu</span>
                <span class="i-mdi-close text-lg"></span>
            </button>
        </div>
        <div class="p-4 space-y-3 text-sm text-slate-700 flex-1">
            <p class="text-base font-semibold">Battle #{{ $battle->id }}</p>
            <p>Status: <span class="font-medium" data-battle-status-text>{{ ucfirst($battle->status) }}</span></p>
            <p>Mode: <span class="font-medium" data-battle-mode>{{ ucfirst($state['mode'] ?? 'ranked') }}</span></p>
            <p class="font-semibold text-emerald-700 {{ $battle->winner_user_id ? '' : 'hidden' }}" data-battle-winner>
                @if($battle->winner_user_id)
                    Winner: {{ $battle->winner?->name }}
                @endif
            </p>
            <p class="text-xs text-slate-500">{{ $battle->player1?->name }} vs {{ $battle->player2?->name }}</p>
            <p class="text-xs text-slate-500" data-next-actor>Next actor: {{ $state['next_actor_id'] ?? 'Unknown' }}</p>
            <p class="text-xs text-slate-500">Seed: {{ $battle->seed }}</p>
            <p class="text-xs text-slate-500" data-battle-live-status>Connecting to live battle feed...</p>
            <div class="pt-2">
                <a href="{{ url('/pvp') }}" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-600 text-white font-semibold shadow hover:bg-emerald-500 transition">
                    Leave to PvP
                    <span class="i-mdi-exit-run"></span>
                </a>
            </div>
        </div>
    </div>

    <div class="relative z-10 bg-slate-900 text-white rounded-t-3xl px-3 py-3 sm:px-5 sm:py-4 border-t border-slate-800 shadow-xl shrink-0 max-h-[38svh] overflow-hidden" data-battle-commands>
        <div class="space-y-3" data-battle-commands-body>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold">Choose an action</h3>
                    <span class="text-xs text-emerald-200 rounded-full border border-emerald-400/60 px-2 py-0.5 hidden" data-battle-commands-locked-hint>Locked</span>
                </div>
                <span class="text-sm {{ $isYourTurn ? 'text-emerald-200' : 'text-slate-200/80' }}" data-turn-indicator>{{ $isYourTurn ? 'Your turn' : 'Waiting for opponent' }}</span>
            </div>

            <div class="mt-1 hidden" data-turn-timer>
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

            <div class="flex items-center gap-2 overflow-x-auto pb-1" data-battle-command-tabs>
                <button type="button" class="px-3 py-2 rounded-xl bg-white/10 border border-white/10 text-sm font-semibold transition active:scale-[0.98]" data-battle-tab="move">Move</button>
                <button type="button" class="px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm font-semibold transition active:scale-[0.98]" data-battle-tab="bag">Bag</button>
                <button type="button" class="px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm font-semibold transition active:scale-[0.98]" data-battle-tab="tame">Tame</button>
                <button type="button" class="px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm font-semibold transition active:scale-[0.98]" data-battle-tab="run">Run</button>
                <button type="button" class="px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm font-semibold transition active:scale-[0.98]" data-battle-tab="switch">Switch</button>
            </div>

            <div class="relative overflow-y-auto pr-1 space-y-3" data-battle-tab-panels>
                <div data-battle-panel="move" class="grid grid-cols-2 gap-2">
                    @if($yourActive)
                        @foreach($yourActive['moves'] as $move)
                            <form method="POST" action="{{ route('battles.act', $battle) }}" data-battle-action-form>
                                @csrf
                                <input type="hidden" name="type" value="move">
                                <input type="hidden" name="slot" value="{{ $move['slot'] }}">
                                <button class="w-full h-full min-h-[64px] px-3 py-3 rounded-xl border border-slate-800 bg-gradient-to-br from-slate-800/80 to-slate-900/80 hover:border-emerald-300 hover:bg-emerald-300/10 hover:shadow-md text-left transition-transform duration-150 active:scale-[0.98]" data-move-slot="{{ $move['slot'] }}">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-semibold">{{ $move['name'] }}</span>
                                        <span class="text-[11px] uppercase text-slate-200/80">Slot {{ $move['slot'] }}</span>
                                    </div>
                                    <p class="text-sm text-slate-200/80">{{ ucfirst($move['category']) }} • {{ $move['type'] ?? 'Neutral' }} • Power {{ $move['power'] }}</p>
                                </button>
                            </form>
                        @endforeach
                    @else
                        <p class="text-sm text-slate-200/80">No active combatant.</p>
                    @endif
                </div>

                <div data-battle-panel="bag" class="hidden">
                    <p class="text-sm text-slate-200/80">Your bag items will appear here in a future update.</p>
                </div>

                <div data-battle-panel="tame" class="hidden">
                    <form method="POST" action="{{ route('battles.act', $battle) }}" data-battle-action-form>
                        @csrf
                        <input type="hidden" name="type" value="tame">
                        <button class="w-full px-3 py-3 rounded-xl border border-slate-800 bg-emerald-600 text-white font-semibold hover:bg-emerald-500 transition-transform duration-150 active:scale-[0.98]">Attempt Tame</button>
                    </form>
                </div>

                <div data-battle-panel="run" class="hidden">
                    <form method="POST" action="{{ route('battles.act', $battle) }}" data-battle-action-form>
                        @csrf
                        <input type="hidden" name="type" value="run">
                        <button class="w-full px-3 py-3 rounded-xl border border-slate-800 bg-rose-600 text-white font-semibold hover:bg-rose-500 transition-transform duration-150 active:scale-[0.98]">Attempt to Run</button>
                    </form>
                </div>

                <div data-battle-panel="switch" class="hidden space-y-2">
                    @if($yourBench->isNotEmpty())
                        <form method="POST" action="{{ route('battles.act', $battle) }}" class="flex flex-col sm:flex-row gap-2 items-stretch sm:items-center" data-battle-action-form data-battle-swap-form>
                            @csrf
                            <input type="hidden" name="type" value="swap">
                            <select name="monster_instance_id" class="border-slate-700 bg-slate-800 text-white rounded-lg px-2 py-2 text-sm flex-1">
                                @foreach($yourBench as $monster)
                                    <option value="{{ $monster['id'] }}">Swap to {{ $monster['name'] }} (HP {{ $monster['current_hp'] }})</option>
                                @endforeach
                            </select>
                            <button class="px-3 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-500 transition-transform duration-150 active:scale-[0.98]">Swap</button>
                        </form>
                    @else
                        <p class="text-xs text-slate-200/80">No reserve monsters available{{ ($yourActive['id'] ?? null) === 0 ? '—using martial arts move set.' : '.' }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
