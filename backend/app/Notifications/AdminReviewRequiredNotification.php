<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class AdminReviewRequiredNotification extends Notification implements ShouldBroadcastNow
{
    use Queueable;

    public function __construct(
        private readonly string $targetType,
        private readonly int $targetId,
        private readonly string $title,
        private readonly string $message,
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
        $reviewKind = match (strtolower((string) $this->targetType)) {
            'organization' => 'organization_verification',
            'campaign' => 'campaign_pending_review',
            'withdraw_request' => 'withdraw_request_pending',
            default => 'generic',
        };

        return [
            'loai' => 'admin_review_required',
            'review_kind' => $reviewKind,
            'target_type' => $this->targetType,
            'target_id' => $this->targetId,
            'title' => $this->title,
            'message' => $this->message,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
