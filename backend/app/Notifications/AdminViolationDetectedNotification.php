<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use App\Models\User;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use App\Models\ChienDichGayQuy;
class AdminViolationDetectedNotification extends Notification implements ShouldBroadcastNow
{
    use Queueable;

    public const SCENARIO_USER_REPORT_POST = 'user_report_post';
    public const SCENARIO_RULE = 'rule';
    public const SCENARIO_AI = 'ai';

    public function __construct(
        private readonly string $source,
        private readonly int $canhBaoId,
        private readonly int $userId,
        private readonly ?int $campaignId,
        private readonly ?int $postId,
        private readonly string $reason,
        private readonly ?string $description = null,
        private readonly string $scenario = self::SCENARIO_AI,
        private readonly ?string $userName = null,
        private readonly ?string $violationCode = null,
        private readonly ?string $mucRuiRo = null,
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
    $userName = $this->userName ?? User::find($this->userId)?->ho_ten;

    $campaign = null;

    if ($this->campaignId) {
        $campaign = ChienDichGayQuy::with('toChuc')
            ->find($this->campaignId);
    }

    return [
        'loai' => 'admin_violation_detected',

        'scenario' => $this->scenario,
        'message' => $this->source . ': ' . $this->reason,

        'source' => $this->source,
        'canh_bao_id' => $this->canhBaoId,

        'user_id' => $this->userId,
        'user_name' => $userName,

        'campaign_id' => $this->campaignId,
        'campaign_name' => $campaign?->ten_chien_dich,

        'organization_name' => $campaign?->toChuc?->ten_to_chuc,

        'post_id' => $this->postId,

        'violation_code' => $this->violationCode,
        'muc_rui_ro' => $this->mucRuiRo,

        'reason' => $this->reason,
        'mo_ta' => $this->description,
    ];
}

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
