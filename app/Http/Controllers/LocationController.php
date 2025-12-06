<?php

namespace App\Http\Controllers;

use App\Domain\Geo\EncounterService;
use App\Domain\Geo\LocationValidator;
use App\Models\PlayerLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon\Carbon;

class LocationController extends Controller
{
    public function __construct(private LocationValidator $validator, private EncounterService $encounterService)
    {
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['required', 'numeric', 'min:0'],
            'speed_mps' => ['nullable', 'numeric', 'min:0'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        $user = $request->user();
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

        $ticket = $this->encounterService->issueTicket($user, $data['lat'], $data['lng']);

        return response()->json([
            'location' => $location,
            'encounter' => $ticket,
        ]);
    }
}
