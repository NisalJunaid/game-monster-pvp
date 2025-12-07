<?php

namespace App\Domain\Encounters;

use App\Events\WildBattleUpdated;
use App\Models\EncounterTicket;
use App\Models\MonsterSpeciesStage;
use App\Models\PlayerMonster;
use App\Models\TypeEffectiveness;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WildBattleService
{
    private array $typeChart;

    public function __construct()
    {
        $this->typeChart = TypeEffectiveness::query()
            ->get()
            ->groupBy('attack_type_id')
            ->map(fn (Collection $group) => $group->keyBy('defend_type_id')->map->multiplier)
            ->toArray();
    }

    public function start(User $user, EncounterTicket $ticket): EncounterTicket
    {
        $ticket->loadMissing('species');
        $ticket->loadMissing('species');
        $this->assertTicketOwner($user, $ticket);

        if ($ticket->isExpired()) {
            $ticket->update(['status' => EncounterTicket::STATUS_EXPIRED]);

            abort(410, 'Encounter expired.');
        }

        if (! $ticket->battle_state || ($ticket->battle_state['active'] ?? false) === false) {
            $ticket->battle_state = $this->buildInitialState($user, $ticket);
            $ticket->save();
        }

        return $ticket->fresh(['species']);
    }

    public function actMove(User $user, EncounterTicket $ticket, string $style = 'monster'): EncounterTicket
    {
        $stateful = $this->ensureActiveBattle($user, $ticket);

        $style = in_array($style, ['monster', 'martial'], true) ? $style : 'monster';

        $log = [];
        $stateful['battle_state'] = $this->applyPlayerAttack($stateful['battle_state'], $ticket, $style, $log);

        $ticket = $this->persistBattle($ticket, $stateful['battle_state']);

        if ($stateful['battle_state']['active']) {
            $stateful['battle_state'] = $this->applyWildTurn($stateful['battle_state'], $ticket, $log);
            $ticket = $this->persistBattle($ticket, $stateful['battle_state']);
        }

        $this->broadcast($user, $ticket, $log);

        return $ticket;
    }

    public function actSwitch(User $user, EncounterTicket $ticket, int $playerMonsterId): EncounterTicket
    {
        $stateful = $this->ensureActiveBattle($user, $ticket);
        $log = [];

        $this->switchActiveMonster($stateful['battle_state'], $playerMonsterId, $log);
        $ticket = $this->persistBattle($ticket, $stateful['battle_state']);

        if ($stateful['battle_state']['active']) {
            $stateful['battle_state'] = $this->applyWildTurn($stateful['battle_state'], $ticket, $log);
            $ticket = $this->persistBattle($ticket, $stateful['battle_state']);
        }

        $this->broadcast($user, $ticket, $log);

        return $ticket;
    }

    public function attemptTame(User $user, EncounterTicket $ticket): array
    {
        $stateful = $this->ensureActiveBattle($user, $ticket);

        $hpRatio = max(0, min(1, $ticket->current_hp / max(1, $ticket->max_hp)));
        $baseRate = max(0.1, ($ticket->species?->capture_rate ?? 100) / 255);
        $chance = max(0.05, min(0.95, $baseRate * (1 - (0.5 * $hpRatio)) + (1 - $hpRatio) * 0.25));

        $roll = random_int(1, 10000) / 10000;
        $success = $roll <= $chance;

        $log = [];

        if ($success) {
            $stateful['battle_state']['active'] = false;
            $stateful['battle_state']['resolved'] = true;
            $stateful['battle_state']['last_action_log'] = [
                ...$stateful['battle_state']['last_action_log'],
                [
                    'actor' => 'player',
                    'type' => 'tame',
                    'success' => true,
                    'chance' => $chance,
                    'roll' => $roll,
                ],
            ];
            $log[] = end($stateful['battle_state']['last_action_log']);

            DB::transaction(function () use ($user, $ticket) {
                $stage = MonsterSpeciesStage::where('species_id', $ticket->species_id)
                    ->orderBy('stage_number')
                    ->first();

                PlayerMonster::create([
                    'user_id' => $user->id,
                    'species_id' => $ticket->species_id,
                    'level' => $ticket->rolled_level,
                    'exp' => 0,
                    'current_hp' => $ticket->max_hp ?? ($stage?->hp ?? 30),
                    'max_hp' => $ticket->max_hp ?? ($stage?->hp ?? 30),
                    'nickname' => null,
                    'is_in_team' => true,
                    'team_slot' => (PlayerMonster::where('user_id', $user->id)->max('team_slot') ?? 0) + 1,
                ]);

                $ticket->update(['status' => EncounterTicket::STATUS_RESOLVED]);
            });
        } else {
            $stateful['battle_state']['last_action_log'] = [
                ...$stateful['battle_state']['last_action_log'],
                [
                    'actor' => 'player',
                    'type' => 'tame',
                    'success' => false,
                    'chance' => $chance,
                    'roll' => $roll,
                ],
            ];
            $log[] = end($stateful['battle_state']['last_action_log']);

            $stateful['battle_state'] = $this->applyWildTurn($stateful['battle_state'], $ticket, $log);
            $ticket = $this->persistBattle($ticket, $stateful['battle_state']);
        }

        $this->persistBattle($ticket, $stateful['battle_state']);
        $this->broadcast($user, $ticket, $log);

        return [
            'ticket' => $ticket->fresh(),
            'chance' => $chance,
            'roll' => $roll,
            'success' => $success,
        ];
    }

    public function run(User $user, EncounterTicket $ticket): EncounterTicket
    {
        $stateful = $this->ensureActiveBattle($user, $ticket);

        $stateful['battle_state']['active'] = false;
        $stateful['battle_state']['resolved'] = true;
        $stateful['battle_state']['last_action_log'][] = [
            'actor' => 'player',
            'type' => 'run',
        ];

        $ticket->battle_state = $stateful['battle_state'];
        $ticket->status = EncounterTicket::STATUS_RESOLVED;
        $ticket->save();

        $this->broadcast($user, $ticket, $stateful['battle_state']['last_action_log']);

        return $ticket;
    }

    private function ensureActiveBattle(User $user, EncounterTicket $ticket): array
    {
        $this->assertTicketOwner($user, $ticket);

        if ($ticket->isExpired()) {
            $ticket->update(['status' => EncounterTicket::STATUS_EXPIRED]);

            abort(410, 'Encounter expired.');
        }

        if ($ticket->status !== EncounterTicket::STATUS_ACTIVE) {
            abort(400, 'Battle is not active.');
        }

        if (! $ticket->battle_state || ($ticket->battle_state['active'] ?? false) === false) {
            $ticket->battle_state = $this->buildInitialState($user, $ticket);
            $ticket->save();
        }

        return ['battle_state' => $ticket->battle_state];
    }

    private function buildInitialState(User $user, EncounterTicket $ticket): array
    {
        $party = $this->buildPlayerParty($user);
        $activeId = $party[0]['id'] ?? null;

        return [
            'active' => true,
            'wild_ai' => true,
            'turn' => 1,
            'player_active_monster_id' => $activeId,
            'player_monsters' => $party,
            'wild' => $this->buildWild($ticket),
            'last_action_log' => [],
            'resolved' => false,
        ];
    }

    private function buildPlayerParty(User $user): array
    {
        $team = PlayerMonster::query()
            ->where('user_id', $user->id)
            ->orderByDesc('is_in_team')
            ->orderBy('team_slot')
            ->get();

        if ($team->isEmpty()) {
            return [[
                'id' => 0,
                'name' => 'Martial Artist',
                'level' => 5,
                'types' => [],
                'current_hp' => 50,
                'max_hp' => 50,
                'attack' => 28,
                'defense' => 24,
                'sp_attack' => 20,
                'sp_defense' => 20,
            ]];
        }

        return $team->map(function (PlayerMonster $monster) {
            $stage = MonsterSpeciesStage::where('species_id', $monster->species_id)
                ->orderBy('stage_number')
                ->first();

            return [
                'id' => $monster->id,
                'species_id' => $monster->species_id,
                'name' => $monster->nickname ?: $monster->species?->name,
                'level' => $monster->level,
                'types' => array_values(array_filter([
                    $monster->species?->primary_type_id,
                    $monster->species?->secondary_type_id,
                ])),
                'current_hp' => $monster->current_hp,
                'max_hp' => $monster->max_hp,
                'attack' => $stage?->attack ?? 25,
                'defense' => $stage?->defense ?? 20,
                'sp_attack' => $stage?->sp_attack ?? 20,
                'sp_defense' => $stage?->sp_defense ?? 20,
            ];
        })->values()->all();
    }

    private function buildWild(EncounterTicket $ticket): array
    {
        $stage = MonsterSpeciesStage::where('species_id', $ticket->species_id)
            ->orderBy('stage_number')
            ->first();

        return [
            'species_id' => $ticket->species_id,
            'name' => $ticket->species?->name,
            'level' => $ticket->rolled_level,
            'types' => array_values(array_filter([
                $ticket->species?->primary_type_id,
                $ticket->species?->secondary_type_id,
            ])),
            'current_hp' => $ticket->current_hp ?? $ticket->max_hp ?? ($stage?->hp ?? 40),
            'max_hp' => $ticket->max_hp ?? ($stage?->hp ?? 40),
            'attack' => $stage?->attack ?? 20,
            'defense' => $stage?->defense ?? 18,
            'sp_attack' => $stage?->sp_attack ?? 18,
            'sp_defense' => $stage?->sp_defense ?? 18,
        ];
    }

    private function applyPlayerAttack(array $state, EncounterTicket $ticket, string $style, array &$log): array
    {
        $playerMonster = $this->activeMonster($state);
        $wild = & $state['wild'];

        [$damage, $multiplier] = $this->calculateDamage($playerMonster, $wild, $style);
        $wild['current_hp'] = max(0, $wild['current_hp'] - $damage);
        $ticket->current_hp = $wild['current_hp'];
        $ticket->save();

        $state['last_action_log'][] = [
            'actor' => 'player',
            'type' => 'move',
            'style' => $style,
            'damage' => $damage,
            'multiplier' => $multiplier,
        ];

        $log[] = end($state['last_action_log']);

        if ($wild['current_hp'] <= 0) {
            $state['active'] = false;
            $state['resolved'] = true;
            $ticket->status = EncounterTicket::STATUS_RESOLVED;
            $ticket->save();
        }

        $state['turn']++;

        return $state;
    }

    private function applyWildTurn(array $state, EncounterTicket $ticket, array &$log): array
    {
        if (! $state['active']) {
            return $state;
        }

        $playerMonster = & $state['player_monsters'][$this->activeMonsterIndex($state)];
        $wild = $state['wild'];

        [$damage, $multiplier] = $this->calculateDamage($wild, $playerMonster, 'monster');
        $playerMonster['current_hp'] = max(0, $playerMonster['current_hp'] - $damage);
        $state['player_monsters'][$this->activeMonsterIndex($state)] = $playerMonster;

        $state['last_action_log'][] = [
            'actor' => 'wild',
            'type' => 'move',
            'style' => 'monster',
            'damage' => $damage,
            'multiplier' => $multiplier,
        ];

        $log[] = end($state['last_action_log']);

        if ($playerMonster['current_hp'] <= 0) {
            $next = $this->findNextHealthyMonster($state['player_monsters']);
            $state['player_active_monster_id'] = $next?['id'];

            if (! $next) {
                $state['active'] = false;
                $state['resolved'] = true;
                $ticket->status = EncounterTicket::STATUS_RESOLVED;
                $ticket->save();
            }
        }

        $state['turn']++;

        return $state;
    }

    private function switchActiveMonster(array &$state, int $monsterId, array &$log): void
    {
        foreach ($state['player_monsters'] as $monster) {
            if ($monster['id'] === $monsterId) {
                if ($monster['current_hp'] <= 0) {
                    abort(400, 'Cannot switch to a fainted monster.');
                }

                $state['player_active_monster_id'] = $monsterId;
                $state['last_action_log'][] = [
                    'actor' => 'player',
                    'type' => 'switch',
                    'target_id' => $monsterId,
                ];
                $log[] = end($state['last_action_log']);
                $state['turn']++;

                return;
            }
        }

        abort(404, 'Monster not found on your team.');
    }

    private function activeMonster(array $state): array
    {
        foreach ($state['player_monsters'] as $monster) {
            if ($monster['id'] === $state['player_active_monster_id']) {
                return $monster;
            }
        }

        return $state['player_monsters'][0];
    }

    private function activeMonsterIndex(array $state): int
    {
        foreach ($state['player_monsters'] as $index => $monster) {
            if ($monster['id'] === $state['player_active_monster_id']) {
                return $index;
            }
        }

        return 0;
    }

    private function findNextHealthyMonster(array $monsters): ?array
    {
        foreach ($monsters as $monster) {
            if ($monster['current_hp'] > 0) {
                return $monster;
            }
        }

        return null;
    }

    private function calculateDamage(array $attacker, array $defender, string $style): array
    {
        $power = $style === 'martial' ? 18 : 32;
        $attackStat = $style === 'martial' ? ($attacker['attack'] ?? 20) : max($attacker['sp_attack'] ?? 20, $attacker['attack'] ?? 20);
        $defenseStat = $style === 'martial' ? ($defender['defense'] ?? 18) : max($defender['sp_defense'] ?? 18, $defender['defense'] ?? 18);
        $levelFactor = 1 + (($attacker['level'] ?? 1) / 10);

        $typeMultiplier = 1.0;
        if ($style === 'monster') {
            $attackType = Arr::first($attacker['types'] ?? []);
            $typeMultiplier = $attackType ? $this->typeMultiplier($attackType, $defender['types'] ?? []) : 1.0;
        }

        $base = ($attackStat / max(5, $defenseStat)) * $power * $levelFactor;
        $random = 0.9 + (random_int(0, 10) / 100);

        $damage = (int) floor($base * $typeMultiplier * $random / 12);
        $damage = max(1, $damage);
        $damage = min($damage, (int) max(1, floor(($defender['max_hp'] ?? 1) * 0.45)));

        return [$damage, $typeMultiplier];
    }

    private function typeMultiplier(int $attackTypeId, array $defenderTypes): float
    {
        return array_reduce($defenderTypes, function (float $carry, int $defendTypeId) use ($attackTypeId) {
            $multiplier = Arr::get($this->typeChart, $attackTypeId.'.'.$defendTypeId, 1.0);

            return $carry * $multiplier;
        }, 1.0);
    }

    private function persistBattle(EncounterTicket $ticket, array $state): EncounterTicket
    {
        $ticket->battle_state = $state;
        $ticket->current_hp = $state['wild']['current_hp'];
        $ticket->max_hp = $state['wild']['max_hp'];
        $ticket->save();

        return $ticket->fresh();
    }

    private function assertTicketOwner(User $user, EncounterTicket $ticket): void
    {
        if ($ticket->user_id !== $user->id) {
            abort(403, 'Encounter does not belong to user.');
        }
    }

    private function broadcast(User $user, EncounterTicket $ticket, array $log): void
    {
        broadcast(new WildBattleUpdated($user->id, [
            'ticket_id' => $ticket->id,
            'battle' => $ticket->battle_state,
            'wild' => [
                'hp' => $ticket->current_hp,
                'max_hp' => $ticket->max_hp,
            ],
            'log' => $log,
            'status' => $ticket->status,
            'next_turn' => $ticket->battle_state['turn'] ?? null,
            'resolved' => ! ($ticket->battle_state['active'] ?? true),
        ]));
    }
}

