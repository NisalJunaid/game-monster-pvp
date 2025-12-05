<?php

namespace App\Domain\Battle;

use App\Models\Monster;

class BattleSimulator
{
    public function simulate(Monster $first, Monster $second): BattleResult
    {
        $attacker = clone $first;
        $defender = clone $second;
        $log = [];
        $rounds = 0;

        while ($attacker->health > 0 && $defender->health > 0) {
            $rounds++;

            $damageToDefender = $this->calculateDamage($attacker, $defender);
            $defender->health = max(0, $defender->health - $damageToDefender);
            $log[] = $this->formatRound($rounds, $attacker, $defender, $damageToDefender);

            if ($defender->health <= 0) {
                break;
            }

            $damageToAttacker = $this->calculateDamage($defender, $attacker);
            $attacker->health = max(0, $attacker->health - $damageToAttacker);
            $log[] = $this->formatRound($rounds, $defender, $attacker, $damageToAttacker);
        }

        $winner = $defender->health <= 0 ? $attacker : $defender;

        return new BattleResult($first, $second, $winner, $rounds, $log);
    }

    private function calculateDamage(Monster $attacker, Monster $defender): int
    {
        return max(1, $attacker->attack - (int) floor($defender->defense / 2));
    }

    private function formatRound(int $round, Monster $actor, Monster $target, int $damage): array
    {
        return [
            'round' => $round,
            'attacker' => $actor->only(['id', 'name', 'attack', 'defense']),
            'defender' => $target->only(['id', 'name', 'attack', 'defense']),
            'damage' => $damage,
            'defender_remaining_health' => $target->health,
        ];
    }
}
