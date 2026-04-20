<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TinNhanDaXem implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /**
     * @param  int[]  $tin_nhan_ids
     */
    public function __construct(
        public readonly int $cuoc_tro_chuyen_id,
        public readonly int $nguoi_xem_id,
        public readonly array $tin_nhan_ids,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('cuoc-tro-chuyen.' . $this->cuoc_tro_chuyen_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'TinNhanDaXem';
    }
}

