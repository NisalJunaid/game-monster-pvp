<?php

namespace App\Support;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;

class RedisRateLimiter
{
    private static array $fallbackCounters = [];

    public function ensureWithinLimit(string $key, int $maxAttempts, int $decaySeconds, string $message): void
    {
        if (! $this->hit($key, $maxAttempts, $decaySeconds)) {
            throw new HttpResponseException(response()->json(['message' => $message], 429));
        }
    }

    public function hit(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $count = $this->increment($key, $decaySeconds);

        return $count <= $maxAttempts;
    }

    private function increment(string $key, int $decaySeconds): int
    {
        try {
            $count = Redis::incr($key);
            if ($count === 1) {
                Redis::expire($key, $decaySeconds);
            }

            return (int) $count;
        } catch (\Throwable) {
            // Fallback to in-memory counters when Redis is unavailable.
            $now = Carbon::now()->timestamp;
            $counter = self::$fallbackCounters[$key] ?? ['count' => 0, 'expires_at' => $now + $decaySeconds];

            if ($counter['expires_at'] <= $now) {
                $counter = ['count' => 0, 'expires_at' => $now + $decaySeconds];
            }

            $counter['count']++;
            self::$fallbackCounters[$key] = $counter;

            return $counter['count'];
        }
    }
}
