<?php

namespace App\Domain\Pvp;

use App\Domain\Battle\BattleEngine;
use App\Events\PvpMatchFound;
use App\Events\PvpSearchStatus;
use App\Models\Battle;
use App\Models\MatchmakingQueue;
use App\Models\MonsterInstance;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LiveMatchmaker
{
    public const SEARCH_TIMEOUT_SECONDS = 45;

    public function __construct(
        private readonly BattleEngine $engine,
        private readonly PvpRankingService $rankingService,
    ) {
    }

    /**
     * Add (or refresh) a player in the queue, attempt to match immediately, and broadcast search state.
     */
    public function join(User $user, string $mode = 'ranked'): array
    {
        $profile = $this->rankingService->ensureProfile($user->id);

        $entry = MatchmakingQueue::updateOrCreate(
            ['user_id' => $user->id],
            [
                'mode' => $mode,
                'queued_at' => now(),
            ],
        )->load('pvpProfile');

        $match = $this->findMatchFor($entry);

        if ($match) {
            $battle = $this->createBattleFromPair($entry, $match, $mode);

            return [
                'matched' => true,
                'battle' => $battle,
                'opponent_id' => $match->user_id,
            ];
        }

        broadcast(new PvpSearchStatus($user->id, [
            'mode' => $mode,
            'mmr' => $profile->mmr,
            'ladder_window' => $this->windowForEntry($entry),
            'queued_at' => $entry->queued_at?->toIso8601String(),
            'queue_size' => MatchmakingQueue::where('mode', $mode)->count(),
            'search_timeout' => self::SEARCH_TIMEOUT_SECONDS,
            'message' => 'Searching for '.$mode.' opponents...'
        ]));

        return [
            'matched' => false,
            'entry' => $entry,
        ];
    }

    /**
     * Process the current queue (for CLI/manual triggering) and return number of pairings created.
     */
    public function processQueue(string $mode = 'ranked'): int
    {
        $pairings = 0;

        $entries = MatchmakingQueue::query()
            ->with('pvpProfile')
            ->where('mode', $mode)
            ->orderBy('queued_at')
            ->get();

        $consumed = [];

        foreach ($entries as $entry) {
            if (in_array($entry->id, $consumed, true)) {
                continue;
            }

            $match = $this->findMatchFor($entry, $entries);

            if (! $match) {
                continue;
            }

            $consumed[] = $entry->id;
            $consumed[] = $match->id;

            $this->createBattleFromPair($entry, $match, $mode);
            $pairings++;
        }

        return $pairings;
    }

    /**
     * Remove a player from the queue.
     */
    public function leave(User $user): void
    {
        MatchmakingQueue::where('user_id', $user->id)->delete();
        broadcast(new PvpSearchStatus($user->id, [
            'mode' => 'ranked',
            'message' => 'You left the queue.',
            'queue_size' => MatchmakingQueue::count(),
        ]));
    }

    public function windowForEntry(?MatchmakingQueue $entry): int
    {
        if (! $entry) {
            return 200;
        }

        $waitSeconds = now()->diffInSeconds($entry->queued_at ?? now());

        return min(800, 200 + ($waitSeconds * 15));
    }

    private function findMatchFor(MatchmakingQueue $entry, ?EloquentCollection $pool = null): ?MatchmakingQueue
    {
        $pool ??= MatchmakingQueue::query()
            ->with('pvpProfile')
            ->where('mode', $entry->mode)
            ->where('id', '!=', $entry->id)
            ->orderBy('queued_at')
            ->get();

        $entryWindow = $this->windowForEntry($entry);
        $entryMmr = $entry->pvpProfile?->mmr ?? 1000;

        $bestMatch = null;
        $closestDiff = PHP_INT_MAX;

        foreach ($pool as $candidate) {
            if ($candidate->id === $entry->id || $candidate->mode !== $entry->mode) {
                continue;
            }

            $candidateMmr = $candidate->pvpProfile?->mmr ?? 1000;
            $diff = abs($candidateMmr - $entryMmr);
            $candidateWindow = $this->windowForEntry($candidate);
            $allowedDiff = max($entryWindow, $candidateWindow);

            if ($diff <= $allowedDiff && $diff < $closestDiff) {
                $bestMatch = $candidate;
                $closestDiff = $diff;
            }
        }

        return $bestMatch;
    }

    private function createBattleFromPair(MatchmakingQueue $entry, MatchmakingQueue $opponent, string $mode): Battle
    {
        $player1 = User::findOrFail($entry->user_id);
        $player2 = User::findOrFail($opponent->user_id);

        $player1Party = $this->buildParty($player1);
        $player2Party = $this->buildParty($player2);
        $seed = random_int(1, PHP_INT_MAX);

        $state = $this->engine->initialize($player1, $player2, $player1Party, $player2Party, $seed);
        $state['mode'] = $mode;
        $state['matched_at'] = now()->toIso8601String();

        $battle = DB::transaction(function () use ($entry, $opponent, $player1, $player2, $seed, $mode, $state) {
            $battle = Battle::query()->create([
                'seed' => (string) $seed,
                'status' => 'active',
                'player1_id' => $player1->id,
                'player2_id' => $player2->id,
                'started_at' => now(),
                'meta_json' => $state,
                'winner_user_id' => null,
            ]);

            MatchmakingQueue::whereIn('id', [$entry->id, $opponent->id])->delete();

            return $battle;
        });

        broadcast(new PvpMatchFound(
            battleId: $battle->id,
            playerAId: $player1->id,
            playerBId: $player2->id,
            mode: $mode,
            playerAName: $player1->name,
            playerBName: $player2->name,
        ));

        return $battle;
    }

    private function buildParty(User $user): Collection
    {
        return MonsterInstance::query()
            ->with(['currentStage', 'species.primaryType', 'species.secondaryType', 'moves.move.type'])
            ->where('user_id', $user->id)
            ->orderByDesc('level')
            ->orderBy('id')
            ->take(3)
            ->get();
    }
}
