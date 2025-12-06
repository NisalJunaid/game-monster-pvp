<?php

namespace App\Domain\Pvp;

class EloCalculator
{
    public function calculate(int $ratingA, int $ratingB, float $scoreA, float $scoreB, int $kFactor = 32): array
    {
        $expectedA = 1 / (1 + pow(10, ($ratingB - $ratingA) / 400));
        $expectedB = 1 / (1 + pow(10, ($ratingA - $ratingB) / 400));

        $newRatingA = (int) round($ratingA + $kFactor * ($scoreA - $expectedA));
        $newRatingB = (int) round($ratingB + $kFactor * ($scoreB - $expectedB));

        return [$newRatingA, $newRatingB];
    }
}
