<?php

namespace App\Console\Commands;

use App\Domain\Pvp\PvpRankingService;
use App\Events\BattleUpdated;
use App\Models\Battle;
use App\Models\BattleTurn;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ResolveExpiredBattles extends Command
{
    protected $signature = 'pvp:resolve-expired-turns';

    protected $description = 'Resolve PvP battles whose turn timers have expired.';

    private const DEFAULT_TURN_TIMEOUT_SECONDS = 30;

    public function __construct(private readonly PvpRankingService $rankingService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $now = Carbon::now()->toIso8601String();

        Battle::query()
            ->where('status', 'active')
            ->whereRaw("meta_json->>'turn_expires_at' IS NOT NULL")
            ->whereRaw("meta_json->>'turn_expires_at' <= ?", [$now])
            ->orderBy('id')
            ->chunk(50, function (Collection $battles) {
                foreach ($battles as $battle) {
                    $this->resolveBattle($battle);
                }
            });

        return self::SUCCESS;
    }

    private function resolveBattle(Battle $battle): void
    {
        $updatedBattle = DB::transaction(function () use ($battle) {
            $lockedBattle = Battle::query()->whereKey($battle->id)->lockForUpdate()->first();

            if (! $lockedBattle || $lockedBattle->status !== 'active') {
                return null;
            }

            $lockedBattle->loadMissing(['player1', 'player2']);

            $state = $lockedBattle->meta_json ?? [];
            $expiresAt = isset($state['turn_expires_at']) ? Carbon::parse($state['turn_expires_at']) : null;

            if (! $expiresAt || $expiresAt->isFuture()) {
                return null;
            }

            $forcedSwitchUserId = $state['forced_switch_user_id'] ?? null;
            $timedOutUserId = $forcedSwitchUserId ?? ($state['next_actor_id'] ?? null);

            if (! $timedOutUserId) {
                return null;
            }

            $turnNumber = $state['turn'] ?? 1;
            $message = $forcedSwitchUserId
                ? sprintf('%s failed to swap in time.', $this->playerName($lockedBattle, $timedOutUserId))
                : sprintf('%s timed out.', $this->playerName($lockedBattle, $timedOutUserId));
            $logEntry = $this->buildTimeoutLog($turnNumber, $timedOutUserId, $message);

            if ($forcedSwitchUserId) {
                $winnerId = $this->opponentId($lockedBattle, $timedOutUserId);

                $state['log'][] = $logEntry;
                $state['turn'] = $turnNumber + 1;
                $state['next_actor_id'] = null;
                $state['forced_switch_user_id'] = null;
                $state['forced_switch_reason'] = null;
                $state['turn_started_at'] = null;
                $state['turn_expires_at'] = null;

                $lockedBattle->fill([
                    'meta_json' => $state,
                    'status' => 'completed',
                    'winner_user_id' => $winnerId,
                    'ended_at' => now(),
                ])->save();

                BattleTurn::query()->create([
                    'battle_id' => $lockedBattle->id,
                    'turn_number' => $turnNumber,
                    'actor_user_id' => $timedOutUserId,
                    'action_json' => ['type' => 'timeout'],
                    'result_json' => $logEntry,
                ]);

                return $lockedBattle->fresh();
            }

            $nextActorId = $this->opponentId($lockedBattle, $timedOutUserId);

            if (! $nextActorId) {
                return null;
            }

            $timeoutSeconds = $state['turn_timeout_seconds'] ?? self::DEFAULT_TURN_TIMEOUT_SECONDS;
            $turnStartedAt = Carbon::now();

            $state['log'][] = $logEntry;
            $state['turn'] = $turnNumber + 1;
            $state['next_actor_id'] = $nextActorId;
            $state['turn_started_at'] = $turnStartedAt->toIso8601String();
            $state['turn_expires_at'] = $turnStartedAt->copy()->addSeconds($timeoutSeconds)->toIso8601String();

            $lockedBattle->update(['meta_json' => $state]);

            BattleTurn::query()->create([
                'battle_id' => $lockedBattle->id,
                'turn_number' => $turnNumber,
                'actor_user_id' => $timedOutUserId,
                'action_json' => ['type' => 'timeout'],
                'result_json' => $logEntry,
            ]);

            return $lockedBattle->fresh();
        });

        if (! $updatedBattle) {
            return;
        }

        if ($updatedBattle->status === 'completed' && $updatedBattle->winner_user_id !== null) {
            $this->rankingService->handleBattleCompletion($updatedBattle);
        }

        broadcast(new BattleUpdated(
            battleId: $updatedBattle->id,
            state: $updatedBattle->meta_json ?? [],
            status: $updatedBattle->status,
            nextActorId: data_get($updatedBattle->meta_json, 'next_actor_id'),
            winnerUserId: $updatedBattle->winner_user_id,
        ));
    }

    private function playerName(Battle $battle, int $userId): string
    {
        if ($battle->player1_id === $userId) {
            return $battle->player1->name ?? 'Player '.$userId;
        }

        if ($battle->player2_id === $userId) {
            return $battle->player2->name ?? 'Player '.$userId;
        }

        return 'Player '.$userId;
    }

    private function opponentId(Battle $battle, int $userId): ?int
    {
        return match ($userId) {
            $battle->player1_id => $battle->player2_id,
            $battle->player2_id => $battle->player1_id,
            default => null,
        };
    }

    private function buildTimeoutLog(int $turnNumber, int $actorUserId, string $message): array
    {
        return [
            'turn' => $turnNumber,
            'actor_user_id' => $actorUserId,
            'action' => ['type' => 'timeout'],
            'events' => [
                [
                    'type' => 'log',
                    'message' => $message,
                ],
            ],
        ];
    }
}
