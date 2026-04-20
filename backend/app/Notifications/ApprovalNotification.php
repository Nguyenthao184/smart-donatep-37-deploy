<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ApprovalNotification extends Notification
{
    use Queueable;
    public function __construct(
        protected string $type,
        protected string $name,
        protected ?string $reason = null,
        protected ?string $targetType = null,
        protected ?int $targetId = null,
    ) {}

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable)
    {
        $message = match($this->type) {
            'approve' => "{$this->name} đã được duyệt",
            'reject' => "{$this->name} đã bị từ chối",
            'lock' => "{$this->name} đã bị khóa",
            default => 'Thông báo'
        };

        return [
            // Trường chuẩn hóa để FE phân loại và điều hướng ổn định.
            'loai' => 'approval',
            'action' => $this->type,
            'target_type' => $this->targetType,
            'target_id' => $this->targetId,
            'entity_name' => $this->name,
            'message' => $message,
            'ly_do' => $this->reason,
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
