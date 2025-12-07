<?php

namespace App\Http\Controllers;

use App\Domain\Geo\EncounterService;
use App\Domain\Geo\LocationValidator;
use App\Models\PlayerLocation;
use App\Support\RedisRateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LocationController extends Controller
{
    private const LOCATION_LIMIT = 5;
    private const LOCATION_WINDOW_SECONDS = 10;

    public function __construct(
        private LocationValidator $validator,
        private EncounterService $encounterService,
        private RedisRateLimiter $rateLimiter
    ) {
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $this->rateLimiter->ensureWithinLimit(
            "location:update:{$user->id}",
            self::LOCATION_LIMIT,
            self::LOCATION_WINDOW_SECONDS,
            'Too many location updates; please slow down.',
        );

        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['required', 'numeric', 'min:0'],
            'speed_mps' => ['nullable', 'numeric', 'min:0'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        $recordedAt = Carbon::parse($data['recorded_at'] ?? Carbon::now());
        $previousLocation = PlayerLocation::where('user_id', $user->id)->first();

        $this->validator->validateMovement(
            $previousLocation,
            $data['lat'],
            $data['lng'],
            (float) $data['accuracy_m'],
            $data['speed_mps'] ?? null,
            $recordedAt,
        );

        $location = PlayerLocation::updateOrCreate(
            ['user_id' => $user->id],
            [
                'lat' => $data['lat'],
                'lng' => $data['lng'],
                'accuracy_m' => $data['accuracy_m'],
                'speed_mps' => $data['speed_mps'] ?? null,
                'recorded_at' => $recordedAt,
            ],
        );

        $encounters = $this->encounterService->ensureTickets($user, $data['lat'], $data['lng']);
        $this->encounterService->broadcastWildEncounters($user);

        return response()->json([
            'location' => $location,
            'encounters' => $encounters,
            'encounter' => $encounters->first(),
        ]);
    }
}
