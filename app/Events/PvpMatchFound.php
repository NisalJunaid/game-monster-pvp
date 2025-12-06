<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PvpMatchFound implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $battleId,
        public int $playerAId,
        public int $playerBId,
        public string $mode,
        public string $playerAName,
        public string $playerBName,
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('users.'.$this->playerAId),
            new PrivateChannel('users.'.$this->playerBId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'PvpMatchFound';
    }

    public function broadcastWith(): array
    {
        return [
            'battle_id' => $this->battleId,
            'player_a' => ['id' => $this->playerAId, 'name' => $this->playerAName],
            'player_b' => ['id' => $this->playerBId, 'name' => $this->playerBName],
            'mode' => $this->mode,
        ];
    }
}
