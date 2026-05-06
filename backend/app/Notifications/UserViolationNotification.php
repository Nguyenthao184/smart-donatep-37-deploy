<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class UserViolationNotification extends Notification implements ShouldBroadcastNow
{
    use Queueable;

    /**
     * @param  'suspended'|'rejected'  $action
     */
    public function __construct(
        private readonly string $action,
        private readonly string $targetType,
        private readonly int $targetId,
        private readonly string $reason,
        private readonly ?string $description = null,
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
        $message = match ($this->action) {
            'suspended' => 'Bài đăng của bạn đã bị tạm dừng sau khi admin xử lý báo cáo vi phạm.',
            'rejected' => 'Báo cáo về bài đăng của bạn đã được admin xem xét: không xác định vi phạm.',
            default => 'Có cập nhật liên quan đến bài đăng của bạn.',
        };

        return [
            'loai' => 'post_report_resolution',
            'action' => $this->action,
            'target_type' => $this->targetType,
            'target_id' => $this->targetId,
            'reason' => $this->reason,
            'message' => $message,
            'mo_ta' => $this->description,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
