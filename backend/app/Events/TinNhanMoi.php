<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TinNhanMoi implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $cuoc_tro_chuyen_id,
        public readonly int $tin_nhan_id,
        public readonly int $nguoi_gui_id,
        public readonly ?string $noi_dung,
        public readonly string $loai_tin,
        public readonly ?string $tep_dinh_kem,
        public readonly string $created_at,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('cuoc-tro-chuyen.' . $this->cuoc_tro_chuyen_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'TinNhanMoi';
    }
}

