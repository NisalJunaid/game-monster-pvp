<?php

namespace App\Http\Controllers;

use App\Domain\Battle\BattleActionService;
use App\Domain\Battle\BattleEngine;
use App\Domain\Pvp\PvpRankingService;
use App\Http\Requests\BattleActionRequest;
use App\Http\Requests\ChallengeBattleRequest;
use App\Models\Battle;
use App\Models\MonsterInstance;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class BattleController extends Controller
{
    public function __construct(
        private readonly BattleEngine $engine,
        private readonly BattleActionService $actionService,
        private readonly PvpRankingService $rankingService,
    )
    {
    }

    public function challenge(ChallengeBattleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $challenger = $request->user();
        $opponent = User::findOrFail($data['opponent_user_id']);

        if ($challenger->id === $opponent->id) {
            abort(Response::HTTP_BAD_REQUEST, 'You cannot challenge yourself.');
        }

        $playerParty = $this->loadParty($challenger->id, $data['player_party']);
        $opponentParty = $this->loadParty($opponent->id, $data['opponent_party']);

        $seed = $data['seed'] ?? random_int(1, PHP_INT_MAX);

        $battle = DB::transaction(function () use ($challenger, $opponent, $seed, $playerParty, $opponentParty) {
            $meta = $this->engine->initialize($challenger, $opponent, $playerParty, $opponentParty, (int) $seed);

            return Battle::query()->create([
                'seed' => (string) $seed,
                'status' => 'active',
                'player1_id' => $challenger->id,
                'player2_id' => $opponent->id,
                'started_at' => now(),
                'meta_json' => $meta,
            ]);
        });

        return response()->json(['data' => $this->serializeBattle($battle)], Response::HTTP_CREATED);
    }

    public function act(BattleActionRequest $request, Battle $battle): JsonResponse
    {
        $actor = $request->user();

        if (! in_array($actor->id, [$battle->player1_id, $battle->player2_id], true)) {
            abort(Response::HTTP_FORBIDDEN, 'You are not part of this battle.');
        }

        if ($battle->status !== 'active') {
            abort(Response::HTTP_BAD_REQUEST, 'Battle is not active.');
        }

        $meta = $battle->meta_json;
        $actorSide = $meta['participants'][$actor->id] ?? null;
        $actorActiveMonster = $actorSide['monsters'][$actorSide['active_index'] ?? null] ?? null;

        if (($actorActiveMonster['current_hp'] ?? 0) <= 0 && $request->input('type') !== 'swap') {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Fainted monsters can’t act.');
        }

        if (($meta['forced_switch_user_id'] ?? null) !== null) {
            if (($meta['forced_switch_user_id'] ?? null) !== $actor->id) {
                abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Waiting for opponent to swap.');
            }

            if ($request->input('type') !== 'swap') {
                abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'You must swap to continue.');
            }
        }

        if (($meta['next_actor_id'] ?? null) !== $actor->id) {
            abort(Response::HTTP_BAD_REQUEST, 'It is not your turn.');
        }

        try {
            $outcome = $this->actionService->handleAction($battle, $actor, $request->validated());
            $state = $outcome['state'];
            $result = $outcome['result'];
            $hasEnded = $outcome['hasEnded'];
            $winnerId = $outcome['winnerId'];
            $battle = $outcome['battle'];
        } catch (\InvalidArgumentException $exception) {
            abort($exception->getCode() ?: Response::HTTP_BAD_REQUEST, $exception->getMessage());
        }

        if ($hasEnded && $winnerId !== null) {
            $this->rankingService->handleBattleCompletion($battle);
        }

        return response()->json([
            'data' => $this->serializeBattle($battle->fresh(['turns'])),
            'turn' => $result,
        ]);
    }

    public function show(Battle $battle): JsonResponse
    {
        $battle->load('turns');

        return response()->json(['data' => $this->serializeBattle($battle)]);
    }

    private function loadParty(int $userId, array $partyIds)
    {
        $party = MonsterInstance::query()
            ->with(['currentStage', 'species.primaryType', 'species.secondaryType', 'moves.move.type'])
            ->where('user_id', $userId)
            ->whereIn('id', $partyIds)
            ->get();

        if ($party->count() !== count($partyIds)) {
            abort(Response::HTTP_BAD_REQUEST, 'Party contains monsters you do not own.');
        }

        return $party;
    }

    private function serializeBattle(Battle $battle): array
    {
        return [
            'id' => $battle->id,
            'status' => $battle->status,
            'seed' => $battle->seed,
            'player1_id' => $battle->player1_id,
            'player2_id' => $battle->player2_id,
            'winner_user_id' => $battle->winner_user_id,
            'started_at' => $battle->started_at,
            'ended_at' => $battle->ended_at,
            'meta' => $battle->meta_json,
            'turns' => $battle->turns->map(function (BattleTurn $turn) {
                return [
                    'turn_number' => $turn->turn_number,
                    'actor_user_id' => $turn->actor_user_id,
                    'action' => $turn->action_json,
                    'result' => $turn->result_json,
                ];
            })->values()->all(),
        ];
    }
}
