<?php

namespace App\Http\Controllers;

use App\Domain\Geo\EncounterService;
use App\Models\EncounterTicket;
use Illuminate\Http\Request;

class EncounterController extends Controller
{
    public function __construct(private EncounterService $encounterService)
    {
    }

    public function current(Request $request)
    {
        $ticket = $this->encounterService->currentTicket($request->user());

        return response()->json([
            'encounter' => $ticket?->load(['species', 'zone']),
        ]);
    }

    public function resolveCapture(Request $request, EncounterTicket $ticket)
    {
        $ticket->loadMissing('species');
        $result = $this->encounterService->resolveCapture($request->user(), $ticket);

        return response()->json([
            'success' => $result['success'],
            'roll' => $result['roll'],
            'threshold' => $result['threshold'],
        ]);
    }
}
