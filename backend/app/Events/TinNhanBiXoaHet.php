<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TinNhanBiXoaHet implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $cuoc_tro_chuyen_id,
        public readonly int $nguoi_dung_id,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('cuoc-tro-chuyen.' . $this->cuoc_tro_chuyen_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'TinNhanBiXoaHet';
    }
}
