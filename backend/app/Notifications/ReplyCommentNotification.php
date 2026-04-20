<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ReplyCommentNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $bai_dang_id,
        public int $binh_luan_id,
        public int $nguoi_reply_id,
        public string $nguoi_reply_ten,
        public string $noi_dung
    ) {}

    public function via($notifiable)
    {
        return ['database', 'broadcast']; // lưu DB + broadcast realtime
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'reply_comment',
            'bai_dang_id' => $this->bai_dang_id,
            'binh_luan_id' => $this->binh_luan_id,
            'nguoi_reply_id' => $this->nguoi_reply_id,
            'nguoi_reply_ten' => $this->nguoi_reply_ten,
            'noi_dung' => $this->noi_dung,
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}