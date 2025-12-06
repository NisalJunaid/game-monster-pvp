<?php

namespace App\Domain\Pvp;

use App\Models\Battle;
use App\Models\PvpProfile;
use Illuminate\Support\Facades\DB;

class PvpRankingService
{
    public function __construct(private readonly EloCalculator $calculator)
    {
    }

    public function ensureProfile(int $userId): PvpProfile
    {
        return PvpProfile::query()->firstOrCreate([
            'user_id' => $userId,
        ]);
    }

    public function handleBattleCompletion(Battle $battle): void
    {
        if ($battle->winner_user_id === null) {
            return;
        }

        $mode = data_get($battle->meta_json, 'mode', 'ranked');
        if ($mode !== 'ranked') {
            return;
        }

        $player1Profile = $this->ensureProfile($battle->player1_id);
        $player2Profile = $this->ensureProfile($battle->player2_id);

        $winnerId = $battle->winner_user_id;
        $player1Score = $winnerId === $battle->player1_id ? 1.0 : 0.0;
        $player2Score = $winnerId === $battle->player2_id ? 1.0 : 0.0;

        [$player1NewMmr, $player2NewMmr] = $this->calculator->calculate(
            $player1Profile->mmr,
            $player2Profile->mmr,
            $player1Score,
            $player2Score,
        );

        DB::transaction(function () use ($player1Profile, $player2Profile, $player1Score, $player2Score, $player1NewMmr, $player2NewMmr) {
            $player1Profile->update([
                'mmr' => $player1NewMmr,
                'wins' => $player1Profile->wins + ($player1Score > 0 ? 1 : 0),
                'losses' => $player1Profile->losses + ($player1Score === 0.0 ? 1 : 0),
            ]);

            $player2Profile->update([
                'mmr' => $player2NewMmr,
                'wins' => $player2Profile->wins + ($player2Score > 0 ? 1 : 0),
                'losses' => $player2Profile->losses + ($player2Score === 0.0 ? 1 : 0),
            ]);
        });
    }
}
