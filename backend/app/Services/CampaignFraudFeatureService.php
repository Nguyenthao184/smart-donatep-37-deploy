<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CampaignFraudFeatureService
{
    /**
     * Build payload cho AI `/campaign-fraud-check` (key tiếng Anh theo Pydantic).
     * Thêm `chu_so_huu_id` để controller gán cảnh báo đúng user tổ chức.
     *
     * @param  array<int>  $chienDichIds
     * @return array<int, array<string, float|int>>
     */
    public function buildCampaignsFeatures(array $chienDichIds): array
    {
        if (!Schema::hasTable('chien_dich_gay_quy') || $chienDichIds === []) {
            return [];
        }

        $ketQua = [];

        foreach ($chienDichIds as $idChienDich) {
            $idChienDich = (int) $idChienDich;
            if ($idChienDich <= 0) {
                continue;
            }

            $dong = DB::table('chien_dich_gay_quy as cd')
                ->join('to_chuc as tc', 'tc.id', '=', 'cd.to_chuc_id')
                ->where('cd.id', $idChienDich)
                ->first(['cd.id as cd_id', 'cd.to_chuc_id', 'tc.nguoi_dung_id']);

            if (!$dong) {
                continue;
            }

            $idToChuc = (int) $dong->to_chuc_id;
            $chuSoHuuId = (int) $dong->nguoi_dung_id;

            $soChienDichMoiToChuc = $this->demSoChienDichTheoToChuc($idToChuc);
            $tangTruongUngHo = $this->tinhTangTruongUngHoTheoChienDich($idChienDich);
            $tiLeTuUngHo = $this->tinhTiLeTuUngHo($idChienDich, $chuSoHuuId);
            $soNguoiUngHoKhac = $this->demNguoiUngHoKhacNhau($idChienDich);
            $tanSuatUngHo = $this->tinhTanSuatUngHoBayNgay($idChienDich);

            $ketQua[] = [
                'campaign_id' => $idChienDich,
                'chu_so_huu_id' => $chuSoHuuId,
                'campaigns_per_user' => round((float) $soChienDichMoiToChuc, 4),
                'donation_growth' => round((float) $tangTruongUngHo, 4),
                'self_donation_ratio' => round((float) $tiLeTuUngHo, 4),
                'unique_donors' => round((float) $soNguoiUngHoKhac, 4),
                'donation_frequency' => round((float) $tanSuatUngHo, 4),
            ];
        }

        return $ketQua;
    }

    private function demSoChienDichTheoToChuc(int $idToChuc): int
    {
        return (int) DB::table('chien_dich_gay_quy')
            ->where('to_chuc_id', $idToChuc)
            ->count();
    }

    private function tinhTangTruongUngHoTheoChienDich(int $idChienDich): float
    {
        if (!Schema::hasTable('ung_ho')) {
            return 0.0;
        }

        $mocGanDay = now()->subDays(7);
        $mocTruocBatDau = now()->subDays(14);
        $mocTruocKetThuc = now()->subDays(7);

        $tongGanDay = (float) DB::table('ung_ho')
            ->where('chien_dich_gay_quy_id', $idChienDich)
            ->where('created_at', '>=', $mocGanDay)
            ->sum('so_tien');

        $tongTruoc = (float) DB::table('ung_ho')
            ->where('chien_dich_gay_quy_id', $idChienDich)
            ->whereBetween('created_at', [$mocTruocBatDau, $mocTruocKetThuc])
            ->sum('so_tien');

        if ($tongTruoc <= 0 && $tongGanDay > 0) {
            return 300.0;
        }

        if ($tongTruoc <= 0) {
            return 0.0;
        }

        $phanTram = (($tongGanDay - $tongTruoc) / $tongTruoc) * 100.0;

        return max(-100.0, min($phanTram, 500.0));
    }

    private function tinhTiLeTuUngHo(int $idChienDich, int $chuSoHuuId): float
    {
        if (!Schema::hasTable('ung_ho')) {
            return 0.0;
        }

        $tongTatCa = (float) DB::table('ung_ho')
            ->where('chien_dich_gay_quy_id', $idChienDich)
            ->sum('so_tien');

        if ($tongTatCa <= 0) {
            return 0.0;
        }

        $tongTuChu = (float) DB::table('ung_ho')
            ->where('chien_dich_gay_quy_id', $idChienDich)
            ->where('nguoi_dung_id', $chuSoHuuId)
            ->sum('so_tien');

        return min(1.0, max(0.0, $tongTuChu / $tongTatCa));
    }

    private function demNguoiUngHoKhacNhau(int $idChienDich): int
    {
        if (!Schema::hasTable('ung_ho')) {
            return 0;
        }

        return (int) DB::table('ung_ho')
            ->where('chien_dich_gay_quy_id', $idChienDich)
            ->selectRaw('COUNT(DISTINCT nguoi_dung_id) as c')
            ->value('c');
    }

    private function tinhTanSuatUngHoBayNgay(int $idChienDich): float
    {
        if (!Schema::hasTable('ung_ho')) {
            return 0.0;
        }

        $soLan = (int) DB::table('ung_ho')
            ->where('chien_dich_gay_quy_id', $idChienDich)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return (float) $soLan;
    }
}
