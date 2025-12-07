<?php

namespace Tests\Feature;

use App\Models\EncounterTicket;
use App\Models\MonsterSpecies;
use App\Models\MonsterSpeciesStage;
use App\Models\PlayerMonster;
use App\Models\Type;
use App\Models\User;
use Database\Seeders\TypeEffectivenessSeeder;
use Database\Seeders\TypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WildBattleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([TypeSeeder::class, TypeEffectivenessSeeder::class]);
    }

    public function test_tame_chance_scales_with_hp(): void
    {
        [$user, $species] = $this->buildUserAndSpecies();
        $token = $user->createToken('test')->plainTextToken;

        $highHpTicket = $this->spawnTicket($user, $species, 80, 80);
        $this->withToken($token)->getJson("/api/encounters/{$highHpTicket->id}/battle");
        $highChance = $this->withToken($token)->postJson("/api/encounters/{$highHpTicket->id}/battle/tame")
            ->json('chance');

        $lowHpTicket = $this->spawnTicket($user, $species, 10, 80);
        $this->withToken($token)->getJson("/api/encounters/{$lowHpTicket->id}/battle");
        $lowChance = $this->withToken($token)->postJson("/api/encounters/{$lowHpTicket->id}/battle/tame")
            ->json('chance');

        $this->assertGreaterThan($highChance, $lowChance);
    }

    public function test_move_reduces_hp_and_persists(): void
    {
        [$user, $species] = $this->buildUserAndSpecies();
        $token = $user->createToken('test')->plainTextToken;
        $ticket = $this->spawnTicket($user, $species, 60, 60);

        $this->withToken($token)->getJson("/api/encounters/{$ticket->id}/battle");
        $response = $this->withToken($token)->postJson("/api/encounters/{$ticket->id}/battle/move", ['style' => 'monster']);

        $response->assertOk();
        $ticket->refresh();

        $this->assertLessThan(60, $ticket->current_hp);
    }

    public function test_run_ends_battle(): void
    {
        [$user, $species] = $this->buildUserAndSpecies();
        $token = $user->createToken('test')->plainTextToken;
        $ticket = $this->spawnTicket($user, $species, 40, 40);

        $this->withToken($token)->getJson("/api/encounters/{$ticket->id}/battle");
        $response = $this->withToken($token)->postJson("/api/encounters/{$ticket->id}/battle/run");

        $response->assertOk();

        $ticket->refresh();
        $this->assertEquals(EncounterTicket::STATUS_RESOLVED, $ticket->status);
        $this->assertFalse($ticket->battle_state['active']);
    }

    private function buildUserAndSpecies(): array
    {
        $user = User::factory()->create();
        $water = Type::where('name', 'Water')->first();

        $species = MonsterSpecies::factory()->create([
            'primary_type_id' => $water->id,
            'capture_rate' => 180,
        ]);

        MonsterSpeciesStage::factory()->create([
            'species_id' => $species->id,
            'stage_number' => 1,
            'hp' => 60,
            'attack' => 30,
            'defense' => 25,
            'sp_attack' => 28,
            'sp_defense' => 22,
        ]);

        PlayerMonster::create([
            'user_id' => $user->id,
            'species_id' => $species->id,
            'level' => 5,
            'exp' => 0,
            'current_hp' => 55,
            'max_hp' => 55,
            'nickname' => null,
            'is_in_team' => true,
            'team_slot' => 1,
        ]);

        return [$user, $species];
    }

    private function spawnTicket(User $user, MonsterSpecies $species, int $hp, int $maxHp): EncounterTicket
    {
        return EncounterTicket::create([
            'user_id' => $user->id,
            'zone_id' => 1,
            'species_id' => $species->id,
            'rolled_level' => 5,
            'seed' => 123,
            'status' => EncounterTicket::STATUS_ACTIVE,
            'expires_at' => now()->addMinutes(5),
            'current_hp' => $hp,
            'max_hp' => $maxHp,
        ]);
    }
}

