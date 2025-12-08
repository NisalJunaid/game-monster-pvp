<?php

namespace Tests\Feature;

use App\Models\Battle;
use App\Models\BattleTurn;
use App\Models\PvpProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BattleTimeoutCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_turn_timeout_passes_turn_to_opponent(): void
    {
        $player = User::factory()->create(['name' => 'Alice']);
        $opponent = User::factory()->create(['name' => 'Bob']);

        $battle = Battle::query()->create([
            'seed' => '123',
            'status' => 'active',
            'player1_id' => $player->id,
            'player2_id' => $opponent->id,
            'started_at' => now()->subMinute(),
            'meta_json' => [
                'turn' => 1,
                'next_actor_id' => $player->id,
                'forced_switch_user_id' => null,
                'forced_switch_reason' => null,
                'turn_timeout_seconds' => 30,
                'turn_started_at' => Carbon::now()->subSeconds(45)->toIso8601String(),
                'turn_expires_at' => Carbon::now()->subSeconds(5)->toIso8601String(),
                'participants' => [
                    $player->id => [
                        'user_id' => $player->id,
                        'active_index' => 0,
                        'monsters' => [[
                            'id' => 1,
                            'name' => 'Alpha',
                            'max_hp' => 40,
                            'current_hp' => 40,
                            'is_fainted' => false,
                            'moves' => [],
                        ]],
                    ],
                    $opponent->id => [
                        'user_id' => $opponent->id,
                        'active_index' => 0,
                        'monsters' => [[
                            'id' => 2,
                            'name' => 'Beta',
                            'max_hp' => 40,
                            'current_hp' => 40,
                            'is_fainted' => false,
                            'moves' => [],
                        ]],
                    ],
                ],
                'log' => [],
            ],
        ]);

        Artisan::call('pvp:resolve-expired-turns');

        $battle->refresh();
        $state = $battle->meta_json;

        $this->assertEquals('active', $battle->status);
        $this->assertEquals(2, $state['turn']);
        $this->assertEquals($opponent->id, $state['next_actor_id']);
        $this->assertNull($state['forced_switch_user_id']);
        $this->assertCount(1, $state['log']);
        $this->assertEquals('Alice timed out.', $state['log'][0]['events'][0]['message']);
        $this->assertTrue(Carbon::parse($state['turn_expires_at'])->isFuture());

        $turn = BattleTurn::query()->where('battle_id', $battle->id)->first();

        $this->assertNotNull($turn);
        $this->assertEquals(1, $turn->turn_number);
        $this->assertEquals($player->id, $turn->actor_user_id);
        $this->assertEquals('timeout', $turn->action_json['type']);
    }

    public function test_forced_switch_timeout_awards_win(): void
    {
        $player = User::factory()->create(['name' => 'Alice']);
        $opponent = User::factory()->create(['name' => 'Bob']);

        $battle = Battle::query()->create([
            'seed' => '456',
            'status' => 'active',
            'player1_id' => $player->id,
            'player2_id' => $opponent->id,
            'started_at' => now()->subMinute(),
            'meta_json' => [
                'turn' => 3,
                'next_actor_id' => $opponent->id,
                'forced_switch_user_id' => $opponent->id,
                'forced_switch_reason' => 'fainted',
                'turn_timeout_seconds' => 30,
                'turn_started_at' => Carbon::now()->subSeconds(50)->toIso8601String(),
                'turn_expires_at' => Carbon::now()->subSeconds(10)->toIso8601String(),
                'participants' => [
                    $player->id => [
                        'user_id' => $player->id,
                        'active_index' => 0,
                        'monsters' => [[
                            'id' => 1,
                            'name' => 'Alpha',
                            'max_hp' => 40,
                            'current_hp' => 40,
                            'is_fainted' => false,
                            'moves' => [],
                        ]],
                    ],
                    $opponent->id => [
                        'user_id' => $opponent->id,
                        'active_index' => 0,
                        'monsters' => [[
                            'id' => 2,
                            'name' => 'Beta',
                            'max_hp' => 0,
                            'current_hp' => 0,
                            'is_fainted' => true,
                            'moves' => [],
                        ]],
                    ],
                ],
                'log' => [],
            ],
        ]);

        Artisan::call('pvp:resolve-expired-turns');

        $battle->refresh();
        $state = $battle->meta_json;

        $this->assertEquals('completed', $battle->status);
        $this->assertEquals($player->id, $battle->winner_user_id);
        $this->assertNull($state['next_actor_id']);
        $this->assertNull($state['forced_switch_user_id']);
        $this->assertNull($state['turn_started_at']);
        $this->assertNull($state['turn_expires_at']);
        $this->assertCount(1, $state['log']);
        $this->assertEquals('Bob failed to swap in time.', $state['log'][0]['events'][0]['message']);

        $turn = BattleTurn::query()->where('battle_id', $battle->id)->first();
        $this->assertEquals(3, $turn->turn_number);
        $this->assertEquals($opponent->id, $turn->actor_user_id);
        $this->assertEquals('timeout', $turn->action_json['type']);

        $winnerProfile = PvpProfile::query()->where('user_id', $player->id)->first();
        $loserProfile = PvpProfile::query()->where('user_id', $opponent->id)->first();

        $this->assertNotNull($winnerProfile);
        $this->assertNotNull($loserProfile);
        $this->assertEquals(1, $winnerProfile->wins);
        $this->assertEquals(1, $loserProfile->losses);
    }
}
