<?php

namespace App\Http\Controllers\Web;

use App\Domain\Geo\EncounterService;
use App\Domain\Geo\LocationValidator;
use App\Http\Controllers\Controller;
use App\Http\Requests\LocationUpdateRequest;
use App\Models\EncounterTicket;
use App\Models\PlayerLocation;
use App\Support\RedisRateLimiter;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class EncounterController extends Controller
{
    private const LOCATION_LIMIT = 5;
    private const LOCATION_WINDOW_SECONDS = 10;

    public function __construct(
        private readonly LocationValidator $validator,
        private readonly EncounterService $encounterService,
        private readonly RedisRateLimiter $rateLimiter
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $ticket = $this->encounterService->currentTicket($user);
        $location = PlayerLocation::where('user_id', $user->id)->first();

        return view('encounters.index', [
            'ticket' => $ticket?->load(['species', 'zone']),
            'location' => $location,
        ]);
    }

    public function update(LocationUpdateRequest $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $expectsJson = $request->expectsJson();

        try {
            $this->rateLimiter->ensureWithinLimit(
                "location:update:{$user->id}",
                self::LOCATION_LIMIT,
                self::LOCATION_WINDOW_SECONDS,
                'Too many location updates; please slow down.',
            );
        } catch (HttpResponseException $exception) {
            $message = $exception->getResponse()->getData()->message ?? 'Rate limit hit.';

            if ($expectsJson) {
                return response()->json(['message' => $message], 429);
            }

            return back()->withErrors(['location' => $message]);
        }

        $data = $request->validated();
        $recordedAt = Carbon::parse($data['recorded_at'] ?? Carbon::now());
        $previousLocation = PlayerLocation::where('user_id', $user->id)->first();

        try {
            $this->validator->validateMovement(
                $previousLocation,
                $data['lat'],
                $data['lng'],
                (float) $data['accuracy_m'],
                $data['speed_mps'] ?? null,
                $recordedAt,
            );
        } catch (ValidationException $exception) {
            if ($expectsJson) {
                return response()->json(['errors' => $exception->errors()], 422);
            }

            return back()->withErrors($exception->errors())->withInput();
        }

        try {
            PlayerLocation::updateOrCreate(
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
        } catch (\Throwable $exception) {
            if ($expectsJson) {
                return response()->json([
                    'message' => $exception->getMessage() ?: 'Unable to update location right now.',
                ], 500);
            }

            return back()->withErrors([
                'location' => $exception->getMessage() ?: 'Unable to update location right now.',
            ])->withInput();
        }
        $message = $ticket ? 'Location updated. Encounter available!' : 'Location updated. No encounters nearby yet.';

        if ($expectsJson) {
            return response()->json([
                'message' => $message,
                'encounter' => $ticket?->load(['species', 'zone']),
            ]);
        }

        return redirect()->route('encounters.index')->with('status', $message);
    }

    public function resolve(Request $request, EncounterTicket $ticket): RedirectResponse
    {
        try {
            $ticket->loadMissing('species');
            $result = $this->encounterService->resolveCapture($request->user(), $ticket);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        } catch (\Throwable $exception) {
            return back()->withErrors(['encounter' => $exception->getMessage()]);
        }

        $message = $result['success'] ? 'Capture successful!' : 'Capture failed.';
        $message .= " (Roll {$result['roll']} / {$result['threshold']})";

        return redirect()->route('encounters.index')->with([
            'status' => $message,
            'capture_result' => $result,
        ]);
    }
}
