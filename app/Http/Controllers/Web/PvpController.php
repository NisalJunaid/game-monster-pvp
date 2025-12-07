<?php

namespace App\Http\Controllers\Web;

use App\Domain\Pvp\LiveMatchmaker;
use App\Domain\Pvp\PvpRankingService;
use App\Http\Controllers\Controller;
use App\Models\Battle;
use App\Models\MatchmakingQueue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PvpController extends Controller
{
    public function __construct(
        private readonly PvpRankingService $rankingService,
        private readonly LiveMatchmaker $matchmaker,
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $queueEntry = MatchmakingQueue::where('user_id', $user->id)->first();
        $profile = $this->rankingService->ensureProfile($user->id);
        $latestBattle = Battle::where(function ($query) use ($user) {
            $query->where('player1_id', $user->id)->orWhere('player2_id', $user->id);
        })->latest('id')->first();
        $activeBattle = Battle::where('status', 'active')
            ->where(function ($query) use ($user) {
                $query->where('player1_id', $user->id)->orWhere('player2_id', $user->id);
            })
            ->latest('id')
            ->first();
        $queueCount = MatchmakingQueue::count();

        if ($activeBattle) {
            $activeBattle->load(['player1', 'player2', 'winner']);

            return view('pvp.battle_lobby', [
                'battle' => $activeBattle,
                'state' => $activeBattle->meta_json,
                'queueEntry' => $queueEntry,
                'latestBattle' => $latestBattle,
                'pvpProfile' => $profile,
                'searchTimeout' => LiveMatchmaker::SEARCH_TIMEOUT_SECONDS,
                'currentWindow' => $this->matchmaker->windowForEntry($queueEntry),
                'queueCount' => $queueCount,
            ]);
        }

        return view('pvp.index', [
            'queueEntry' => $queueEntry,
            'latestBattle' => $latestBattle,
            'pvpProfile' => $profile,
            'searchTimeout' => LiveMatchmaker::SEARCH_TIMEOUT_SECONDS,
            'currentWindow' => $this->matchmaker->windowForEntry($queueEntry),
            'queueCount' => $queueCount,
            'activeBattleId' => $activeBattle?->id,
        ]);
    }

    public function fragment(Request $request)
    {
        $user = $request->user();
        $queueEntry = MatchmakingQueue::where('user_id', $user->id)->first();
        $activeBattle = Battle::where('status', 'active')
            ->where(function ($query) use ($user) {
                $query->where('player1_id', $user->id)->orWhere('player2_id', $user->id);
            })
            ->latest('id')
            ->first();
        $queueCount = MatchmakingQueue::count();

        if ($activeBattle) {
            $activeBattle->load(['player1', 'player2', 'winner']);

            return view('pvp._battle_fragment', [
                'battle' => $activeBattle,
                'state' => $activeBattle->meta_json,
                'currentWindow' => $this->matchmaker->windowForEntry($queueEntry),
            ]);
        }

        return view('pvp._lobby_fragment', [
            'queueEntry' => $queueEntry,
            'searchTimeout' => LiveMatchmaker::SEARCH_TIMEOUT_SECONDS,
            'currentWindow' => $this->matchmaker->windowForEntry($queueEntry),
            'queueCount' => $queueCount,
            'activeBattleId' => $activeBattle?->id,
        ]);
    }

    public function status(Request $request)
    {
        $user = $request->user();
        $queueEntry = MatchmakingQueue::where('user_id', $user->id)->first();
        $activeBattle = Battle::where('status', 'active')
            ->where(function ($query) use ($user) {
                $query->where('player1_id', $user->id)->orWhere('player2_id', $user->id);
            })
            ->latest('id')
            ->first();

        return response()->json([
            'queued' => (bool) $queueEntry,
            'active_battle_id' => $activeBattle?->id,
            'current_mode' => $queueEntry?->mode,
        ]);
    }

    public function queue(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mode' => 'required|in:ranked,casual',
        ]);

        $user = $request->user();
        $result = $this->matchmaker->join($user, $data['mode']);
        $freshEntry = MatchmakingQueue::where('user_id', $user->id)->first();
        $window = $this->matchmaker->windowForEntry($freshEntry);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => $result['matched'] ? 'matched' : 'searching',
                'battle_id' => $result['battle']->id ?? null,
                'opponent_id' => $result['opponent_id'] ?? null,
                'search_timeout' => LiveMatchmaker::SEARCH_TIMEOUT_SECONDS,
                'ladder_window' => $window,
            ], $result['matched'] ? 201 : 200);
        }

        if ($result['matched']) {
            return back()->with('status', 'Match found! Opening battle...');
        }

        return back()->with('status', "Searching for a {$data['mode']} opponent using live matchmaking.");
    }

    public function dequeue(Request $request): RedirectResponse
    {
        $this->matchmaker->leave($request->user());

        if ($request->expectsJson()) {
            return response()->json(['status' => 'removed']);
        }

        return back()->with('status', 'Removed from matchmaking queue.');
    }

    public function battleFragment(Request $request, Battle $battle)
    {
        $this->assertParticipant($request, $battle);

        $battle->load(['player1', 'player2', 'winner']);

        return view('pvp._battle_fragment', [
            'battle' => $battle,
            'state' => $battle->meta_json,
            'currentWindow' => $this->matchmaker->windowForEntry(null),
        ]);
    }

    private function assertParticipant(Request $request, Battle $battle): void
    {
        $user = $request->user();

        if (! in_array($user->id, [$battle->player1_id, $battle->player2_id], true)) {
            abort(Response::HTTP_FORBIDDEN, 'You are not part of this battle.');
        }
    }
}
