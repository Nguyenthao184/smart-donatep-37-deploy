<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ApprovalNotification extends Notification implements ShouldBroadcastNow
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
        // ✅ FIX: Type 'lock' (vi phạm) không gửi broadcast
        // Chỉ gửi database notification cho user, không phát tới admin
        if ($this->type === 'lock') {
            return ['database'];
        }

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

        if (!empty($this->reason)) {
            $message .= ': ' . $this->reason;
        }

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