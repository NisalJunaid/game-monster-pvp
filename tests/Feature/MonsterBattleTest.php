<?php

namespace Tests\Feature;

use App\Domain\Battle\BattleSimulator;
use App\Models\Monster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonsterBattleTest extends TestCase
{
    use RefreshDatabase;

    public function test_monsters_can_be_created(): void
    {
        $payload = [
            'name' => 'Goblin',
            'attack' => 10,
            'defense' => 4,
            'health' => 30,
        ];

        $response = $this->postJson('/api/monsters', $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Goblin');
        $this->assertDatabaseHas('monsters', $payload);
    }

    public function test_battle_outcome_is_calculated_on_server(): void
    {
        $attacker = Monster::factory()->create([
            'attack' => 12,
            'defense' => 2,
            'health' => 30,
        ]);

        $defender = Monster::factory()->create([
            'attack' => 5,
            'defense' => 1,
            'health' => 20,
        ]);

        $response = $this->postJson('/api/battles', [
            'attacker_id' => $attacker->id,
            'defender_id' => $defender->id,
            'winner_id' => $defender->id, // client suggestion should be ignored
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.winner.id', $attacker->id);
        $response->assertJsonStructure([
            'data' => [
                'attacker',
                'defender',
                'winner',
                'rounds',
                'log',
            ],
        ]);
    }

    public function test_validation_requires_distinct_monsters(): void
    {
        $monster = Monster::factory()->create();

        $response = $this->postJson('/api/battles', [
            'attacker_id' => $monster->id,
            'defender_id' => $monster->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['attacker_id', 'defender_id']);
    }

    public function test_battle_simulation_service_is_deterministic(): void
    {
        $simulator = new BattleSimulator();

        $first = Monster::factory()->make([
            'id' => 1,
            'attack' => 8,
            'defense' => 2,
            'health' => 25,
        ]);

        $second = Monster::factory()->make([
            'id' => 2,
            'attack' => 7,
            'defense' => 3,
            'health' => 22,
        ]);

        $result = $simulator->simulate($first, $second);

        $this->assertSame($first->name, $result->attacker->name);
        $this->assertSame($second->name, $result->defender->name);
        $this->assertSame($first->name, $result->winner->name);
        $this->assertGreaterThan(0, $result->rounds);
        $this->assertNotEmpty($result->log);
    }
}
