<?php

namespace App\Console\Commands;

use App\Models\Battle;
use App\Models\MatchmakingQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RunMatchmaker extends Command
{
    protected $signature = 'pvp:matchmake';

    protected $description = 'Pair players in the ranked queue and create pending battles';

    public function handle(): int
    {
        $entries = MatchmakingQueue::query()
            ->with('pvpProfile')
            ->where('mode', 'ranked')
            ->orderBy('queued_at')
            ->get();

        if ($entries->count() < 2) {
            $this->info('Not enough players queued for ranked matchmaking.');

            return self::SUCCESS;
        }

        $queue = $entries->sortBy(fn ($entry) => $entry->pvpProfile?->mmr ?? 1000)->values();
        $pairings = [];

        while ($queue->count() > 1) {
            $first = $queue->shift();
            $closestIndex = null;
            $closestDiff = PHP_INT_MAX;

            foreach ($queue as $index => $candidate) {
                $diff = abs(($candidate->pvpProfile?->mmr ?? 1000) - ($first->pvpProfile?->mmr ?? 1000));

                if ($diff < $closestDiff) {
                    $closestDiff = $diff;
                    $closestIndex = $index;
                }
            }

            if ($closestIndex === null) {
                break;
            }

            $second = $queue->pull($closestIndex);
            $pairings[] = [$first, $second];
        }

        DB::transaction(function () use ($pairings) {
            foreach ($pairings as [$first, $second]) {
                Battle::query()->create([
                    'seed' => (string) random_int(1, PHP_INT_MAX),
                    'status' => 'pending',
                    'player1_id' => $first->user_id,
                    'player2_id' => $second->user_id,
                    'meta_json' => [
                        'mode' => 'ranked',
                        'matched_at' => now(),
                    ],
                ]);

                MatchmakingQueue::query()->whereIn('id', [$first->id, $second->id])->delete();
            }
        });

        $this->info(sprintf('Created %d battle(s) from ranked queue.', count($pairings)));

        return self::SUCCESS;
    }
}
