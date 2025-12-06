<?php

namespace Tests\Feature;

use App\Models\MonsterSpecies;
use App\Models\Type;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminZoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_admin_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.zones.map'))
            ->assertForbidden();

        $zone = Zone::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.zones.spawns.store', $zone), [
                'species_id' => MonsterSpecies::factory()->create(['primary_type_id' => Type::factory()])->id,
                'weight' => 1,
                'min_level' => 1,
                'max_level' => 5,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_create_zone_with_polygon(): void
    {
        $admin = User::factory()->admin()->create();

        $payload = [
            'name' => 'Central Park',
            'priority' => 5,
            'is_active' => 1,
            'shape_type' => 'polygon',
            'shape' => [
                'path' => [
                    ['lat' => 40.7644, 'lng' => -73.9738],
                    ['lat' => 40.7644, 'lng' => -73.9710],
                    ['lat' => 40.7677, 'lng' => -73.9710],
                ],
            ],
        ];

        $response = $this->actingAs($admin)->post(route('admin.zones.store'), $payload);

        $response->assertRedirect(route('admin.zones.map'));

        $this->assertDatabaseHas('zones', [
            'name' => 'Central Park',
            'shape_type' => 'polygon',
            'is_active' => true,
        ]);

        $zone = Zone::where('name', 'Central Park')->first();

        $this->assertNotNull($zone);
        $this->assertNotNull($zone->geom);
        $this->assertNull($zone->center);
        $this->assertNull($zone->radius_m);
    }

    public function test_admin_can_create_spawn_entry_for_zone(): void
    {
        $admin = User::factory()->admin()->create();
        $zone = Zone::factory()->create();
        $species = MonsterSpecies::factory()->create(['primary_type_id' => Type::factory()]);

        $response = $this->actingAs($admin)->post(route('admin.zones.spawns.store', $zone), [
            'species_id' => $species->id,
            'weight' => 10,
            'min_level' => 2,
            'max_level' => 7,
            'rarity_tier' => 'rare',
        ]);

        $response->assertRedirect(route('admin.zones.spawns.index', $zone));

        $this->assertDatabaseHas('zone_spawn_entries', [
            'zone_id' => $zone->id,
            'species_id' => $species->id,
            'weight' => 10,
            'rarity_tier' => 'rare',
        ]);
    }
}
