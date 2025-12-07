<?php

namespace App\Events;

use App\Models\EncounterTicket;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class WildEncountersUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @var Collection<int, EncounterTicket> */
    public Collection $tickets;

    public function __construct(public int $userId, Collection $tickets)
    {
        $this->tickets = $tickets->loadMissing(['species', 'zone']);
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('users.'.$this->userId);
    }

    public function broadcastAs(): string
    {
        return 'WildEncountersUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'encounters' => $this->tickets->map(function (EncounterTicket $ticket) {
                return [
                    'id' => $ticket->id,
                    'species' => [
                        'id' => $ticket->species?->id,
                        'name' => $ticket->species?->name,
                    ],
                    'zone_id' => $ticket->zone_id,
                    'zone' => [
                        'id' => $ticket->zone?->id,
                        'name' => $ticket->zone?->name,
                    ],
                    'level' => $ticket->rolled_level,
                    'current_hp' => $ticket->current_hp,
                    'max_hp' => $ticket->max_hp,
                    'expires_at' => $ticket->expires_at?->toIso8601String(),
                    'status' => $ticket->status,
                ];
            })->values(),
        ];
    }
}
