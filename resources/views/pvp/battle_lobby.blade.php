@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="bg-gradient-to-r from-indigo-600 via-teal-500 to-emerald-500 text-white rounded-xl p-6 shadow-lg">
        <div class="flex items-center justify-between">
            <div>
                <p class="uppercase text-xs tracking-widest opacity-80">Ranked ladder</p>
                <h1 class="text-3xl font-bold">Battle Lobby</h1>
                <p class="text-sm opacity-80">You are currently battling an opponent. Stay on this page for live updates.</p>
            </div>
            <div class="text-right">
                <p class="text-sm opacity-80">MMR</p>
                <p class="text-3xl font-semibold">{{ number_format($pvpProfile->mmr ?? 1000) }}</p>
                <p class="text-xs opacity-80">Record: {{ $pvpProfile->wins }}W / {{ $pvpProfile->losses }}L</p>
            </div>
        </div>
    </div>

    @include('pvp._battle_fragment', ['battle' => $battle, 'state' => $state])
</div>
@endsection
