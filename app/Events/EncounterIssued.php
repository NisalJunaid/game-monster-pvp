<?php

namespace App\Events;

use App\Models\EncounterTicket;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EncounterIssued implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public EncounterTicket $ticket)
    {
        $this->ticket->loadMissing(['species', 'zone']);
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('users.'.$this->ticket->user_id);
    }

    public function broadcastAs(): string
    {
        return 'EncounterIssued';
    }

    public function broadcastWith(): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'species' => [
                'id' => $this->ticket->species?->id,
                'name' => $this->ticket->species?->name,
            ],
            'level' => $this->ticket->rolled_level,
            'zone' => [
                'id' => $this->ticket->zone?->id,
                'name' => $this->ticket->zone?->name,
            ],
            'expires_at' => $this->ticket->expires_at?->toIso8601String(),
        ];
    }
}
