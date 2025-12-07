<?php

namespace Tests\Feature;

use App\Models\MonsterSpecies;
use App\Models\MonsterSpeciesStage;
use App\Models\PlayerMonster;
use App\Models\Type;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StarterSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_starter_is_redirected_to_starter_page(): void
    {
        $user = User::factory()->create([
            'has_starter' => false,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('starter.show'));
    }

    public function test_user_can_select_type_and_receive_starter_monster(): void
    {
        $user = User::factory()->create([
            'has_starter' => false,
        ]);

        $type = Type::factory()->create();
        $species = MonsterSpecies::factory()->create([
            'primary_type_id' => $type->id,
            'secondary_type_id' => null,
        ]);

        $stage = MonsterSpeciesStage::factory()->for($species, 'species')->state([
            'stage_number' => 1,
            'hp' => 52,
        ])->create();

        $response = $this->actingAs($user)->post(route('starter.store'), [
            'type_id' => $type->id,
        ]);

        $response->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertTrue($user->has_starter);
        $this->assertDatabaseHas('player_monsters', [
            'user_id' => $user->id,
            'species_id' => $species->id,
        ]);

        $playerMonster = PlayerMonster::where('user_id', $user->id)->first();
        $this->assertSame($stage->hp, $playerMonster->max_hp);
    }
}
