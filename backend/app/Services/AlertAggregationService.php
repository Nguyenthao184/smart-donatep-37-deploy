<?php

namespace App\Services;

use App\Models\CanhBaoGianLan;
use Illuminate\Support\Facades\DB;

class AlertAggregationService
{
    private const MAX_DETAILS = 50;

    /**
     * @param array<int, array<string, mixed>> $detailItems
     */
    public function upsertAggregatedAlert(
        int $userId,
        string $targetType,
        int $targetId,
        string $source,
        string $violationCode,
        string $title,
        string $reasonText,
        float $score,
        ?int $campaignId,
        int $initialCount,
        ?int $timeWindowSeconds,
        array $detailItems = [],
        bool $notifyOnCreate = true,
        ?callable $notify = null,
    ): CanhBaoGianLan {
        $source = strtoupper(trim($source));
        $targetType = strtolower(trim($targetType));
        $violationCode = strtoupper(trim($violationCode));
        $initialCount = max(1, (int) $initialCount);

        $now = now();
        $windowStartedAt = null;
        if ($timeWindowSeconds !== null && $timeWindowSeconds > 0) {
            $sec = (int) $timeWindowSeconds;
            $windowStartedAt = $now->copy()->subSeconds($now->timestamp % $sec)->startOfSecond();
        }

        $created = false;

        /** @var CanhBaoGianLan $alert */
        $alert = DB::transaction(function () use (
            $userId,
            $campaignId,
            $targetType,
            $targetId,
            $source,
            $violationCode,
            $title,
            $reasonText,
            $score,
            $initialCount,
            $timeWindowSeconds,
            $windowStartedAt,
            $detailItems,
            $now,
            &$created
        ) {
            $query = CanhBaoGianLan::query()
                ->where('nguoi_dung_id', $userId)
                ->where('source', $source)
                ->where('target_type', $targetType)
                ->where('target_id', $targetId)
                ->where('violation_code', $violationCode)
                ->where('trang_thai', 'CHO_XU_LY');

            if ($campaignId) {
                $query->where('chien_dich_id', $campaignId);
            } else {
                $query->whereNull('chien_dich_id');
            }

            if ($windowStartedAt !== null) {
                $query->where('window_started_at', $windowStartedAt);
            } else {
                $query->whereNull('window_started_at');
            }

            $existing = $query->lockForUpdate()->first();
            if ($existing) {
                $mergedDetails = $this->mergeDetails(is_array($existing->details) ? $existing->details : [], $detailItems);
                $existing->fill([
                    'count' => max(1, ((int) $existing->count) + $initialCount),
                    'details' => $mergedDetails,
                    'last_seen_at' => $now,
                    'diem_rui_ro' => round(max((float) ($existing->diem_rui_ro ?? 0.0), $score), 2),
                    'muc_rui_ro' => $score >= 70 ? 'HIGH' : ($score >= 40 ? 'MEDIUM' : 'LOW'),
                ]);

                // Keep original title fields stable but refresh message text
                $existing->loai_canh_bao = $title;
                $existing->loai_gian_lan = $title;
                $existing->ly_do = $this->formatAggregatedReasonText($reasonText, (int) $existing->count, $timeWindowSeconds);
                $existing->mo_ta = mb_substr((string) $existing->ly_do, 0, 255);
                $existing->save();
                return $existing;
            }

            $created = true;
            $count = $initialCount;
            $lyDo = $this->formatAggregatedReasonText($reasonText, $count, $timeWindowSeconds);

            return CanhBaoGianLan::query()->create([
                'nguoi_dung_id' => $userId,
                'chien_dich_id' => $campaignId,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'source' => $source,
                'violation_code' => $violationCode,
                'count' => $count,
                'time_window_seconds' => $timeWindowSeconds,
                'window_started_at' => $windowStartedAt,
                'details' => $this->mergeDetails([], $detailItems),
                'last_seen_at' => $now,
                'loai_canh_bao' => $title,
                'muc_rui_ro' => $score >= 70 ? 'HIGH' : ($score >= 40 ? 'MEDIUM' : 'LOW'),
                'loai_gian_lan' => $title,
                'diem_rui_ro' => round($score, 2),
                'ly_do' => $lyDo,
                'mo_ta' => mb_substr($lyDo, 0, 255),
                'trang_thai' => 'CHO_XU_LY',
                'decision' => 'CHO_XU_LY',
                'admin_id' => null,
                'admin_note' => null,
                'reviewed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        if ($created && $notifyOnCreate && is_callable($notify)) {
            $notify($alert);
        }

        return $alert;
    }

    /**
     * @param array<int, mixed> $a
     * @param array<int, mixed> $b
     * @return array<int, mixed>
     */
    private function mergeDetails(array $a, array $b): array
    {
        $out = [];
        foreach (array_merge($a, $b) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $key = ($item['type'] ?? '') . ':' . ($item['id'] ?? '');
            if ($key === ':' || $key === '') {
                $out[] = $item;
                continue;
            }
            $out[$key] = $item;
        }

        $values = array_values($out);
        if (count($values) > self::MAX_DETAILS) {
            $values = array_slice($values, 0, self::MAX_DETAILS);
        }
        return $values;
    }

    private function formatAggregatedReasonText(string $base, int $count, ?int $windowSeconds): string
    {
        $base = trim($base);
        if ($count <= 1) {
            return $base;
        }
        if ($windowSeconds === null || $windowSeconds <= 0) {
            return sprintf('%s (%d lần)', $base, $count);
        }

        $mins = (int) floor($windowSeconds / 60);
        if ($mins <= 0) {
            return sprintf('%s (%d lần)', $base, $count);
        }

        return sprintf('%s (%d lần trong %d phút)', $base, $count, $mins);
    }
}

