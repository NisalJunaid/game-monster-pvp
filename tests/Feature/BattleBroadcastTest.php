<?php

namespace Tests\Feature;

use App\Events\BattleUpdated;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\MonsterInstance;
use App\Models\MonsterSpecies;
use App\Models\Move;
use App\Models\User;
use Database\Seeders\MonsterSpeciesSeeder;
use Database\Seeders\MoveSeeder;
use Database\Seeders\TypeEffectivenessSeeder;
use Database\Seeders\TypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

class BattleBroadcastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([TypeSeeder::class, TypeEffectivenessSeeder::class, MoveSeeder::class, MonsterSpeciesSeeder::class]);
    }

    public function test_battle_updated_event_uses_custom_name_when_broadcasting(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        [$player, $opponent, $tokenPlayer] = $this->buildPlayers();
        [$battleId, $meta] = $this->startBattle($player, $opponent, $tokenPlayer);

        $nextActor = $meta['next_actor_id'] === $player->id ? $player : $opponent;

        Broadcast::fake();

        $this->actingAs($nextActor)
            ->withSession(['_token' => 'test-token'])
            ->post(route('battles.act', ['battle' => $battleId]), [
                '_token' => 'test-token',
                'type' => 'move',
                'slot' => 1,
            ])
            ->assertRedirect(route('battles.show', ['battle' => $battleId]));

        Broadcast::assertBroadcasted(BattleUpdated::class, function (BattleUpdated $event) use ($battleId) {
            return $event->battleId === $battleId
                && $event->broadcastAs() === 'BattleUpdated';
        });
    }

    private function buildPlayers(string $playerType = 'Water', string $opponentType = 'Fire'): array
    {
        $player = User::factory()->create();
        $opponent = User::factory()->create();

        $playerMonster = $this->spawnMonster($player, $playerType, ['Water Jet']);
        $opponentMonster = $this->spawnMonster($opponent, $opponentType, ['Ember']);

        $tokenPlayer = $player->createToken('test')->plainTextToken;

        return [$player, $opponent, $tokenPlayer];
    }

    private function startBattle(User $player, User $opponent, string $tokenPlayer): array
    {
        [$playerMonster, $opponentMonster] = MonsterInstance::query()
            ->whereIn('user_id', [$player->id, $opponent->id])
            ->get()
            ->partition(fn ($instance) => $instance->user_id === $player->id)
            ->map->pluck('id')
            ->map->values();

        $response = $this->withToken($tokenPlayer)->postJson('/api/battles/challenge', [
            'opponent_user_id' => $opponent->id,
            'player_party' => $playerMonster->all(),
            'opponent_party' => $opponentMonster->all(),
            'seed' => 4242,
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
