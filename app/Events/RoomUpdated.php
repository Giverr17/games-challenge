<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoomUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $code,
        public string $reason, // 'joined', 'ready' or 'started'. For debugging only, there are no secrets here
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel("game.{$this->code}")];
    }
}