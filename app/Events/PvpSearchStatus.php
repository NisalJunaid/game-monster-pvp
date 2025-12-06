<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PvpSearchStatus implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $userId, public array $payload)
    {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('users.'.$this->userId);
    }

    public function broadcastAs(): string
    {
        return 'PvpSearchStatus';
    }

    public function broadcastWith(): array
    {
        return [
            'payload' => $this->payload,
        ];
    }
}
