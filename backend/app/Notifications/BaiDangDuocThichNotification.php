<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class BaiDangDuocThichNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $bai_dang_id,
        public readonly int $nguoi_thich_id,
        public readonly string $nguoi_thich_ten,
        public readonly ?string $tieu_de_bai,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'loai' => 'bai_dang_duoc_thich',
            'message' => "{$this->nguoi_thich_ten} đã thích bài đăng của bạn.",
            'bai_dang_id' => $this->bai_dang_id,
            'nguoi_thich_id' => $this->nguoi_thich_id,
            'nguoi_thich_ten' => $this->nguoi_thich_ten,
            'tieu_de_bai' => $this->tieu_de_bai,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
