<div id="matchmaking-panel"
     class="bg-white shadow rounded-xl p-6 space-y-4"
     data-user-id="{{ auth()->id() }}"
     data-queue-url="{{ route('pvp.queue') }}"
     data-dequeue-url="{{ route('pvp.dequeue') }}"
     data-battle-url="{{ url('/battles/__BATTLE_ID__') }}"
     data-battle-fragment-url="{{ route('pvp.battle_fragment', ['battle' => '__BATTLE_ID__']) }}"
     data-search-timeout="{{ $searchTimeout }}"
     data-ladder-window="{{ $currentWindow }}"
     data-queue-size="{{ $queueCount }}"
     data-is-queued="{{ $queueEntry ? '1' : '0' }}"
     data-current-mode="{{ $queueEntry->mode ?? '' }}"
     data-active-battle-id="{{ $activeBattleId ?? '' }}">
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-sm text-gray-600">Ladder search window</p>
            <p class="text-xl font-semibold" data-ladder-window>{{ $currentWindow }} MMR window</p>
            <p class="text-xs text-gray-500">Window grows over time to keep matches close.</p>
        </div>
        <div class="flex items-center gap-3">
            <div class="text-right">
                <p class="text-sm text-gray-600">Players queuing</p>
                <p class="text-xl font-semibold" data-queue-size>{{ $queueCount }}</p>
                <p class="text-xs text-gray-500">Updated live via websockets.</p>
            </div>
            @include('partials.live_badge')
        </div>
    </div>

    <div class="hidden p-3 bg-indigo-50 text-indigo-800 border border-indigo-200 rounded" data-battle-finished-banner>
        Battle finished! Returning to lobby...
    </div>

    <div class="grid md:grid-cols-3 gap-4">
        <div class="border rounded-lg p-4 bg-gray-50 space-y-2">
            <p class="text-sm font-semibold text-gray-700">Choose queue</p>
            <div class="flex flex-wrap gap-2">
                <button class="px-4 py-2 rounded bg-indigo-600 text-white hover:bg-indigo-500" data-queue-mode="ranked">Ranked ladder</button>
                <button class="px-4 py-2 rounded bg-emerald-600 text-white hover:bg-emerald-500" data-queue-mode="casual">Casual</button>
            </div>
            <p class="text-xs text-gray-500">Search starts instantly when you press a mode.</p>
        </div>

        <div class="border rounded-lg p-4 space-y-3">
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-gray-700">Search status</p>
                <span class="px-3 py-1 text-xs rounded-full bg-indigo-100 text-indigo-700 hidden" data-queue-mode-label>{{ $queueEntry->mode ?? 'ranked' }}</span>
            </div>
            <div class="flex items-center gap-2 {{ $queueEntry ? '' : 'hidden' }}" data-searching-banner>
                <span class="h-3 w-3 rounded-full bg-emerald-500 animate-pulse"></span>
                <p class="text-sm text-gray-700">Searching live...</p>
            </div>
            <p class="text-sm text-gray-700" data-status-text>
                @if($queueEntry)
                    Searching for {{ $queueEntry->mode }} opponents since {{ $queueEntry->queued_at->diffForHumans() }}
                @else
                    Press a queue button to start live matchmaking.
                @endif
            </p>
            <p class="text-xs text-gray-500">If no opponent is found, you'll be prompted to try again after a short cooldown.</p>
        </div>

        <div class="border rounded-lg p-4 bg-gray-50 space-y-3">
            <p class="text-sm font-semibold text-gray-700">Queue control</p>
            <div class="flex items-center gap-3">
                <span class="text-sm text-gray-600">Time left:</span>
                <span class="text-lg font-semibold" data-countdown>{{ $queueEntry ? $searchTimeout.'s' : '--' }}</span>
            </div>
            <button class="w-full px-4 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-100" data-leave-queue>Leave queue</button>
            <p class="text-xs text-gray-500">You can rejoin immediately after leaving.</p>
        </div>
    </div>
</div>
