<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 🔴 RULE-BASED FRAUD DETECTION (REALTIME)
 * 
 * Mục đích:
 * - Phát hiện gian lận ngay lập tức (realtime)
 * - Không phụ thuộc vào AI (độc lập)
 * - Có thể kết hợp với AI để confident score cao hơn
 * 
 * Flow:
 * action (post/donate) → RuleBasedFraudDetector → alert if match
 */
class RuleBasedFraudDetector
{
    /**
     * 🎯 Kiểm tra spam bài đăng (realtime)
     * 
     * @param int $userId
     * @param array $postData = [
     *     'title' => string,
     *     'content' => string,
     *     'id' => int (optional),
     * ]
     * @return array = [
     *     'flagged' => bool,
     *     'reasons' => string[],
     *     'severity' => 'LOW|MEDIUM|HIGH',
     *     'matched_rules' => int
     * ]
     */
    public function checkPostSpam(int $userId, array $postData): array
    {
        $reasons = [];
        $severity = 'LOW';

        // 🔴 RULE 1: Posting too fast (burst activity)
        // > 5 posts trong 10 phút → HIGH
        $recentPostsCount = DB::table('bai_dang')
            ->where('nguoi_dung_id', $userId)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->count();

        if ($recentPostsCount >= 5) {
            $reasons[] = 'posting_too_fast_5_in_10min';
            $severity = 'HIGH';
        } elseif ($recentPostsCount >= 3) {
            $reasons[] = 'posting_frequently_3_in_10min';
            $severity = 'MEDIUM';
        }

        // 🔴 RULE 2: Duplicate/similar content
        $lastPosts = DB::table('bai_dang')
            ->where('nguoi_dung_id', $userId)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['tieu_de', 'mo_ta']);

        if ($lastPosts->count() > 0) {
            $currentContent = strtolower(
                ($postData['title'] ?? '') . ' ' . ($postData['content'] ?? '')
            );
            $currentContent = preg_replace('/\s+/', ' ', trim($currentContent)) ?? '';

            foreach ($lastPosts as $post) {
                $previousContent = strtolower(
                    ($post->tieu_de ?? '') . ' ' . ($post->mo_ta ?? '')
                );
                $previousContent = preg_replace('/\s+/', ' ', trim($previousContent)) ?? '';

                similar_text($currentContent, $previousContent, $percent);

                if ($percent > 90) {
                    $reasons[] = 'duplicate_content_' . intval($percent) . '%';
                    $severity = 'HIGH';
                    break;
                } elseif ($percent > 75) {
                    $reasons[] = 'similar_content_' . intval($percent) . '%';
                    $severity = 'MEDIUM';
                    break;
                }
            }
        }

        // 🔴 RULE 3: Spam keywords (cơ bản)
        if ($this->hasSpamKeywords($postData['content'] ?? '')) {
            $reasons[] = 'contains_spam_keywords';
            if ($severity !== 'HIGH') {
                $severity = 'MEDIUM';
            }
        }

        // 🔴 RULE 4: External links abuse
        $linkCount = preg_match_all('/https?:\/\//', $postData['content'] ?? '', $matches);
        if ($linkCount > 3) {
            $reasons[] = 'too_many_links_' . $linkCount;
            if ($severity === 'LOW') {
                $severity = 'MEDIUM';
            }
        }

        return [
            'flagged' => count($reasons) > 0,
            'reasons' => $reasons,
            'severity' => $severity,
            'matched_rules' => count($reasons),
        ];
    }

    /**
     * 🎯 Kiểm tra gian lận donation (realtime)
     * 
     * @param int $userId
     * @param int $campaignId
     * @param array $donationData = ['amount' => float]
     * @return array
     */
    public function checkDonationFraud(int $userId, int $campaignId, array $donationData): array
    {
        $reasons = [];
        $severity = 'LOW';

        // 🔴 RULE 1: Campaign owner self-donating too much
        $isOwner = DB::table('chien_dich_gay_quy as cd')
            ->join('to_chuc as tc', 'tc.id', '=', 'cd.to_chuc_id')
            ->where('cd.id', $campaignId)
            ->where('tc.nguoi_dung_id', $userId)
            ->exists();

        if ($isOwner) {
            $totalDonation = DB::table('ung_ho')
                ->where('chien_dich_gay_quy_id', $campaignId)
                ->where('nguoi_dung_id', $userId)
                ->sum('so_tien');

            $totalCampaignDonation = DB::table('ung_ho')
                ->where('chien_dich_gay_quy_id', $campaignId)
                ->sum('so_tien');

            if ($totalCampaignDonation > 0) {
                $selfRatio = $totalDonation / $totalCampaignDonation;

                if ($selfRatio > 0.5) {
                    $reasons[] = 'self_donation_ratio_' . intval($selfRatio * 100) . '%';
                    $severity = 'HIGH';
                } elseif ($selfRatio > 0.3) {
                    $reasons[] = 'high_self_donation_ratio_' . intval($selfRatio * 100) . '%';
                    $severity = 'MEDIUM';
                }
            }
        }

        // 🔴 RULE 2: Donation burst (tăng đột ngột)
        $last7Days = (float) DB::table('ung_ho')
            ->where('nguoi_dung_id', $userId)
            ->where('created_at', '>=', now()->subDays(7))
            ->sum('so_tien');

        $before14To7Days = (float) DB::table('ung_ho')
            ->where('nguoi_dung_id', $userId)
            ->whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])
            ->sum('so_tien');

        if ($before14To7Days > 0 && $last7Days > 0) {
            $growth = (($last7Days - $before14To7Days) / $before14To7Days) * 100;

            if ($growth > 200) {
                $reasons[] = 'donation_growth_spike_' . intval($growth) . '%';
                $severity = $severity === 'HIGH' ? 'HIGH' : 'MEDIUM';
            }
        }

        // 🔴 RULE 3: Campaign has too few unique donors but high amount
        $uniqueDonors = DB::table('ung_ho')
            ->where('chien_dich_gay_quy_id', $campaignId)
            ->distinct('nguoi_dung_id')
            ->count('nguoi_dung_id');

        $totalAmount = (float) DB::table('ung_ho')
            ->where('chien_dich_gay_quy_id', $campaignId)
            ->sum('so_tien');

        if ($uniqueDonors < 5 && $totalAmount > 5000000) { // 5 triệu VND
            $reasons[] = 'few_donors_high_amount_' . $uniqueDonors . '_donors';
            if ($severity === 'LOW') {
                $severity = 'MEDIUM';
            }
        }

        // 🔴 RULE 4: Same IP donating multiple campaigns in short time
        $ipAddress = null;
        if (Schema::hasTable('sessions')) {
            $ipAddress = DB::table('sessions')
                ->where('user_id', $userId)
                ->orderByDesc('last_activity')
                ->value('ip_address');
        }

        if ($ipAddress) {
            $otherUsersFromSameIp = DB::table('sessions')
                ->where('ip_address', $ipAddress)
                ->where('user_id', '!=', $userId)
                ->distinct('user_id')
                ->count('user_id');

            if ($otherUsersFromSameIp > 3) {
                $reasons[] = 'same_ip_multiple_accounts_' . $otherUsersFromSameIp;
                $severity = 'HIGH';
            }
        }

        return [
            'flagged' => count($reasons) > 0,
            'reasons' => $reasons,
            'severity' => $severity,
            'matched_rules' => count($reasons),
        ];
    }

    /**
     * 🎯 Kiểm tra hành vi user bất thường
     * 
     * @param int $userId
     * @return array
     */
    public function checkAbnormalUserBehavior(int $userId): array
    {
        $reasons = [];
        $severity = 'LOW';

        // 🔴 RULE 1: Multiple accounts from same IP
        $ipAddress = null;
        if (Schema::hasTable('sessions')) {
            $ipAddress = DB::table('sessions')
                ->where('user_id', $userId)
                ->orderByDesc('last_activity')
                ->value('ip_address');
        }

        if ($ipAddress) {
            $multipleAccountsCount = DB::table('sessions')
                ->where('ip_address', $ipAddress)
                ->distinct('user_id')
                ->count('user_id');

            if ($multipleAccountsCount > 5) {
                $reasons[] = 'multiple_accounts_same_ip_' . $multipleAccountsCount;
                $severity = 'HIGH';
            } elseif ($multipleAccountsCount > 3) {
                $reasons[] = 'suspicious_multiple_accounts_' . $multipleAccountsCount;
                $severity = 'MEDIUM';
            }
        }

        // 🔴 RULE 2: Content length variance (thay đổi lớn)
        $recentPosts = DB::table('bai_dang')
            ->where('nguoi_dung_id', $userId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->pluck('mo_ta')
            ->map(fn($t) => strlen((string)$t))
            ->values()
            ->all();

        if (count($recentPosts) >= 2) {
            $mean = array_sum($recentPosts) / count($recentPosts);
            $variance = 0.0;

            foreach ($recentPosts as $length) {
                $variance += ($length - $mean) ** 2;
            }

            $variance = $variance / count($recentPosts);
            $stdDev = sqrt($variance);
            $cv = $mean > 0 ? $stdDev / $mean : 0;

            if ($cv > 0.5) {
                $reasons[] = 'content_variance_too_high_cv_' . round($cv, 2);
            }
        }

        return [
            'flagged' => count($reasons) > 0,
            'reasons' => $reasons,
            'severity' => $severity,
        ];
    }

    /**
     * 🔍 Kiểm tra spam keywords (cơ bản)
     */
    private function hasSpamKeywords(string $content): bool
    {
        $spamPatterns = [
            '/click here now/i',
            '/buy now/i',
            '/limited offer/i',
            '/act now|hurry|urgent/i',
            '/guarantee|100% free|no risk/i',
            '/viagra|casino|lottery/i',
        ];

        foreach ($spamPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 🎯 Tính severity score (0-100)
     */
    public static function calculateSeverityScore(string $severity, int $matchedRules): float
    {
        $baseScore = match ($severity) {
            'LOW' => 25,
            'MEDIUM' => 50,
            'HIGH' => 75,
            default => 10,
        };

        // Thêm điểm cho mỗi rule matched
        $ruleBonus = min($matchedRules * 5, 20);

        return min(99.0, $baseScore + $ruleBonus);
    }
}