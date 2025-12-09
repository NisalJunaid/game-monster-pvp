<?php

namespace App\Domain\Battle;

use Illuminate\Support\Collection;

class BattleStateRedactor
{
    /**
     * Hide opponent bench information for a given viewer while preserving active combatants.
     */
    public static function forViewer(array $state, int $viewerId): array
    {
        $participants = new Collection($state['participants'] ?? []);

        $state['participants'] = $participants
            ->map(function (array $participant, $userId) use ($viewerId) {
                if ((int) $userId === $viewerId) {
                    return $participant;
                }

                $activeIndex = $participant['active_index'] ?? 0;

                $participant['monsters'] = (new Collection($participant['monsters'] ?? []))
                    ->map(function (array $monster, int $index) use ($activeIndex) {
                        if ($index === $activeIndex) {
                            return $monster;
                        }

                        $isFainted = ($monster['current_hp'] ?? 0) <= 0;

                        return [
                            'id' => $monster['id'] ?? null,
                            'name' => $isFainted ? 'Fainted ally' : 'Unknown ally',
                            'level' => null,
                            'types' => [],
                            'type_names' => [],
                            'stats' => [],
                            'max_hp' => null,
                            'current_hp' => $isFainted ? 0 : null,
                            'status' => $monster['status'] ?? ($isFainted ? ['name' => 'fainted'] : null),
                            'moves' => [],
                            'is_placeholder' => true,
                            'is_fainted' => $isFainted,
                        ];
                    })
                    ->all();

                return $participant;
            })
            ->all();

        return $state;
    }
}
