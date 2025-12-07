<?php

namespace Tests\Feature;

use App\Models\MonsterInstance;
use App\Models\MonsterSpecies;
use App\Models\Move;
use App\Models\User;
use Database\Seeders\MonsterSpeciesSeeder;
use Database\Seeders\MoveSeeder;
use Database\Seeders\TypeEffectivenessSeeder;
use Database\Seeders\TypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonsterBattleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([TypeSeeder::class, TypeEffectivenessSeeder::class, MoveSeeder::class, MonsterSpeciesSeeder::class]);
    }

    public function test_deterministic_outcomes_with_same_seed_and_actions(): void
    {
        [$player, $opponent, $tokenPlayer, $tokenOpponent] = $this->buildPlayers();
        [$battleAId, $stateA] = $this->startBattle($player, $opponent, $tokenPlayer, $tokenOpponent, 4242);
        [$battleBId, $stateB] = $this->startBattle($player, $opponent, $tokenPlayer, $tokenOpponent, 4242);

        $sequence = [
            [$battleAId, $tokenPlayer, ['type' => 'move', 'slot' => 1]],
            [$battleAId, $tokenOpponent, ['type' => 'move', 'slot' => 1]],
            [$battleBId, $tokenPlayer, ['type' => 'move', 'slot' => 1]],
            [$battleBId, $tokenOpponent, ['type' => 'move', 'slot' => 1]],
        ];

        foreach ($sequence as [$battleId, $token, $action]) {
            $this->withToken($token)->postJson("/api/battles/{$battleId}/act", $action)->assertOk();
        }

        $battleA = $this->withToken($tokenPlayer)->getJson("/api/battles/{$battleAId}")->json('data.meta');
        $battleB = $this->withToken($tokenPlayer)->getJson("/api/battles/{$battleBId}")->json('data.meta');

        $this->assertSame($battleA['participants'][$player->id]['monsters'][0]['current_hp'], $battleB['participants'][$player->id]['monsters'][0]['current_hp']);
        $this->assertSame($battleA['participants'][$opponent->id]['monsters'][0]['current_hp'], $battleB['participants'][$opponent->id]['monsters'][0]['current_hp']);
    }

    public function test_type_effectiveness_in_damage_calculation(): void
    {
        [$player, $opponent, $tokenPlayer, $tokenOpponent] = $this->buildPlayers('Fire', 'Nature');
        [$battleId] = $this->startBattle($player, $opponent, $tokenPlayer, $tokenOpponent, 7777);

        $response = $this->withToken($tokenPlayer)->postJson("/api/battles/{$battleId}/act", ['type' => 'move', 'slot' => 1]);
        $response->assertOk();

        $damageEvent = collect($response->json('turn.events'))->firstWhere('type', 'damage');
        $this->assertNotNull($damageEvent, 'Damage event missing');
        $this->assertEquals(2.0, $damageEvent['multipliers']['type']);
    }

    public function test_neutral_matchups_require_multiple_turns(): void
    {
        [$player, $opponent, $tokenPlayer, $tokenOpponent] = $this->buildPlayers('Water', 'Water');
        [$battleId] = $this->startBattle($player, $opponent, $tokenPlayer, $tokenOpponent, 1234);

        $this->withToken($tokenPlayer)->postJson("/api/battles/{$battleId}/act", ['type' => 'move', 'slot' => 1])->assertOk();
        $this->withToken($tokenOpponent)->postJson("/api/battles/{$battleId}/act", ['type' => 'move', 'slot' => 1])->assertOk();

        $state = $this->withToken($tokenPlayer)->getJson("/api/battles/{$battleId}")->json('data.meta');

        $playerMon = $state['participants'][$player->id]['monsters'][0];
        $opponentMon = $state['participants'][$opponent->id]['monsters'][0];

        $this->assertGreaterThan(0, $playerMon['current_hp']);
        $this->assertGreaterThan(0, $opponentMon['current_hp']);
        $this->assertLessThan($playerMon['max_hp'] * 0.6, $playerMon['max_hp'] - $playerMon['current_hp']);
        $this->assertLessThan($opponentMon['max_hp'] * 0.6, $opponentMon['max_hp'] - $opponentMon['current_hp']);
    }

    public function test_type_advantage_hits_harder_than_neutral(): void
    {
        [$player, $opponent, $tokenPlayer, $tokenOpponent] = $this->buildPlayers('Fire', 'Nature');
        [$battleId] = $this->startBattle($player, $opponent, $tokenPlayer, $tokenOpponent, 5151);

        $advResponse = $this->withToken($tokenPlayer)->postJson("/api/battles/{$battleId}/act", ['type' => 'move', 'slot' => 1]);
        $advDamage = collect($advResponse->json('turn.events'))->firstWhere('type', 'damage');

        [$neutralPlayer, $neutralOpponent, $neutralTokenPlayer, $neutralTokenOpponent] = $this->buildPlayers('Water', 'Water');
        [$neutralBattle] = $this->startBattle($neutralPlayer, $neutralOpponent, $neutralTokenPlayer, $neutralTokenOpponent, 6161);
        $neutralResponse = $this->withToken($neutralTokenPlayer)->postJson("/api/battles/{$neutralBattle}/act", ['type' => 'move', 'slot' => 1]);
        $neutralDamage = collect($neutralResponse->json('turn.events'))->firstWhere('type', 'damage');

        $this->assertNotNull($advDamage);
        $this->assertNotNull($neutralDamage);
        $this->assertGreaterThanOrEqual(2.0, $advDamage['multipliers']['type']);
        $this->assertGreaterThan($neutralDamage['amount'], $advDamage['amount']);
    }

    public function test_battle_completes_and_sets_winner(): void
    {
        [$player, $opponent, $tokenPlayer, $tokenOpponent] = $this->buildPlayers();
        [$battleId] = $this->startBattle($player, $opponent, $tokenPlayer, $tokenOpponent, 9001);

        $currentToken = $tokenPlayer;
        $maxSteps = 10;
        while ($maxSteps-- > 0) {
            $response = $this->withToken($currentToken)->postJson("/api/battles/{$battleId}/act", ['type' => 'move', 'slot' => 1]);
            $response->assertOk();
            $battleData = $response->json('data');

            if ($battleData['status'] === 'completed') {
                $this->assertEquals($player->id, $battleData['winner_user_id']);
                $this->assertNotNull($battleData['ended_at']);
                $this->assertNotEmpty($battleData['turns']);

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
        [$playerMonster, $opponentMonster] = MonsterInstance::query()->whereIn('user_id', [$player->id, $opponent->id])->get()->partition(fn ($instance) => $instance->user_id === $player->id)->map->pluck('id')->map->values();

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
