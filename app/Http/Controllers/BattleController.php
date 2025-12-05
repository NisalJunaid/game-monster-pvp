<?php

namespace App\Http\Controllers;

use App\Domain\Battle\BattleSimulator;
use App\Http\Requests\StartBattleRequest;
use App\Models\Monster;
use Illuminate\Http\JsonResponse;

class BattleController extends Controller
{
    public function __construct(private readonly BattleSimulator $simulator)
    {
    }

    public function store(StartBattleRequest $request): JsonResponse
    {
        $data = $request->validated();

        $attacker = Monster::findOrFail($data['attacker_id']);
        $defender = Monster::findOrFail($data['defender_id']);

        $result = $this->simulator->simulate($attacker, $defender);

        return response()->json([
            'data' => [
                'attacker' => $result->attacker,
                'defender' => $result->defender,
                'winner' => $result->winner,
                'rounds' => $result->rounds,
                'log' => $result->log,
            ],
        ]);
    }
}
