<?php

namespace App\Http\Controllers;

use App\Domain\Pvp\LiveMatchmaker;
use App\Domain\Pvp\PvpRankingService;
use App\Models\MatchmakingQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PvpQueueController extends Controller
{
    public function __construct(
        private readonly PvpRankingService $rankingService,
        private readonly LiveMatchmaker $matchmaker,
    )
    {
    }

    public function queue(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mode' => 'required|in:ranked,casual',
        ]);

        $user = $request->user();

        if ($data['mode'] === 'ranked' && ! $this->matchmaker->hasFullTeam($user)) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Ranked requires a full team of 6 monsters.');
        }

        $this->rankingService->ensureProfile($user->id);

        $entry = MatchmakingQueue::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'mode' => $data['mode'],
                'queued_at' => now(),
            ],
        );

        return response()->json([
            'data' => [
                'user_id' => $entry->user_id,
                'mode' => $entry->mode,
                'queued_at' => $entry->queued_at,
            ],
        ], Response::HTTP_CREATED);
    }

    public function dequeue(Request $request): JsonResponse
    {
        $user = $request->user();

        MatchmakingQueue::query()->where('user_id', $user->id)->delete();

        return response()->json([], Response::HTTP_NO_CONTENT);
    }
}
