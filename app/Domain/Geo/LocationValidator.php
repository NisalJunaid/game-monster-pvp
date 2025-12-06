<?php

namespace App\Domain\Geo;

use App\Models\PlayerLocation;
use Illuminate\Support\Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LocationValidator
{
    private const MAX_SPEED_MPS = 60.0;
    private const ACCURACY_BUFFER_METERS = 25.0;

    public function validateMovement(
        ?PlayerLocation $previousLocation,
        float $lat,
        float $lng,
        float $accuracyMeters,
        ?float $speedMetersPerSecond,
        Carbon $recordedAt
    ): void {
        if (! $previousLocation) {
            return;
        }

        $timeDelta = max(1, $recordedAt->diffInSeconds($previousLocation->recorded_at));
        $distance = $this->haversineDistanceMeters($previousLocation->lat, $previousLocation->lng, $lat, $lng);

        $accuracyPadding = max(self::ACCURACY_BUFFER_METERS, $accuracyMeters + ($previousLocation->accuracy_m ?? 0));
        $allowedDistance = ($timeDelta * self::MAX_SPEED_MPS) + $accuracyPadding;

        if ($speedMetersPerSecond !== null && $speedMetersPerSecond > self::MAX_SPEED_MPS * 1.5) {
            $this->flagTeleport($previousLocation, $lat, $lng, $distance, $speedMetersPerSecond, $timeDelta);
        }

        if ($distance > $allowedDistance) {
            $this->flagTeleport($previousLocation, $lat, $lng, $distance, $speedMetersPerSecond, $timeDelta);
        }
    }

    private function flagTeleport(
        PlayerLocation $previousLocation,
        float $lat,
        float $lng,
        float $distance,
        ?float $speedMetersPerSecond,
        int $timeDelta
    ): void {
        Log::warning('geo.teleport_detected', [
            'user_id' => $previousLocation->user_id,
            'from' => [$previousLocation->lat, $previousLocation->lng],
            'to' => [$lat, $lng],
            'distance_m' => $distance,
            'time_delta_s' => $timeDelta,
            'reported_speed_mps' => $speedMetersPerSecond,
        ]);

        throw ValidationException::withMessages([
            'location' => __('Movement speed too high; potential teleport detected.'),
        ]);
    }

    private function haversineDistanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
