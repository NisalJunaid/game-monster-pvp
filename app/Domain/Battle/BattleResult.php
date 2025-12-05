<?php

namespace App\Domain\Battle;

use App\Models\Monster;

class BattleResult
{
    public function __construct(
        public readonly Monster $attacker,
        public readonly Monster $defender,
        public readonly Monster $winner,
        public readonly int $rounds,
        public readonly array $log,
    ) {
    }
}
