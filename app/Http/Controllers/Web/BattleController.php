<?php

namespace App\Http\Controllers\Web;

use App\Domain\Battle\BattleEngine;
use App\Domain\Pvp\PvpRankingService;
use App\Events\BattleUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\BattleActionRequest;
use App\Models\Battle;
use App\Models\BattleTurn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class BattleController extends Controller
{
    public function __construct(private readonly BattleEngine $engine, private readonly PvpRankingService $rankingService)
    {
    }

    public function show(Request $request, Battle $battle)
    {
        $this->assertParticipant($request, $battle);

        $battle->load(['turns', 'player1', 'player2', 'winner']);

        return view('battles.show', [
            'battle' => $battle,
            'state' => $battle->meta_json,
        ]);
    }

    public function act(BattleActionRequest $request, Battle $battle): RedirectResponse
    {
        $actor = $this->assertParticipant($request, $battle);

        if ($battle->status !== 'active') {
            return back()->withErrors(['battle' => 'Battle is not active.']);
        }

        $meta = $battle->meta_json;

        if (($meta['next_actor_id'] ?? null) !== $actor->id) {
            return back()->withErrors(['battle' => 'It is not your turn yet.']);
        }

        try {
            [$state, $result, $hasEnded, $winnerId] = $this->engine->applyAction($battle, $actor->id, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['action' => $exception->getMessage()]);
        }

        $battle->update([
            'meta_json' => $state,
            'status' => $hasEnded ? 'completed' : 'active',
            'winner_user_id' => $winnerId,
            'ended_at' => $hasEnded ? now() : null,
        ]);

        BattleTurn::query()->create([
            'battle_id' => $battle->id,
            'turn_number' => $result['turn'],
            'actor_user_id' => $actor->id,
            'action_json' => $request->validated(),
            'result_json' => $result,
        ]);

        if ($hasEnded && $winnerId !== null) {
            $this->rankingService->handleBattleCompletion($battle->fresh());
        }

        broadcast(new BattleUpdated(
            battleId: $battle->id,
            state: $state,
            status: $hasEnded ? 'completed' : 'active',
            nextActorId: $state['next_actor_id'] ?? null,
            winnerUserId: $winnerId,
        ));

        return redirect()->route('battles.show', $battle)->with('status', 'Action submitted.');
    }

    private function assertParticipant(Request $request, Battle $battle)
    {
        $user = $request->user();

        if (! in_array($user->id, [$battle->player1_id, $battle->player2_id], true)) {
            abort(Response::HTTP_FORBIDDEN, 'You are not part of this battle.');
        }

        return $user;
    }
}
