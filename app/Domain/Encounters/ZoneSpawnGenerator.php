<?php

namespace App\Domain\Encounters;

use App\Models\MonsterSpecies;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ZoneSpawnGenerator
{
    private const DEFAULT_RULES = [
        'num_species' => 8,
        'level_min' => 1,
        'level_max' => 8,
        'weight_min' => 10,
        'weight_max' => 100,
    ];

    public function generate(Zone $zone, array $rules, bool $replaceExisting = true): Collection
    {
        $poolMode = $rules['pool_mode'] ?? 'any';
        $numSpecies = (int) ($rules['num_species'] ?? 8);
        $typeIds = $rules['types'] ?? [];
        $rarityTiers = $rules['rarity_tiers'] ?? [];
        $levelMin = (int) ($rules['level_min'] ?? 1);
        $levelMax = (int) ($rules['level_max'] ?? 8);
        $weightMin = (int) ($rules['weight_min'] ?? 10);
        $weightMax = (int) ($rules['weight_max'] ?? 100);

        $poolQuery = MonsterSpecies::query();

        if ($poolMode === 'type_based' && ! empty($typeIds)) {
            $poolQuery->where(function ($query) use ($typeIds) {
                $query->whereIn('primary_type_id', $typeIds)
                    ->orWhereIn('secondary_type_id', $typeIds);
            });
        } elseif ($poolMode === 'rarity_based' && ! empty($rarityTiers)) {
            $poolQuery->whereIn('rarity_tier', $rarityTiers);
        }

        $candidates = $poolQuery->inRandomOrder()->take($numSpecies)->get();

        if ($candidates->isEmpty()) {
            return collect();
        }

        $entries = collect();

        foreach ($candidates as $candidate) {
            $entries->push([
                'zone_id' => $zone->id,
                'species_id' => $candidate->id,
                'weight' => random_int($weightMin, $weightMax),
                'min_level' => $levelMin,
                'max_level' => $levelMax,
                'rarity_tier' => $candidate->rarity_tier ?? null,
                'conditions_json' => null,
            ]);
        }

        $normalized = $this->normalizeWeights($entries->pluck('weight')->all());

        $entries = $entries->map(function (array $entry, int $index) use ($normalized) {
            $entry['weight'] = $normalized[$index];

            return $entry;
        });

        return DB::transaction(function () use ($zone, $entries, $replaceExisting) {
            if ($replaceExisting) {
                $zone->spawnEntries()->delete();
            }

            $zone->spawnEntries()->createMany($entries);

            return $zone->spawnEntries()->with('species')->get();
        });
    }

    public function generateFromZone(Zone $zone, bool $replaceExisting = true): Collection
    {
        $rules = array_merge(self::DEFAULT_RULES, $zone->spawn_rules ?? []);
        $rules['pool_mode'] = match ($zone->spawn_strategy) {
            'type_weighted' => 'type_based',
            'rarity_weighted' => 'rarity_based',
            default => 'any',
        };

        if (($rules['pool_mode'] ?? 'any') === 'type_based' && empty($rules['types'])) {
            $rules['pool_mode'] = 'any';
        }

        return $this->generate($zone, $rules, $replaceExisting);
    }

    private function normalizeWeights(array $weights): array
    {
        $total = array_sum($weights);

        if ($total <= 0) {
            return $weights;
        }

        $normalized = [];
        $fractions = [];

        foreach ($weights as $index => $weight) {
            $raw = ($weight / $total) * 1000;
            $base = max(1, (int) floor($raw));
            $normalized[$index] = $base;
            $fractions[$index] = $raw - floor($raw);
        }

        $assigned = array_sum($normalized);
        $target = 1000;

        if ($assigned > $target) {
            $overage = $assigned - $target;
            asort($fractions);

            foreach (array_keys($fractions) as $index) {
                while ($overage > 0 && $normalized[$index] > 1) {
                    $normalized[$index]--;
                    $overage--;
                }
            }
        } elseif ($assigned < $target) {
            $remaining = $target - $assigned;
            arsort($fractions);

            while ($remaining > 0) {
                foreach (array_keys($fractions) as $index) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $normalized[$index]++;
                    $remaining--;
                }
            }
        }

        return $normalized;
    }
}
