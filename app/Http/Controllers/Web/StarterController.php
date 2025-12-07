<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MonsterSpeciesStage;
use App\Models\PlayerMonster;
use App\Models\Type;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StarterController extends Controller
{
    public function show(): View
    {
        $types = Type::orderBy('name')->get();

        return view('starter', [
            'types' => $types,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->has_starter) {
            return redirect()->route('dashboard');
        }

        $validated = $request->validate([
            'type_id' => ['required', 'exists:types,id'],
        ]);

        $starterStage = MonsterSpeciesStage::query()
            ->with('species')
            ->where('stage_number', 1)
            ->whereHas('species', function ($query) use ($validated) {
                $query->where('primary_type_id', $validated['type_id'])
                    ->orWhere('secondary_type_id', $validated['type_id']);
            })
            ->inRandomOrder()
            ->first();

        if (! $starterStage || ! $starterStage->species) {
            return back()->withErrors('No starter species available for that type.');
        }

        DB::transaction(function () use ($user, $starterStage) {
            PlayerMonster::create([
                'user_id' => $user->id,
                'species_id' => $starterStage->species_id,
                'level' => 5,
                'exp' => 0,
                'current_hp' => $starterStage->hp,
                'max_hp' => $starterStage->hp,
                'nickname' => null,
                'is_in_team' => true,
                'team_slot' => 1,
            ]);

            $user->forceFill(['has_starter' => true])->save();
        });

        return redirect()->route('dashboard')->with('status', 'Your starter has joined your party!');
    }
}
