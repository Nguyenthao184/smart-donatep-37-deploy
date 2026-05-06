<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FraudFeatureService
{
    /**
     * Build 5-feature payload for AI fraud-check.
     *
     * - donation_growth: 0 khi chưa có bảng `ung_ho` (dev khác sẽ bổ sung).
     *
     * @param array<int> $userIds
     * @return array<int, array<string, float|int>>
     */
    public function buildUsersFeatures(array $userIds): array
    {
        $danhSachNguoiDung = User::query()->whereIn('id', $userIds)->get(['id']);
        $ketQua = [];

        foreach ($danhSachNguoiDung as $nguoiDung) {
            $soBaiMoiNgay = $this->calcPostsPerDay((int)$nguoiDung->id);
            $doTrungNoiDung = $this->calcContentSimilarity((int)$nguoiDung->id);
            $tangTruongUngHo = $this->calcDonationGrowth((int)$nguoiDung->id);
            $soTaiKhoanCungIp = $this->calcSameIpAccounts((int)$nguoiDung->id);
            $diemHoatDong = $this->calcActivityScore(
                $soBaiMoiNgay,
                $doTrungNoiDung,
                $tangTruongUngHo,
                $soTaiKhoanCungIp
            );
            $burstActivity = $this->calcBurstActivity((int)$nguoiDung->id);
            $maxJump = $this->calcMaxJump((int)$nguoiDung->id);
            $variance = $this->calcVariance((int)$nguoiDung->id);

            $ketQua[] = [
                'user_id' => (int)$nguoiDung->id,
                'posts_per_day' => round($soBaiMoiNgay, 4),
                'content_similarity' => round($doTrungNoiDung, 4),
                'donation_growth' => round($tangTruongUngHo, 4),
                'same_ip_accounts' => (int)$soTaiKhoanCungIp,
                'activity_score' => round($diemHoatDong, 4),
                'burst_activity' => round($burstActivity, 4),
                'max_jump' => round($maxJump, 4),
                'variance' => round($variance, 4),
            ];
        }

        return $ketQua;
    }

    private function calcPostsPerDay(int $userId): float
    {
        $soLuong = DB::table('bai_dang')
            ->where('nguoi_dung_id', $userId)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return (float)$soLuong;
    }

    private function calcContentSimilarity(int $userId): float
    {
        $danhSachBai = DB::table('bai_dang')
            ->where('nguoi_dung_id', $userId)
            ->orderByDesc('created_at')
            ->limit(8)
            ->get(['tieu_de', 'mo_ta']);

        if ($danhSachBai->count() < 2) {
            return 0.0;
        }

        $danhSachVanBan = $danhSachBai->map(function ($baiDang) {
            $chuoiNoiDung = trim(strtolower(($baiDang->tieu_de ?? '') . ' ' . ($baiDang->mo_ta ?? '')));
            return preg_replace('/\s+/', ' ', $chuoiNoiDung) ?? '';
        })->filter()->values()->all();

        if (count($danhSachVanBan) < 2) {
            return 0.0;
        }

        $tongDiem = 0.0;
        $soCap = 0;
        $tongVanBan = count($danhSachVanBan);
        for ($i = 0; $i < $tongVanBan; $i++) {
            for ($j = $i + 1; $j < $tongVanBan; $j++) {
                similar_text($danhSachVanBan[$i], $danhSachVanBan[$j], $phanTramTrung);
                $tongDiem += ($phanTramTrung / 100.0);
                $soCap++;
            }
        }

        return $soCap > 0 ? ($tongDiem / $soCap) : 0.0;
    }

    private function calcDonationGrowth(int $userId): float
    {
        if (!Schema::hasTable('ung_ho')) {
            return 0.0;
        }

        $mocGanDay = now()->subDays(7);
        $mocTruocDoBatDau = now()->subDays(14);
        $mocTruocDoKetThuc = now()->subDays(7);

        $tongGanDay = (float) DB::table('ung_ho')
            ->where('nguoi_dung_id', $userId)
            ->where('created_at', '>=', $mocGanDay)
            ->sum('so_tien');

        $tongTruocDo = (float) DB::table('ung_ho')
            ->where('nguoi_dung_id', $userId)
            ->whereBetween('created_at', [$mocTruocDoBatDau, $mocTruocDoKetThuc])
            ->sum('so_tien');

        if ($tongTruocDo <= 0 && $tongGanDay > 0) {
            return 300.0;
        }

        if ($tongTruocDo <= 0) {
            return 0.0;
        }

        $phanTramTangTruong = (($tongGanDay - $tongTruocDo) / $tongTruocDo) * 100.0;
        return max(-100.0, min($phanTramTangTruong, 500.0));
    }

    private function calcSameIpAccounts(int $userId): int
    {
        if (!Schema::hasTable('sessions')) {
            return 1;
        }

        $ipGanNhat = DB::table('sessions')
            ->where('user_id', $userId)
            ->whereNotNull('ip_address')
            ->orderByDesc('last_activity')
            ->value('ip_address');

        if (!$ipGanNhat) {
            return 1;
        }

        $soTaiKhoan = DB::table('sessions')
            ->where('ip_address', $ipGanNhat)
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->count('user_id');

        return max(1, (int)$soTaiKhoan);
    }

    private function calcActivityScore(
        float $postsPerDay,
        float $contentSimilarity,
        float $donationGrowth,
        int $sameIpAccounts
    ): float {
        // Điểm tổng hợp đơn giản để phản ánh hành vi tổng quan.
        $diemTongHop = 0.0;
        $diemTongHop += $postsPerDay * 0.8;
        $diemTongHop += $contentSimilarity * 10.0;
        $diemTongHop += min(max($donationGrowth, 0.0), 300.0) / 30.0;
        $diemTongHop += $sameIpAccounts * 2.0;

        return $diemTongHop;
    }

    private function calcBurstActivity(int $userId): float
    {
        $count10m = DB::table('bai_dang')
            ->where('nguoi_dung_id', $userId)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->count();

        return (float) $count10m;
    }

    private function calcMaxJump(int $userId): float
    {
        if (!Schema::hasTable('ung_ho')) {
            return 0.0;
        }

        $amounts = DB::table('ung_ho')
            ->where('nguoi_dung_id', $userId)
            ->where('created_at', '>=', now()->subDays(14))
            ->orderBy('created_at')
            ->pluck('so_tien')
            ->map(fn ($v) => (float) $v)
            ->values()
            ->all();

        if (count($amounts) < 2) {
            return 0.0;
        }

        $maxJump = 0.0;
        for ($i = 1; $i < count($amounts); $i++) {
            $jump = abs($amounts[$i] - $amounts[$i - 1]);
            if ($jump > $maxJump) {
                $maxJump = $jump;
            }
        }

        return $maxJump;
    }

    private function calcVariance(int $userId): float
    {
        $texts = DB::table('bai_dang')
            ->where('nguoi_dung_id', $userId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->pluck('mo_ta')
            ->map(fn ($t) => strlen((string) $t))
            ->values()
            ->all();

        $n = count($texts);
        if ($n < 2) {
            return 0.0;
        }

        $mean = array_sum($texts) / $n;
        $sum = 0.0;
        foreach ($texts as $x) {
            $sum += ($x - $mean) ** 2;
        }

        return ($sum / $n) / max(1.0, $mean ** 2);
    }
}

