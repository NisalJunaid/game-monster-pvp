<?php

namespace App\Http\Controllers\Web;

use App\Domain\Encounters\WildBattleService;
use App\Http\Controllers\Controller;
use App\Models\EncounterTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WildBattleController extends Controller
{
    public function __construct(private readonly WildBattleService $battleService)
    {
    }

    public function show(Request $request, EncounterTicket $ticket)
    {
        $battleTicket = $this->battleService->start($request->user(), $ticket);

        return view('encounters.battle', [
            'ticket' => $battleTicket,
            'state' => $battleTicket->battle_state,
        ]);
    }

    public function move(Request $request, EncounterTicket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'style' => ['nullable', 'in:monster,martial'],
        ]);

        $updated = $this->battleService->actMove(
            $request->user(),
            $ticket,
            $validated['style'] ?? 'monster'
        );

        return response()->json([
            'battle' => $updated->battle_state,
            'ticket' => $updated,
        ]);
    }

    public function switchActive(Request $request, EncounterTicket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'player_monster_id' => ['required', 'integer'],
        ]);

        $updated = $this->battleService->actSwitch($request->user(), $ticket, (int) $validated['player_monster_id']);

        return response()->json([
            'battle' => $updated->battle_state,
            'ticket' => $updated,
        ]);
    }

    public function tame(Request $request, EncounterTicket $ticket): JsonResponse
    {
        $result = $this->battleService->attemptTame($request->user(), $ticket);

        return response()->json([
            'battle' => $result['ticket']->battle_state,
            'ticket' => $result['ticket'],
            'chance' => $result['chance'],
            'roll' => $result['roll'],
            'success' => $result['success'],
        ]);
    }

    public function run(Request $request, EncounterTicket $ticket): JsonResponse
    {
        $updated = $this->battleService->run($request->user(), $ticket);

        return response()->json([
            'battle' => $updated->battle_state,
            'ticket' => $updated,
        ]);
    }
}
