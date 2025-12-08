<?php

namespace App\Http\Controllers\Web;

use App\Domain\Battle\BattleActionService;
use App\Domain\Pvp\PvpRankingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\BattleActionRequest;
use App\Models\Battle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class BattleController extends Controller
{
    public function __construct(
        private readonly BattleActionService $actionService,
        private readonly PvpRankingService $rankingService,
    )
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

    public function state(Request $request, Battle $battle)
    {
        $viewer = $this->assertParticipant($request, $battle);

        $battle->load(['player1', 'player2', 'winner']);
        $state = $battle->meta_json;

        return response()->json([
            'battle' => [
                'id' => $battle->id,
                'status' => $battle->status,
                'mode' => $state['mode'] ?? 'ranked',
                'player1_id' => $battle->player1_id,
                'player2_id' => $battle->player2_id,
                'winner_user_id' => $battle->winner_user_id,
            ],
            'players' => [
                $battle->player1_id => $battle->player1?->name ?? 'Player '.$battle->player1_id,
                $battle->player2_id => $battle->player2?->name ?? 'Player '.$battle->player2_id,
            ],
            'state' => $state,
            'viewer_id' => $viewer->id,
        ]);
    }

    public function act(BattleActionRequest $request, Battle $battle): RedirectResponse
    {
        $actor = $this->assertParticipant($request, $battle);

        if ($battle->status !== 'active') {
            return back()->withErrors(['battle' => 'Battle is not active.']);
        }

        $meta = $battle->meta_json;

        if (($meta['forced_switch_user_id'] ?? null) !== null) {
            if (($meta['forced_switch_user_id'] ?? null) !== $actor->id) {
                return back()->withErrors(['action' => 'Waiting for opponent to swap.']);
            }

            if ($request->input('type') !== 'swap') {
                return back()->withErrors(['action' => 'You must swap to continue.']);
            }
        }

        if (($meta['next_actor_id'] ?? null) !== $actor->id) {
            return back()->withErrors(['battle' => 'It is not your turn yet.']);
        }

        try {
            $outcome = $this->actionService->handleAction($battle, $actor, $request->validated());
            $state = $outcome['state'];
            $result = $outcome['result'];
            $hasEnded = $outcome['hasEnded'];
            $winnerId = $outcome['winnerId'];
            $battle = $outcome['battle'];
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['action' => $exception->getMessage()]);
        }

        if ($hasEnded && $winnerId !== null) {
            $this->rankingService->handleBattleCompletion($battle->fresh());
        }

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
