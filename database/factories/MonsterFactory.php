<?php

namespace Database\Factories;

use App\Models\Monster;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Monster>
 */
class MonsterFactory extends Factory
{
    protected $model = Monster::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'attack' => $this->faker->numberBetween(5, 20),
            'defense' => $this->faker->numberBetween(1, 10),
            'health' => $this->faker->numberBetween(20, 50),
        ];
    }
}
