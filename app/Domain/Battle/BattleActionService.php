<?php

namespace App\Domain\Battle;

use App\Events\BattleUpdated;
use App\Models\Battle;
use App\Models\BattleTurn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class BattleActionService
{
    public function __construct(private readonly BattleEngine $engine)
    {
    }

    /**
     * Apply a battle action within a transaction, locking the battle row to avoid race conditions.
     */
    public function handleAction(Battle $battle, User $actor, array $action): array
    {
        $outcome = DB::transaction(function () use ($battle, $actor, $action) {
            $lockedBattle = Battle::query()->whereKey($battle->id)->lockForUpdate()->firstOrFail();
            $meta = $lockedBattle->meta_json ?? [];

            $this->guardTurnOrder($meta, $actor, $action);

            [$state, $result, $hasEnded, $winnerId] = $this->engine->applyAction($lockedBattle, $actor->id, $action);

            if ($action['type'] === 'swap') {
                [$state, $result] = $this->appendSwapLog($state, $result, $actor->name ?? ('Player '.$actor->id));
            }

            $lockedBattle->fill([
                'meta_json' => $state,
                'status' => $hasEnded ? 'completed' : 'active',
                'winner_user_id' => $winnerId,
                'ended_at' => $hasEnded ? now() : null,
            ])->save();

            BattleTurn::query()->create([
                'battle_id' => $lockedBattle->id,
                'turn_number' => $result['turn'],
                'actor_user_id' => $actor->id,
                'action_json' => $action,
                'result_json' => $result,
            ]);

            return [
                'battle' => $lockedBattle->fresh(['turns']),
                'state' => $state,
                'result' => $result,
                'hasEnded' => $hasEnded,
                'winnerId' => $winnerId,
            ];
        });

        broadcast(new BattleUpdated(
            battleId: $outcome['battle']->id,
            state: $outcome['state'],
            status: $outcome['battle']->status,
            nextActorId: $outcome['state']['next_actor_id'] ?? null,
            winnerUserId: $outcome['winnerId'],
        ));

        return $outcome;
    }

    private function guardTurnOrder(array $state, User $actor, array $action): void
    {
        $forcedSwitchUserId = $state['forced_switch_user_id'] ?? null;

        if ($forcedSwitchUserId !== null && $forcedSwitchUserId !== $actor->id) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Waiting for opponent to swap.');
        }

        if ($forcedSwitchUserId === $actor->id && $action['type'] !== 'swap') {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'You must swap to continue.');
        }

        if (($state['next_actor_id'] ?? null) !== $actor->id) {
            abort(Response::HTTP_BAD_REQUEST, 'It is not your turn.');
        }
    }

    private function appendSwapLog(array $state, array $result, string $playerName): array
    {
        $participant = $state['participants'][$result['actor_user_id'] ?? null] ?? null;
        $activeIndex = $participant['active_index'] ?? null;
        $activeMonster = $activeIndex !== null ? ($participant['monsters'][$activeIndex] ?? null) : null;

        if ($activeMonster) {
            $result['events'][] = [
                'type' => 'log',
                'message' => sprintf('%s swapped to %s.', $playerName, $activeMonster['name'] ?? 'Unknown monster'),
            ];

            $lastLogIndex = array_key_last($state['log']);
            if ($lastLogIndex !== null) {
                $state['log'][$lastLogIndex] = $result;
            }
        }

        return [$state, $result];
    }
}
