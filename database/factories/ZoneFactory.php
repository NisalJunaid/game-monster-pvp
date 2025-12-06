<?php

namespace Database\Factories;

use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Zone> */
class ZoneFactory extends Factory
{
    protected $model = Zone::class;

    public function definition(): array
    {
        $centerLat = $this->faker->latitude(30, 50);
        $centerLng = $this->faker->longitude(-120, -70);
        $delta = 0.01;

        $path = [
            ['lat' => $centerLat - $delta, 'lng' => $centerLng - $delta],
            ['lat' => $centerLat - $delta, 'lng' => $centerLng + $delta],
            ['lat' => $centerLat + $delta, 'lng' => $centerLng + $delta],
            ['lat' => $centerLat + $delta, 'lng' => $centerLng - $delta],
            ['lat' => $centerLat - $delta, 'lng' => $centerLng - $delta],
        ];

        $pairs = array_map(fn ($point) => $point['lng'].' '.$point['lat'], $path);
        $polygonWkt = 'POLYGON((' . implode(',', $pairs) . '))';

        $lats = array_column($path, 'lat');
        $lngs = array_column($path, 'lng');

        return [
            'name' => $this->faker->words(2, true),
            'priority' => $this->faker->numberBetween(0, 5),
            'is_active' => true,
            'shape_type' => 'polygon',
            'geom' => $polygonWkt,
            'radius_m' => null,
            'min_lat' => min($lats),
            'max_lat' => max($lats),
            'min_lng' => min($lngs),
            'max_lng' => max($lngs),
        ];
    }
}
