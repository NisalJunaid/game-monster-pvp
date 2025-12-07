<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BattleUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $battleId,
        public readonly array $state,
        public readonly string $status,
        public readonly ?int $nextActorId,
        public readonly ?int $winnerUserId,
    ) {
    }

    public function broadcastAs(): string
    {
        return 'BattleUpdated';
    }

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('battles.'.$this->battleId);
    }

    public function broadcastWith(): array
    {
        return [
            'battle_id' => $this->battleId,
            'status' => $this->status,
            'next_actor_id' => $this->nextActorId,
            'winner_user_id' => $this->winnerUserId,
            'state' => $this->state,
        ];
    }
}
