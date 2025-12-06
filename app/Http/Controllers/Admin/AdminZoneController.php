<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ZoneRequest;
use App\Models\Zone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class AdminZoneController extends Controller
{
    public function map(): View
    {
        return view('admin.zones.map', [
            'zones' => $this->mapZones(),
            'googleMapsApiKey' => config('services.google.maps_api_key'),
        ]);
    }

    public function store(ZoneRequest $request): RedirectResponse
    {
        $zone = new Zone();
        $this->fillZoneFromRequest($zone, $request->validated());
        $zone->save();

        return redirect()->route('admin.zones.map')->with('status', 'Zone created.');
    }

    public function update(ZoneRequest $request, Zone $zone): RedirectResponse
    {
        $this->fillZoneFromRequest($zone, $request->validated());
        $zone->save();

        return redirect()->route('admin.zones.map')->with('status', 'Zone updated.');
    }

    private function fillZoneFromRequest(Zone $zone, array $data): void
    {
        $shape = $this->shapeAttributes($data['shape_type'], $data['shape']);

        $zone->fill([
            'name' => $data['name'],
            'priority' => $data['priority'],
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : false,
            'shape_type' => $data['shape_type'],
            'radius_m' => $shape['radius_m'],
            'min_lat' => $shape['min_lat'],
            'max_lat' => $shape['max_lat'],
            'min_lng' => $shape['min_lng'],
            'max_lng' => $shape['max_lng'],
            'rules_json' => $data['rules_json'] ?? null,
        ]);

        $zone->setAttribute('geom', $shape['geom']);
        $zone->setAttribute('center', $shape['center']);
    }

    private function shapeAttributes(string $shapeType, array $shape): array
    {
        if ($shapeType === 'polygon') {
            return $this->polygonAttributes($shape['path'] ?? []);
        }

        return $this->circleAttributes($shape['center'] ?? [], (float) ($shape['radius_m'] ?? 0));
    }

    /**
     * @param array<int, array{lat: float, lng: float}> $path
     */
    private function polygonAttributes(array $path): array
    {
        $points = array_map(fn ($point) => ['lat' => (float) $point['lat'], 'lng' => (float) $point['lng']], $path);
        $first = $points[0] ?? null;
        $last = $points[count($points) - 1] ?? null;

        if ($first && $last && ($first['lat'] !== $last['lat'] || $first['lng'] !== $last['lng'])) {
            $points[] = $first;
        }

        $wktPairs = array_map(fn ($point) => $point['lng'].' '.$point['lat'], $points);
        $polygonWkt = 'POLYGON((' . implode(',', $wktPairs) . '))';

        $lats = array_column($points, 'lat');
        $lngs = array_column($points, 'lng');

        return [
            'geom' => $this->usesPostgis()
                ? DB::raw(sprintf("ST_SetSRID(ST_GeomFromText('%s'), 4326)", $polygonWkt))
                : $polygonWkt,
            'center' => null,
            'radius_m' => null,
            'min_lat' => min($lats),
            'max_lat' => max($lats),
            'min_lng' => min($lngs),
            'max_lng' => max($lngs),
        ];
    }

    /**
     * @param array{lat?: float, lng?: float} $center
     */
    private function circleAttributes(array $center, float $radiusMeters): array
    {
        $lat = (float) ($center['lat'] ?? 0);
        $lng = (float) ($center['lng'] ?? 0);
        $radius = max($radiusMeters, 0);

        $deltaLat = $radius / 111111;
        $deltaLng = $radius / max(cos(deg2rad($lat)) * 111111, 1e-6);

        return [
            'geom' => null,
            'center' => $this->usesPostgis()
                ? DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%f, %f), 4326)', $lng, $lat))
                : sprintf('POINT(%F %F)', $lng, $lat),
            'radius_m' => $radius,
            'min_lat' => $lat - $deltaLat,
            'max_lat' => $lat + $deltaLat,
            'min_lng' => $lng - $deltaLng,
            'max_lng' => $lng + $deltaLng,
        ];
    }

    private function usesPostgis(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    private function mapZones(): array
    {
        $query = Zone::query()->select('zones.*');

        if ($this->usesPostgis()) {
            $query->selectRaw('ST_AsText(geom) as geom_wkt')->selectRaw('ST_AsText(center) as center_wkt');
        } else {
            $query->addSelect(DB::raw('geom as geom_wkt'));
            $query->addSelect(DB::raw('center as center_wkt'));
        }

        return $query
            ->orderByDesc('priority')
            ->get()
            ->map(fn (Zone $zone) => [
                'id' => $zone->id,
                'name' => $zone->name,
                'priority' => $zone->priority,
                'is_active' => (bool) $zone->is_active,
                'shape_type' => $zone->shape_type,
                'radius_m' => $zone->radius_m,
                'bounds' => [
                    'min_lat' => $zone->min_lat,
                    'max_lat' => $zone->max_lat,
                    'min_lng' => $zone->min_lng,
                    'max_lng' => $zone->max_lng,
                ],
                'shape' => $this->formatShape(
                    $zone->shape_type,
                    $zone->geom_wkt ?? null,
                    $zone->center_wkt ?? null,
                    $zone->radius_m
                ),
            ])->toArray();
    }

    /**
     * @return array{path?: array<int, array{lat: float, lng: float}>, center?: array{lat: float, lng: float}, radius_m?: float}
     */
    private function formatShape(string $shapeType, ?string $geomWkt, ?string $centerWkt, ?float $radius): array
    {
        if ($shapeType === 'polygon' && $geomWkt) {
            return [
                'path' => $this->parsePolygonWkt($geomWkt),
            ];
        }

        if ($shapeType === 'circle' && $centerWkt && $radius !== null) {
            $center = $this->parsePointWkt($centerWkt);

            if ($center) {
                return [
                    'center' => $center,
                    'radius_m' => $radius,
                ];
            }
        }

        return [];
    }

    /**
     * @return array<int, array{lat: float, lng: float}>
     */
    private function parsePolygonWkt(string $wkt): array
    {
        if (! preg_match('/POLYGON\s*\(\((.*)\)\)/i', $wkt, $matches)) {
            return [];
        }

        $pairs = array_filter(array_map('trim', explode(',', (string) $matches[1])));

        $points = [];

        foreach ($pairs as $pair) {
            [$lng, $lat] = array_map('floatval', preg_split('/\s+/', $pair) ?: []);
            $points[] = ['lat' => $lat, 'lng' => $lng];
        }

        return $points;
    }

    private function parsePointWkt(string $wkt): ?array
    {
        if (! preg_match('/POINT\s*\(([-0-9\.\s]+)\)/i', $wkt, $matches)) {
            return null;
        }

        [$lng, $lat] = array_map('floatval', preg_split('/\s+/', trim($matches[1])) ?: []);

        return ['lat' => $lat, 'lng' => $lng];
    }
}
