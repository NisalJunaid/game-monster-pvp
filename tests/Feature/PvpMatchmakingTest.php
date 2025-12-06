<?php

namespace Tests\Feature;

use App\Models\MatchmakingQueue;
use App\Models\MonsterInstance;
use App\Models\MonsterSpecies;
use App\Models\Move;
use App\Models\PvpProfile;
use App\Models\User;
use Database\Seeders\MonsterSpeciesSeeder;
use Database\Seeders\MoveSeeder;
use Database\Seeders\TypeEffectivenessSeeder;
use Database\Seeders\TypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PvpMatchmakingTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_can_queue_and_dequeue_for_pvp(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $queueResponse = $this->withToken($token)->postJson('/api/pvp/queue', ['mode' => 'ranked']);
        $queueResponse->assertCreated();

        $this->assertDatabaseHas('pvp_profiles', [
            'user_id' => $user->id,
            'mmr' => 1000,
        ]);

        $this->assertDatabaseHas('matchmaking_queue', [
            'user_id' => $user->id,
            'mode' => 'ranked',
        ]);

        $dequeueResponse = $this->withToken($token)->postJson('/api/pvp/dequeue');
        $dequeueResponse->assertNoContent();

        $this->assertDatabaseMissing('matchmaking_queue', [
            'user_id' => $user->id,
        ]);
    }

    public function test_matchmaker_pairs_ranked_players(): void
    {
        $players = User::factory()->count(2)->create();
        PvpProfile::query()->create(['user_id' => $players[0]->id, 'mmr' => 1100]);
        PvpProfile::query()->create(['user_id' => $players[1]->id, 'mmr' => 900]);

        MatchmakingQueue::query()->create([
            'user_id' => $players[0]->id,
            'mode' => 'ranked',
            'queued_at' => now()->subMinute(),
        ]);

        MatchmakingQueue::query()->create([
            'user_id' => $players[1]->id,
            'mode' => 'ranked',
            'queued_at' => now(),
        ]);

        $this->artisan('pvp:matchmake')->assertExitCode(0);

        $this->assertDatabaseCount('battles', 1);
        $this->assertDatabaseCount('matchmaking_queue', 0);
    }

    public function test_mmr_updates_after_ranked_battle_concludes(): void
    {
        $this->seed([TypeSeeder::class, TypeEffectivenessSeeder::class, MoveSeeder::class, MonsterSpeciesSeeder::class]);

        [$player, $opponent, $tokenPlayer, $tokenOpponent] = $this->buildPlayers();
        [$battleId] = $this->startBattle($player, $opponent, $tokenPlayer, $tokenOpponent, 1337);

        $currentToken = $tokenPlayer;
        $maxSteps = 10;
        while ($maxSteps-- > 0) {
            $response = $this->withToken($currentToken)->postJson("/api/battles/{$battleId}/act", ['type' => 'move', 'slot' => 1]);
            $response->assertOk();
            $battleData = $response->json('data');

            if ($battleData['status'] === 'completed') {
                $winnerProfile = PvpProfile::where('user_id', $player->id)->first();
                $loserProfile = PvpProfile::where('user_id', $opponent->id)->first();

                $this->assertEquals($player->id, $battleData['winner_user_id']);
                $this->assertNotNull($winnerProfile);
                $this->assertNotNull($loserProfile);
                $this->assertGreaterThan(1000, $winnerProfile->mmr);
                $this->assertLessThan(1000, $loserProfile->mmr);
                $this->assertEquals(1, $winnerProfile->wins);
                $this->assertEquals(1, $loserProfile->losses);

                return;
            }

            $currentToken = $currentToken === $tokenPlayer ? $tokenOpponent : $tokenPlayer;
        }

        $this->fail('Battle did not complete within expected number of turns');
    }

    private function buildPlayers(string $playerType = 'Water', string $opponentType = 'Fire'): array
    {
        $player = User::factory()->create();
        $opponent = User::factory()->create();

        $playerMonster = $this->spawnMonster($player, $playerType, ['Water Jet']);
        $opponentMonster = $this->spawnMonster($opponent, $opponentType, ['Ember']);

        $tokenPlayer = $player->createToken('test')->plainTextToken;
        $tokenOpponent = $opponent->createToken('test')->plainTextToken;

        return [$player, $opponent, $tokenPlayer, $tokenOpponent];
    }

    private function startBattle(User $player, User $opponent, string $tokenPlayer, string $tokenOpponent, int $seed): array
    {
        [$playerMonster, $opponentMonster] = MonsterInstance::query()->whereIn('user_id', [$player->id, $opponent->id])->get()->
            partition(fn ($instance) => $instance->user_id === $player->id)->map->pluck('id')->map->values();

        $response = $this->withToken($tokenPlayer)->postJson('/api/battles/challenge', [
            'opponent_user_id' => $opponent->id,
            'player_party' => $playerMonster->all(),
            'opponent_party' => $opponentMonster->all(),
            'seed' => $seed,
        ]);

        $response->assertCreated();

        return [$response->json('data.id'), $response->json('data.meta')];
    }

    private function spawnMonster(User $owner, string $typeName, array $moves): MonsterInstance
    {
        $species = MonsterSpecies::whereHas('primaryType', fn ($query) => $query->where('name', $typeName))->first();
        $moveModels = Move::whereIn('name', $moves)->get();

        $instance = MonsterInstance::factory()->create([
            'user_id' => $owner->id,
            'species_id' => $species->id,
            'current_stage_id' => $species->stages()->orderBy('stage_number')->first()->id,
            'level' => 10,
        ]);

        foreach ($moveModels as $index => $move) {
            $instance->moves()->create([
                'move_id' => $move->id,
                'slot' => $index + 1,
            ]);
        }

        return $instance->fresh(['currentStage', 'species', 'moves.move.type']);
    }
}
