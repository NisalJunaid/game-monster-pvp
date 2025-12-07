<?php

namespace App\Domain\Geo;

use App\Domain\Encounters\ZoneSpawnGenerator;
use App\Events\EncounterIssued;
use App\Events\WildEncountersUpdated;
use App\Models\EncounterTicket;
use App\Models\MonsterSpecies;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Models\Zone;
use App\Models\ZoneSpawnEntry;
use App\Support\RedisRateLimiter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;

class EncounterService
{
    private const TICKET_DURATION_SECONDS = 90;
    private const COOLDOWN_SECONDS = 30;
    private const ENCOUNTER_LIMIT = 3;
    private const ENCOUNTER_WINDOW_SECONDS = 60;
    public const MAX_ACTIVE_TICKETS_PER_USER = 3;

    public function __construct(
        private GeoZoneService $geoZoneService,
        private RedisRateLimiter $rateLimiter,
        private ZoneSpawnGenerator $spawnGenerator,
    )
    {
    }

    public function currentTicket(User $user): ?EncounterTicket
    {
        return $this->activeTickets($user)->first();
    }

    public function activeTickets(User $user, ?Zone $zone = null): Collection
    {
        $query = EncounterTicket::where('user_id', $user->id)
            ->where('status', EncounterTicket::STATUS_ACTIVE)
            ->latest('expires_at');

        if ($zone) {
            $query->where('zone_id', $zone->id);
        }

        $tickets = $query->get()->load(['species', 'zone']);

        $expiredIds = $tickets->filter(fn (EncounterTicket $ticket) => $ticket->isExpired())->pluck('id');

        if ($expiredIds->isNotEmpty()) {
            EncounterTicket::whereIn('id', $expiredIds)->update(['status' => EncounterTicket::STATUS_EXPIRED]);
            $tickets = $tickets->reject(fn (EncounterTicket $ticket) => $expiredIds->contains($ticket->id));
        }

        return $tickets->values();
    }

    public function issueTicket(User $user, float $lat, float $lng): ?EncounterTicket
    {
        return $this->ensureTickets($user, $lat, $lng)->first();
    }

    public function ensureTickets(User $user, float $lat, float $lng): Collection
    {
        $zone = $this->selectZone($lat, $lng);

        if (! $zone) {
            return new Collection();
        }

        return $this->ensureTicketsForZone($user, $zone, $lat, $lng);
    }

    public function ensureTicketsForZone(User $user, Zone $zone, float $lat, float $lng): Collection
    {
        $activeTickets = $this->activeTickets($user, $zone);
        $maxTickets = (int) config('encounters.max_active_tickets', self::MAX_ACTIVE_TICKETS_PER_USER);

        if ($activeTickets->count() >= $maxTickets || $this->onCooldown($user, $zone)) {
            return $this->activeTickets($user, $zone);
        }

        $this->rateLimiter->ensureWithinLimit(
            $this->encounterIssueKey($user->id),
            self::ENCOUNTER_LIMIT,
            self::ENCOUNTER_WINDOW_SECONDS,
            'Encounter issuance rate limit exceeded.',
        );

        $spawnEntries = $zone->spawnEntries()->with('species')->get();

        if (
            $spawnEntries->isEmpty()
            && ($zone->spawn_strategy !== 'manual' || ! empty($zone->spawn_rules ?? []))
        ) {
            $spawnEntries = $this->spawnGenerator->generateFromZone($zone)->load('species');
        }

        if ($spawnEntries->isEmpty()) {
            return $activeTickets;
        }

        $neededTickets = max(0, $maxTickets - $activeTickets->count());

        for ($i = 0; $i < $neededTickets; $i++) {
            $seed = random_int(1, PHP_INT_MAX);
            $selected = $this->selectSpawnEntry($spawnEntries, $seed);
            $rolledLevel = $this->rollLevel($selected, $seed);
            $maxHp = $this->calculateEncounterHp($selected->species, $rolledLevel);

            $ticket = EncounterTicket::create([
                'user_id' => $user->id,
                'zone_id' => $zone->id,
                'species_id' => $selected->species_id,
                'rolled_level' => $rolledLevel,
                'seed' => $seed,
                'status' => EncounterTicket::STATUS_ACTIVE,
                'expires_at' => Carbon::now()->addSeconds(self::TICKET_DURATION_SECONDS),
                'current_hp' => $maxHp,
                'max_hp' => $maxHp,
            ]);

            $ticket->update([
                'integrity_hash' => $this->generateIntegrityHash($ticket),
            ]);

            $activeTickets->push($ticket->load(['species', 'zone']));

            broadcast(new EncounterIssued($ticket));
        }

        $this->storeCooldown($user, $zone);

        $freshTickets = $this->activeTickets($user, $zone);

        return $freshTickets;
    }

    public function resolveCapture(User $user, EncounterTicket $ticket): array
    {
        if ($ticket->user_id !== $user->id) {
            abort(403, 'Encounter does not belong to user.');
        }

        $this->assertIntegrity($ticket);

        if ($ticket->status !== EncounterTicket::STATUS_ACTIVE) {
            return ['success' => false, 'message' => 'Encounter already resolved.'];
        }

        if ($ticket->isExpired()) {
            $ticket->update(['status' => EncounterTicket::STATUS_EXPIRED]);

            abort(410, 'Encounter expired.');
        }

        /** @var MonsterSpecies $species */
        $species = $ticket->species;
        $captureThreshold = max(1, min(255, $species->capture_rate));
        $roll = random_int(1, 255);
        $success = $roll <= $captureThreshold;

        $ticket->update([
            'status' => EncounterTicket::STATUS_RESOLVED,
        ]);

        return ['success' => $success, 'roll' => $roll, 'threshold' => $captureThreshold];
    }

    public function broadcastWildEncounters(User $user): void
    {
        $this->broadcastEncounters($user);
    }

    private function selectZone(float $lat, float $lng): ?Zone
    {
        return $this->geoZoneService->findZonesForPoint($lat, $lng)->first();
    }

    private function encounterIssueKey(int $userId): string
    {
        return "encounters:issue:{$userId}";
    }

    private function selectSpawnEntry(Collection $entries, int $seed): ZoneSpawnEntry
    {
        $totalWeight = $entries->sum('weight');
        $roll = $this->seededInt($seed, 1, 1, $totalWeight);

        $running = 0;
        foreach ($entries as $entry) {
            $running += $entry->weight;
            if ($roll <= $running) {
                return $entry;
            }
        }

        return $entries->first();
    }

    private function rollLevel(ZoneSpawnEntry $entry, int $seed): int
    {
        $min = max(1, (int) $entry->min_level);
        $max = max($min, (int) $entry->max_level);

        return $this->seededInt($seed, 2, $min, $max);
    }

    private function calculateEncounterHp(MonsterSpecies $species, int $level): int
    {
        $base = 30 + ($species->id % 10);

        return max(10, $base + ($level * 5));
    }

    private function seededInt(int $seed, int $step, int $min, int $max): int
    {
        $hash = hash('sha256', $seed.'-'.$step);
        $value = hexdec(substr($hash, 0, 8));
        $normalized = $value / 0xFFFFFFFF;

        return $min + (int) floor($normalized * (($max - $min) + 1));
    }

    private function generateIntegrityHash(EncounterTicket $ticket): string
    {
        $expiresAt = $ticket->expires_at instanceof Carbon
            ? $ticket->expires_at->getTimestamp()
            : Carbon::parse($ticket->expires_at)->getTimestamp();

        $payload = implode('|', [
            $ticket->user_id,
            $ticket->zone_id,
            $ticket->species_id,
            $ticket->rolled_level,
            $ticket->seed,
            $expiresAt,
        ]);

        return hash_hmac('sha256', $payload, config('app.key'));
    }

    private function assertIntegrity(EncounterTicket $ticket): void
    {
        $expected = $this->generateIntegrityHash($ticket);

        if ($ticket->integrity_hash === $expected) {
            return;
        }

        SecurityEvent::create([
            'user_id' => $ticket->user_id,
            'type' => 'encounter_integrity',
            'context' => [
                'ticket_id' => $ticket->id,
                'expected' => $expected,
                'provided' => $ticket->integrity_hash,
            ],
        ]);

        abort(400, 'Encounter integrity check failed.');
    }

    private function broadcastEncounters(User $user): void
    {
        $tickets = $this->activeTickets($user);

        broadcast(new WildEncountersUpdated($user->id, $tickets));
    }

    private function onCooldown(User $user, Zone $zone): bool
    {
        try {
            return Redis::get($this->cooldownKey($user->id, $zone->id)) !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    private function cooldownKey(int $userId, int $zoneId): string
    {
        return "encounters:cooldown:{$userId}:{$zoneId}";
    }

    private function storeCooldown(User $user, Zone $zone): void
    {
        try {
            Redis::setex($this->cooldownKey($user->id, $zone->id), self::COOLDOWN_SECONDS, 1);
        } catch (\Throwable) {
            // Ignore Redis failures in favor of uptime.
        }
    }
}
