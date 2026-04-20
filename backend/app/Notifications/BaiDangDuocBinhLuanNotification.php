<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class BaiDangDuocBinhLuanNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $bai_dang_id,
        public readonly int $binh_luan_id,
        public readonly int $nguoi_binh_luan_id,
        public readonly string $nguoi_binh_luan_ten,
        public readonly ?string $noi_dung_preview,
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
            'loai' => 'bai_dang_duoc_binh_luan',
            'message' => "{$this->nguoi_binh_luan_ten} đã bình luận bài đăng của bạn.",
            'bai_dang_id' => $this->bai_dang_id,
            'binh_luan_id' => $this->binh_luan_id,
            'nguoi_binh_luan_id' => $this->nguoi_binh_luan_id,
            'nguoi_binh_luan_ten' => $this->nguoi_binh_luan_ten,
            'noi_dung_preview' => $this->noi_dung_preview,
            'tieu_de_bai' => $this->tieu_de_bai,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
