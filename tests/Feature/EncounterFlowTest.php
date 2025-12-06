<?php

namespace Tests\Feature;

use App\Events\EncounterIssued;
use App\Models\EncounterTicket;
use App\Models\MonsterSpecies;
use App\Models\User;
use App\Models\Zone;
use App\Models\ZoneSpawnEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EncounterFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_teleport_blocked_and_logged(): void
    {
        Log::spy();
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        Carbon::setTestNow(Carbon::parse('2025-01-01 10:00:00'));

        $this->withToken($token)->postJson('/api/location/update', [
            'lat' => 37.0,
            'lng' => -122.0,
            'accuracy_m' => 5,
        ])->assertOk();

        Carbon::setTestNow(Carbon::parse('2025-01-01 10:00:05'));

        $response = $this->withToken($token)->postJson('/api/location/update', [
            'lat' => 38.0,
            'lng' => -123.0,
            'accuracy_m' => 5,
        ]);

        $response->assertStatus(422);
        Log::shouldHaveReceived('warning')->once();
        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'type' => 'teleport',
        ]);
    }

    public function test_location_update_rate_limited(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        for ($i = 0; $i < 5; $i++) {
            $this->withToken($token)->postJson('/api/location/update', [
                'lat' => 37.0,
                'lng' => -122.0,
                'accuracy_m' => 5,
            ])->assertOk();
        }

        $response = $this->withToken($token)->postJson('/api/location/update', [
            'lat' => 37.1,
            'lng' => -122.1,
            'accuracy_m' => 5,
        ]);

        $response->assertStatus(429);
    }

    public function test_high_priority_zone_controls_spawns(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $speciesA = MonsterSpecies::factory()->create();
        $speciesB = MonsterSpecies::factory()->create();

        $lowPriorityZone = Zone::create([
            'name' => 'Low',
            'priority' => 5,
            'is_active' => true,
            'shape_type' => 'circle',
            'center' => 'POINT(0 0)',
            'radius_m' => 2000,
            'min_lat' => -1,
            'max_lat' => 1,
            'min_lng' => -1,
            'max_lng' => 1,
            'rules_json' => [],
        ]);

        $highPriorityZone = Zone::create([
            'name' => 'High',
            'priority' => 10,
            'is_active' => true,
            'shape_type' => 'circle',
            'center' => 'POINT(0 0)',
            'radius_m' => 2000,
            'min_lat' => -1,
            'max_lat' => 1,
            'min_lng' => -1,
            'max_lng' => 1,
            'rules_json' => [],
        ]);

        ZoneSpawnEntry::create([
            'zone_id' => $lowPriorityZone->id,
            'species_id' => $speciesA->id,
            'weight' => 1,
            'min_level' => 1,
            'max_level' => 5,
            'rarity_tier' => 'common',
        ]);

        ZoneSpawnEntry::create([
            'zone_id' => $highPriorityZone->id,
            'species_id' => $speciesB->id,
            'weight' => 1,
            'min_level' => 1,
            'max_level' => 5,
            'rarity_tier' => 'common',
        ]);

        $response = $this->withToken($token)->postJson('/api/location/update', [
            'lat' => 0,
            'lng' => 0,
            'accuracy_m' => 3,
        ]);

        $response->assertOk();
        $this->assertSame($speciesB->id, $response->json('encounter.species_id'));
    }

    public function test_expired_ticket_is_not_returned(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $species = MonsterSpecies::factory()->create();
        $zone = Zone::create([
            'name' => 'Expired Zone',
            'priority' => 1,
            'is_active' => true,
            'shape_type' => 'circle',
            'center' => 'POINT(0 0)',
            'radius_m' => 2000,
            'min_lat' => -1,
            'max_lat' => 1,
            'min_lng' => -1,
            'max_lng' => 1,
            'rules_json' => [],
        ]);

        EncounterTicket::create([
            'user_id' => $user->id,
            'zone_id' => $zone->id,
            'species_id' => $species->id,
            'rolled_level' => 3,
            'seed' => 123,
            'status' => EncounterTicket::STATUS_ACTIVE,
            'expires_at' => Carbon::now()->subMinute(),
        ]);

        $response = $this->withToken($token)->getJson('/api/encounters/current');

        $response->assertOk();
        $this->assertNull($response->json('encounter'));
        $this->assertDatabaseHas('encounter_tickets', [
            'status' => EncounterTicket::STATUS_EXPIRED,
        ]);
    }

    public function test_encounter_issue_rate_limit(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $species = MonsterSpecies::factory()->create();
        $zone = Zone::create([
            'name' => 'RL Zone',
            'priority' => 1,
            'is_active' => true,
            'shape_type' => 'circle',
            'center' => 'POINT(0 0)',
            'radius_m' => 2000,
            'min_lat' => -1,
            'max_lat' => 1,
            'min_lng' => -1,
            'max_lng' => 1,
            'rules_json' => [],
        ]);

        ZoneSpawnEntry::create([
            'zone_id' => $zone->id,
            'species_id' => $species->id,
            'weight' => 1,
            'min_level' => 1,
            'max_level' => 5,
            'rarity_tier' => 'common',
        ]);

        for ($i = 0; $i < 3; $i++) {
            $response = $this->withToken($token)->postJson('/api/location/update', [
                'lat' => 0,
                'lng' => 0,
                'accuracy_m' => 5,
            ]);

            $response->assertOk();
            EncounterTicket::whereKey($response->json('encounter.id'))
                ->update(['status' => EncounterTicket::STATUS_RESOLVED]);
        }

        $response = $this->withToken($token)->postJson('/api/location/update', [
            'lat' => 0,
            'lng' => 0,
            'accuracy_m' => 5,
        ]);

        $response->assertStatus(429);
    }

    public function test_tampered_ticket_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $speciesA = MonsterSpecies::factory()->create();
        $speciesB = MonsterSpecies::factory()->create();
        $zone = Zone::create([
            'name' => 'Integrity Zone',
            'priority' => 1,
            'is_active' => true,
            'shape_type' => 'circle',
            'center' => 'POINT(0 0)',
            'radius_m' => 2000,
            'min_lat' => -1,
            'max_lat' => 1,
            'min_lng' => -1,
            'max_lng' => 1,
            'rules_json' => [],
        ]);

        ZoneSpawnEntry::create([
            'zone_id' => $zone->id,
            'species_id' => $speciesA->id,
            'weight' => 1,
            'min_level' => 1,
            'max_level' => 5,
            'rarity_tier' => 'common',
        ]);

        $ticket = $this->withToken($token)->postJson('/api/location/update', [
            'lat' => 0,
            'lng' => 0,
            'accuracy_m' => 5,
        ])->json('encounter');

        EncounterTicket::whereKey($ticket['id'])->update(['species_id' => $speciesB->id]);

        $response = $this->withToken($token)->postJson("/api/encounters/{$ticket['id']}/resolve-capture");

        $response->assertStatus(400);
        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'type' => 'encounter_integrity',
        ]);
        $this->assertDatabaseHas('encounter_tickets', [
            'id' => $ticket['id'],
            'status' => EncounterTicket::STATUS_ACTIVE,
        ]);
    }

    public function test_spawn_rules_seed_encounter_when_entries_empty(): void
    {
        Event::fake([EncounterIssued::class]);

        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        MonsterSpecies::factory()->count(2)->create();

        $zone = Zone::create([
            'name' => 'Rule Zone',
            'priority' => 1,
            'is_active' => true,
            'shape_type' => 'circle',
            'center' => 'POINT(0 0)',
            'radius_m' => 2000,
            'min_lat' => -1,
            'max_lat' => 1,
            'min_lng' => -1,
            'max_lng' => 1,
            'rules_json' => [],
            'spawn_strategy' => 'manual',
            'spawn_rules' => ['num_species' => 1],
        ]);

        $response = $this->withToken($token)->postJson('/api/location/update', [
            'lat' => 0,
            'lng' => 0,
            'accuracy_m' => 5,
        ]);

        $response->assertOk();
        $this->assertNotNull($response->json('encounter'));
        $this->assertDatabaseCount('zone_spawn_entries', 1);

        Event::assertDispatched(EncounterIssued::class);
    }
}
