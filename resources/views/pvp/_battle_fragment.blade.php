<div id="matchmaking-panel"
     class="space-y-4"
     data-user-id="{{ auth()->id() }}"
     data-battle-fragment-url="{{ route('pvp.battle_fragment', ['battle' => '__BATTLE_ID__']) }}"
     data-battle-url="{{ url('/battles/__BATTLE_ID__') }}"
     data-active-battle-id="{{ $battle->id }}">
    <div class="bg-white shadow rounded-xl p-4 flex items-center justify-between">
        <div>
            <p class="text-sm text-gray-600">Active PvP battle</p>
            <p class="text-lg font-semibold">Battle #{{ $battle->id }} is in progress.</p>
        </div>
        <div class="flex items-center gap-2">
            <a class="px-3 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-500" href="{{ route('battles.show', $battle) }}">Open standalone</a>
            <a class="px-3 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-100" href="{{ route('pvp.index') }}">Back to lobby</a>
        </div>
    </div>

    @include('battles.partials.battle_interface', ['battle' => $battle, 'state' => $state, 'viewerId' => auth()->id()])
</div>
