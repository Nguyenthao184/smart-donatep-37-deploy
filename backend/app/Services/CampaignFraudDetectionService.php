<?php

namespace App\Services;

use App\Models\CanhBaoGianLan;
use App\Models\ChienDichGayQuy;
use App\Models\User;
use App\Notifications\AdminViolationDetectedNotification;
use Illuminate\Support\Facades\DB;

class CampaignFraudDetectionService
{
    public function __construct(
        private readonly CampaignFraudFeatureService $featureService,
        private readonly FraudCheckService $fraudCheckService,
        private readonly AlertAggregationService $aggregator,
    ) {}

    public function detectCampaign(int $campaignId, string $trigger = 'unknown'): void
    {
        if ($campaignId <= 0) {
            return;
        }
        $campaign = ChienDichGayQuy::find($campaignId);
    if (!$campaign || $campaign->trang_thai !== 'HOAT_DONG') {
        return;  
    }
    $tongUngHo = DB::table('ung_ho')
        ->where('chien_dich_gay_quy_id', $campaignId)
        ->where('trang_thai', 'THANH_CONG')
        ->count();

    if ($tongUngHo === 0) {
        return; 
    }
        $features = $this->featureService->buildCampaignsFeatures([$campaignId]);
        if ($features === []) {
            return;
        }

        $payload = $features;
        if (count($payload) < 2) {
            // baseline để không gãy AI endpoint
            $payload[] = [
                'campaign_id' => 0,
                'campaigns_per_user' => 1.0,
                'donation_growth' => 10.0,
                'self_donation_ratio' => 0.05,
                'unique_donors' => 12.0,
                'donation_frequency' => 1.0,
            ];
        }

        $aiRows = $this->fraudCheckService->checkCampaigns($payload);
        $risk = strtoupper((string) (collect($aiRows)->firstWhere('campaign_id', $campaignId)['risk'] ?? 'LOW'));

        $feature = $features[0];
        $ownerUserId = (int) ($feature['chu_so_huu_id'] ?? 0);
        if ($ownerUserId <= 0) {
            return;
        }

        $reasons = $this->buildCampaignReasons($feature);
        $shouldCreate = in_array($risk, ['MEDIUM', 'HIGH'], true) || $reasons !== [];
        if (!$shouldCreate) {
            return;
        }

        $score = $this->riskToScore($risk, $feature, $reasons);
        $campaign = ChienDichGayQuy::query()->find($campaignId);
        $ownerName = User::query()->where('id', $ownerUserId)->value('ho_ten');

        foreach ($reasons ?: [$this->defaultReasonForRisk($risk)] as $reason) {
            $violation = $this->mapReasonToAggregation((string) $reason, $feature, $campaignId);
            if ($violation === null) {
                continue;
            }

            $sourceText = "[AI_CAMPAIGN][$trigger] Cảnh báo chiến dịch";

            $this->aggregator->upsertAggregatedAlert(
                userId: $ownerUserId,
                targetType: 'campaign',
                targetId: $campaignId,
                source: 'AI',
                violationCode: $violation['code'],
                title: $violation['title'],
                reasonText: $violation['reason_text'],
                score: $score,
                campaignId: $campaignId,
                initialCount: $violation['count'],
                timeWindowSeconds: $violation['time_window_seconds'],
                detailItems: $violation['details'],
                notifyOnCreate: true,
                notify: function (CanhBaoGianLan $alert) use ($sourceText, $ownerName) {
                    $this->notifyAdmins($alert, $sourceText, $ownerName);
                }
            );
        }
    }

    /**
     * @param array<string, mixed> $feature
     * @return array<int, string>
     */
    private function buildCampaignReasons(array $feature): array
    {
        $reasons = [];

        if ((float) ($feature['campaigns_per_user'] ?? 0) >= 4) {
            $reasons[] = 'Nhiều chiến dịch cùng tổ chức';
        }
        if ((float) ($feature['donation_growth'] ?? 0) >= 200) {
            $reasons[] = 'Tăng ủng hộ bất thường (chiến dịch)';
        }
        if ((float) ($feature['self_donation_ratio'] ?? 0) >= 0.5) {
            $reasons[] = 'Tỷ lệ tự ủng hộ cao';
        }
        if ((float) ($feature['unique_donors'] ?? 99) <= 3) {
            $reasons[] = 'Ít người ủng hộ';
        }
        if ((float) ($feature['donation_frequency'] ?? 0) >= 8) {
            $reasons[] = 'Ủng hộ dày đặc (7 ngày gần đây)';
        }

        return $reasons;
    }

    private function riskToScore(string $risk, array $feature, array $reasons): float
    {
        $risk = strtoupper($risk);
        $base = match ($risk) {
            'HIGH' => 82.0,
            'MEDIUM' => 55.0,
            default => 35.0,
        };

        // boost nhẹ theo số lý do
        $base += min(18.0, count($reasons) * 4.0);

        return round(min(100.0, max(0.0, $base)), 2);
    }

    private function defaultReasonForRisk(string $risk): string
    {
        return match (strtoupper($risk)) {
            'HIGH' => 'Gian lận chiến dịch / ủng hộ',
            'MEDIUM' => 'Hoạt động chiến dịch đáng ngờ',
            default => 'Gian lận chiến dịch',
        };
    }

    /**
     * @param array<string, mixed> $feature
     * @return array{code:string,title:string,reason_text:string,count:int,time_window_seconds:?int,details:array<int,array<string,mixed>>}|null
     */
    private function mapReasonToAggregation(string $reason, array $feature, int $campaignId): ?array
    {
        $reason = trim($reason);
        if ($reason === '') {
            return null;
        }

        $code = match ($reason) {
            'Nhiều chiến dịch cùng tổ chức' => 'AI_NHIEU_CHIEN_DICH',
            'Tăng ủng hộ bất thường (chiến dịch)' => 'AI_TANG_UNG_HO_BAT_THUONG',
            'Tỷ lệ tự ủng hộ cao' => 'AI_TU_UNG_HO_CAO',
            'Ít người ủng hộ' => 'AI_IT_NGUOI_UNG_HO',
            'Ủng hộ dày đặc (7 ngày gần đây)' => 'AI_UNG_HO_DAY_DAC',
            default => 'AI_CAMPAIGN_SUSPICIOUS',
        };

        $details = [
            ['type' => 'campaign', 'id' => (int) $campaignId],
        ];

        $timeWindowSeconds = null;
        $count = 1;

        if ($code === 'AI_UNG_HO_DAY_DAC') {
            $timeWindowSeconds = 7 * 24 * 60 * 60;
            $count = (int) max(1, (int) round((float) ($feature['donation_frequency'] ?? 1)));
        }

        if ($code === 'AI_NHIEU_CHIEN_DICH') {
            $timeWindowSeconds = 30 * 24 * 60 * 60;
            $count = (int) max(1, (int) round((float) ($feature['campaigns_per_user'] ?? 1)));
        }

        return [
            'code' => $code,
            'title' => $reason,
            'reason_text' => $reason,
            'count' => $count,
            'time_window_seconds' => $timeWindowSeconds,
            'details' => $details,
        ];
    }

    private function notifyAdmins(CanhBaoGianLan $alert, string $sourceText, ?string $ownerName): void
    {
        $admins = User::query()
            ->whereHas('roles', fn ($q) => $q->where('ten_vai_tro', 'ADMIN'))
            ->get();

        foreach ($admins as $admin) {
            $admin->notify(new AdminViolationDetectedNotification(
                source: $sourceText,
                canhBaoId: (int) $alert->id,
                userId: (int) $alert->nguoi_dung_id,
                campaignId: $alert->chien_dich_id ? (int) $alert->chien_dich_id : null,
                postId: null,
                reason: (string) ($alert->loai_canh_bao ?? $alert->loai_gian_lan ?? 'campaign_fraud'),
                description: $alert->ly_do,
                scenario: AdminViolationDetectedNotification::SCENARIO_AI,
                userName: $ownerName,
                violationCode: (string) ($alert->violation_code ?? null),
                mucRuiRo: (string) ($alert->muc_rui_ro ?? null),
            ));
        }
    }
}

