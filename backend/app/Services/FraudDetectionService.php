<?php

namespace App\Services;

use App\Models\CanhBaoGianLan;
use App\Models\User;
use App\Notifications\AdminViolationDetectedNotification;
use Illuminate\Support\Facades\DB;
use App\Services\AlertAggregationService;

/**

 * Thay đổi chính:
 * 1. Tích hợp RuleBasedFraudDetector (realtime check trước)
 * 2. Xử lý USER source ngoài AI
 * 3. Tính toán score chuẩn hơn
 */
class FraudDetectionService
{
    public function __construct(
        private readonly FraudFeatureService $featureService,
        private readonly CampaignFraudFeatureService $campaignFeatureService,
        private readonly FraudCheckService $fraudCheckService,
        private readonly RuleBasedFraudDetector $ruleDetector
    ) {}

    public function checkPost(int $userId, int $postId, string $title, string $content): void
    {
        if ($userId <= 0 || $postId <= 0) {
            return;
        }

        $ruleResult = $this->ruleDetector->checkPostSpam($userId, [
            'id' => $postId,
            'title' => $title,
            'content' => $content,
        ]);

        if (($ruleResult['flagged'] ?? false) === true) {
            $score = RuleBasedFraudDetector::calculateSeverityScore(
                (string) ($ruleResult['severity'] ?? 'LOW'),
                (int) count($ruleResult['reasons'] ?? [])
            );

            $this->createAlert(
                userId: $userId,
                targetType: 'post',
                targetId: $postId,
                source: 'RULE',
                loaiCanhBao: (string) (($ruleResult['reasons'][0] ?? null) ?: 'spam_post'),
                reasons: (array) ($ruleResult['reasons'] ?? []),
                score: $score
            );
        }

        // AI vẫn chạy để làm lớp detect thứ 2.
        $this->checkUser($userId);
    }

    /**
     * 🎯 Check user fraud (MODIFIED)
     * 
     * Flow:
     * 1. Check rules trước (realtime)
     * 2. Nếu rule match → alert ngay
     * 3. Nếu không → chạy AI
     */
    public function checkUser(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        // STEP 1: Check rules trước (realtime)
        $ruleResult = $this->ruleDetector->checkAbnormalUserBehavior($userId);

        if ($ruleResult['flagged']) {
            $score = RuleBasedFraudDetector::calculateSeverityScore(
                $ruleResult['severity'],
                count($ruleResult['reasons'])
            );

            $this->createAlert(
                userId: $userId,
                targetType: 'user',
                targetId: $userId,
                source: 'RULE',  // ← Mark source as RULE
                loaiCanhBao: $ruleResult['reasons'][0] ?? 'abnormal_behavior',
                reasons: $ruleResult['reasons'],
                score: $score
            );
        }

        // STEP 2: Nếu rule không catch → run AI (background)
        $features = $this->featureService->buildUsersFeatures([$userId]);
        if ($features === []) {
            return;
        }

        $payload = $features;
        if (count($payload) < 2) {
            $payload[] = [
                'user_id' => 0,
                'posts_per_day' => 1.0,
                'content_similarity' => 0.2,
                'donation_growth' => 5.0,
                'same_ip_accounts' => 1.0,
                'activity_score' => 1.0,
                'burst_activity' => 1.0,
                'max_jump' => 10.0,
                'variance' => 0.05,
            ];
        }

        $aiRows = $this->fraudCheckService->check($payload);
        $risk = collect($aiRows)->firstWhere('user_id', $userId)['risk'] ?? 'LOW';
        $feature = $features[0];
        $reasons = $this->buildUserReasons($feature);

        if (strtoupper((string) $risk) !== 'HIGH' && $reasons === []) {
            return;
        }

        $this->createAlert(
            userId: $userId,
            targetType: 'user',
            targetId: $userId,
            source: 'AI',  // ← Mark source as AI
            loaiCanhBao: $reasons[0] ?? 'abnormal_behavior',
            reasons: $reasons,
            score: $this->computeScore($feature, $reasons)
        );
    }

    /**
     * 🎯 Check campaign fraud (MODIFIED)
     */
    public function checkCampaign(int $campaignId): void
    {
        if ($campaignId <= 0) {
            return;
        }

        $features = $this->campaignFeatureService->buildCampaignsFeatures([$campaignId]);
        if ($features === []) {
            return;
        }

        $payload = $features;
        if (count($payload) < 2) {
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
        $risk = collect($aiRows)->firstWhere('campaign_id', $campaignId)['risk'] ?? 'LOW';
        $feature = $features[0];
        $reasons = $this->buildCampaignReasons($feature);

        if (strtoupper((string) $risk) !== 'HIGH' && $reasons === []) {
            return;
        }

        $userId = (int) ($feature['chu_so_huu_id'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        $this->createAlert(
            userId: $userId,
            targetType: 'campaign',
            targetId: $campaignId,
            source: 'AI',
            loaiCanhBao: $reasons[0] ?? 'fraud_donation',
            reasons: $reasons,
            score: $this->computeCampaignScore($feature, $reasons),
            campaignId: $campaignId
        );
    }

    /**
     * 🎯 Tạo cảnh báo (MODIFIED - thêm source)
     * 
     * Thay đổi:
     * - source có thể là: AI, USER, RULE
     * - Debounce 24h trên (source, target_type, target_id)
     * - Thêm decision tracking fields
     */
    private function createAlert(
        int $userId,
        string $targetType,
        int $targetId,
        string $source,
        string $loaiCanhBao,
        array $reasons,
        float $score,
        ?int $campaignId = null
    ): void {
        $aggregator = app(AlertAggregationService::class);
        $sourceNorm = strtoupper(trim($source));
        $targetTypeNorm = strtolower(trim($targetType));

        $rawCode = strtolower((string) ($reasons[0] ?? $loaiCanhBao));

        if (str_contains($rawCode, 'posting_too_fast')) {
            $violationCode = 'POSTING_TOO_FAST';
        } elseif (str_contains($rawCode, 'duplicate_content')) {
            $violationCode = 'DUPLICATE_CONTENT';
        } elseif (str_contains($rawCode, 'content_variance')) {
            $violationCode = 'CONTENT_VARIANCE';
        } else {
            $violationCode = strtoupper($rawCode);
        }
        if ($violationCode === '') {
            $violationCode = strtoupper(trim($loaiCanhBao));
        }

        [$timeWindowSeconds, $count, $details] = $this->buildAggregationMeta($violationCode, $userId, $targetTypeNorm, $targetId);

        $aggregator->upsertAggregatedAlert(
            userId: $userId,
            targetType: $targetTypeNorm,
            targetId: $targetId,
            source: $sourceNorm,
            violationCode: $violationCode,
            title: $loaiCanhBao,
            reasonText: implode(' | ', $reasons),
            score: $score,
            campaignId: $campaignId,
            initialCount: $count,
            timeWindowSeconds: $timeWindowSeconds,
            detailItems: $details,
            notifyOnCreate: true,
            notify: function (CanhBaoGianLan $alert) use ($targetType, $targetId) {
                $this->notifyAdmins($alert, $targetType, $targetId);
            }
        );
    }

    /**
     * @return array{0:?int,1:int,2:array<int,array<string,mixed>>}
     */
    private function buildAggregationMeta(string $violationCode, int $userId, string $targetType, int $targetId): array
    {
        $code = strtolower($violationCode);
        $timeWindowSeconds = null;
        $count = 1;
        $details = [];

        if (str_contains($code, 'posting_too_fast')) {
            $timeWindowSeconds = null;

            $count = (int) DB::table('bai_dang')
                ->where('nguoi_dung_id', $userId)
                ->where('created_at', '>=', now()->subMinutes(10))
                ->count();
            $ids = DB::table('bai_dang')
                ->where('nguoi_dung_id', $userId)
                ->where('created_at', '>=', now()->subMinutes(10))
                ->orderByDesc('id')
                ->limit(20)
                ->pluck('id')
                ->all();
            foreach ($ids as $id) {
                $details[] = ['type' => 'post', 'id' => (int) $id];
            }
        }

        if (str_starts_with($code, 'duplicate_content') || str_starts_with($code, 'similar_content')) {
            $timeWindowSeconds = 10 * 60;
            $ids = DB::table('bai_dang')
                ->where('nguoi_dung_id', $userId)
                ->orderByDesc('created_at')
                ->limit(8)
                ->pluck('id')
                ->all();
            foreach ($ids as $id) {
                $details[] = ['type' => 'post', 'id' => (int) $id];
            }
            $count = max(2, count($details));
        }

        if ($targetType === 'campaign') {
            $details[] = ['type' => 'campaign', 'id' => (int) $targetId];
        }

        if ($count < 1) {
            $count = 1;
        }

        return [$timeWindowSeconds, $count, $details];
    }

    private function notifyAdmins(CanhBaoGianLan $alert, string $targetType, int $targetId): void
    {
        $admins = User::query()->whereHas('roles', fn($q) => $q->where('ten_vai_tro', 'ADMIN'))->get();
        $targetText = match ($targetType) {
            'post' => 'spam bài đăng',
            'campaign' => 'gian lận chiến dịch',
            default => 'hành vi bất thường'
        };

        $sourceText = match (strtoupper((string) ($alert->source ?? ''))) {
            'RULE' => 'RULE',
            'USER_REPORT', 'USER' => 'USER_REPORT',
            'AI' => 'AI',
            default => strtoupper((string) $alert->source),
        };

        $scenario = strtoupper((string) ($alert->source ?? '')) === 'RULE'
            ? AdminViolationDetectedNotification::SCENARIO_RULE
            : AdminViolationDetectedNotification::SCENARIO_AI;

        foreach ($admins as $admin) {
            $admin->notify(new AdminViolationDetectedNotification(
                source: "[{$sourceText}] Phát hiện nghi vấn: {$targetText}",
                canhBaoId: (int) $alert->id,
                userId: (int) $alert->nguoi_dung_id,
                campaignId: $targetType === 'campaign' ? $targetId : null,
                postId: $targetType === 'post' ? $targetId : null,
                reason: (string) ($alert->loai_canh_bao ?? $alert->loai_gian_lan ?? 'fraud_alert'),
                description: $alert->ly_do,
                scenario: $scenario
            ));
        }
    }

    private function buildUserReasons(array $feature): array
    {
        $reasons = [];
        if ((float) ($feature['posts_per_day'] ?? 0) > 5) {
            $reasons[] = 'spam_post';
        }
        if ((float) ($feature['content_similarity'] ?? 0) > 0.9) {
            $reasons[] = 'duplicate_content';
        }
        if ((int) ($feature['same_ip_accounts'] ?? 0) > 5) {
            $reasons[] = 'multi_account_same_ip';
        }
        if ((float) ($feature['burst_activity'] ?? 0) > 6) {
            $reasons[] = 'burst_activity';
        }
        if ((float) ($feature['max_jump'] ?? 0) > 2500000) {
            $reasons[] = 'max_jump_abnormal';
        }
        if ((float) ($feature['variance'] ?? 0) > 0.15) {
            $reasons[] = 'content_variance_abnormal';
        }

        return $reasons;
    }

    private function buildCampaignReasons(array $feature): array
    {
        $reasons = [];
        if ((float) ($feature['self_donation_ratio'] ?? 0) > 0.5) {
            $reasons[] = 'fraud_donation';
        }
        if ((float) ($feature['donation_growth'] ?? 0) > 200) {
            $reasons[] = 'donation_growth_spike';
        }
        if ((float) ($feature['donation_frequency'] ?? 0) > 8) {
            $reasons[] = 'donation_burst';
        }

        return $reasons;
    }

    private function computeScore(array $feature, array $reasons): float
    {
        $score = 0.0;
        $score += min(1.0, ((float) ($feature['posts_per_day'] ?? 0)) / 10.0) * 20;
        $score += min(1.0, (float) ($feature['content_similarity'] ?? 0)) * 18;
        $score += min(1.0, ((float) ($feature['donation_growth'] ?? 0)) / 300.0) * 12;
        $score += min(1.0, ((float) ($feature['same_ip_accounts'] ?? 1) - 1) / 8.0) * 15;
        $score += min(1.0, ((float) ($feature['burst_activity'] ?? 0)) / 10.0) * 12;
        $score += min(1.0, ((float) ($feature['max_jump'] ?? 0)) / 3000000.0) * 12;
        $score += min(1.0, ((float) ($feature['variance'] ?? 0)) / 0.2) * 11;
        $score = max($score, 45 + count($reasons) * 8);
        return min(99.0, round($score, 2));
    }

    private function computeCampaignScore(array $feature, array $reasons): float
    {
        $score = 0.0;
        $score += min(1.0, ((float) ($feature['campaigns_per_user'] ?? 0)) / 8.0) * 18;
        $score += min(1.0, ((float) ($feature['donation_growth'] ?? 0)) / 300.0) * 24;
        $score += min(1.0, (float) ($feature['self_donation_ratio'] ?? 0)) * 24;
        $score += min(1.0, ((float) ($feature['donation_frequency'] ?? 0)) / 20.0) * 16;
        $score += min(1.0, max(0.0, (10 - (float) ($feature['unique_donors'] ?? 0)) / 10.0)) * 18;
        $score = max($score, 50 + count($reasons) * 8);
        return min(99.0, round($score, 2));
    }
}
