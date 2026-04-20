<?php

namespace App\Http\Controllers;

use App\Http\Requests\Fraud\FraudAutoCheckRequest;
use App\Http\Requests\Fraud\FraudCampaignAutoCheckRequest;
use App\Http\Requests\Fraud\UpdateFraudAlertRequest;
use App\Models\CanhBaoGianLan;
use App\Models\User;
use App\Services\CampaignFraudFeatureService;
use App\Services\FraudCheckService;
use App\Services\FraudFeatureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FraudController extends Controller
{
    /** Không tạo cảnh báo trùng: cùng user, CHO_XU_LY, trong khoảng thời gian này (giờ). */
    private const DEDUPE_HOURS = 24;

    /**
     * POST /api/admin/fraud-check/auto
     * Input:
     * {
     *   "user_ids": [1,2,3], // optional
     *   "limit": 20 // optional, used when user_ids is empty
     * }
     */
    public function autoCheck(
        FraudAutoCheckRequest $request,
        FraudFeatureService $featureService,
        FraudCheckService $fraudCheckService
    ) {
        $duLieuDauVao = $request->validated();

        $danhSachNguoiDungId = $duLieuDauVao['user_ids'] ?? [];
        if (empty($danhSachNguoiDungId)) {
            $gioiHan = (int)($duLieuDauVao['limit'] ?? 20);
            $danhSachNguoiDungId = User::query()
                ->orderByDesc('id')
                ->limit($gioiHan)
                ->pluck('id')
                ->all();
        }

        $duLieuDacTrung = $featureService->buildUsersFeatures($danhSachNguoiDungId);
        $duLieuRuiRoAi = $fraudCheckService->check($duLieuDacTrung);

        $bangRuiRoTheoNguoiDung = collect($duLieuRuiRoAi)->keyBy('user_id');

        $ketQua = collect($duLieuDacTrung)->map(function ($mucDacTrung) use ($bangRuiRoTheoNguoiDung) {
            $mucRuiRoAi = $bangRuiRoTheoNguoiDung->get($mucDacTrung['user_id']);
            $mucRuiRo = $mucRuiRoAi['risk'] ?? 'LOW';
            $danhSachLyDo = $this->getFraudReasons($mucDacTrung);
            return [
                'user_id' => $mucDacTrung['user_id'],
                'muc_rui_ro' => $mucRuiRo,
                'ly_do' => $danhSachLyDo,
                'dac_trung' => $mucDacTrung,
            ];
        })->values();

        $this->saveFraudAlerts($ketQua->all());

        return response()->json([
            'data' => $ketQua,
        ]);
    }

    /**
     * POST /api/admin/fraud-check/campaigns/auto
     * Gian lận theo chiến dịch / tiền ủng hộ (gọi AI `/campaign-fraud-check`).
     *
     * Body: { "campaign_ids": [1,2], "limit": 20 } — campaign_ids optional.
     */
    public function autoCheckCampaigns(
        FraudCampaignAutoCheckRequest $request,
        CampaignFraudFeatureService $campaignFraudFeatureService,
        FraudCheckService $fraudCheckService
    ) {
        $duLieuDauVao = $request->validated();

        $danhSachChienDichId = $duLieuDauVao['campaign_ids'] ?? [];
        if ($danhSachChienDichId === []) {
            if (!Schema::hasTable('chien_dich_gay_quy')) {
                return response()->json(['data' => []]);
            }
            $gioiHan = (int) ($duLieuDauVao['limit'] ?? 20);
            $gioiHan = max(1, min($gioiHan, 100));
            $danhSachChienDichId = DB::table('chien_dich_gay_quy')
                ->orderByDesc('id')
                ->limit($gioiHan)
                ->pluck('id')
                ->all();
        }

        $dacTrung = $campaignFraudFeatureService->buildCampaignsFeatures($danhSachChienDichId);
        if ($dacTrung === []) {
            return response()->json(['data' => []]);
        }

        $payloadAi = array_map(static function (array $muc) {
            return [
                'campaign_id' => (int) $muc['campaign_id'],
                'campaigns_per_user' => (float) $muc['campaigns_per_user'],
                'donation_growth' => (float) $muc['donation_growth'],
                'self_donation_ratio' => (float) $muc['self_donation_ratio'],
                'unique_donors' => (float) $muc['unique_donors'],
                'donation_frequency' => (float) $muc['donation_frequency'],
            ];
        }, $dacTrung);

        // AI yêu cầu tối thiểu 2 bản ghi; thêm baseline giả để không gãy endpoint.
        if (count($payloadAi) < 2) {
            $payloadAi[] = [
                'campaign_id' => 0,
                'campaigns_per_user' => 1.0,
                'donation_growth' => 8.0,
                'self_donation_ratio' => 0.08,
                'unique_donors' => 14.0,
                'donation_frequency' => 2.0,
            ];
        }

        $duLieuRuiRoAi = $fraudCheckService->checkCampaigns($payloadAi);
        $bangAi = collect($duLieuRuiRoAi)->keyBy(fn (array $dong) => (int) ($dong['campaign_id'] ?? -1));

        $ketQua = collect($dacTrung)->map(function (array $muc) use ($bangAi) {
            $idChienDich = (int) $muc['campaign_id'];
            $dongAi = $bangAi->get($idChienDich);
            $mucRuiRo = is_array($dongAi) ? (string) ($dongAi['risk'] ?? 'LOW') : 'LOW';

            return [
                'campaign_id' => $idChienDich,
                'chu_so_huu_id' => (int) $muc['chu_so_huu_id'],
                'muc_rui_ro' => $mucRuiRo,
                'ly_do' => $this->getCampaignFraudReasons($muc),
                'dac_trung' => $muc,
            ];
        })->values()->all();

        $this->saveCampaignFraudAlerts($ketQua);

        $phanHoi = collect($ketQua)->map(function (array $dong) {
            unset($dong['chu_so_huu_id']);
            if (isset($dong['dac_trung']) && is_array($dong['dac_trung'])) {
                unset($dong['dac_trung']['chu_so_huu_id']);
            }

            return $dong;
        })->values();

        return response()->json([
            'data' => $phanHoi,
        ]);
    }

    /**
     * GET /api/admin/fraud-alerts
     * Query: risk=HIGH|LOW, trang_thai=CHO_XU_LY|DA_KIEM_TRA|CANH_BAO_SAI, user_id=1, limit=20
     */
    public function getAlerts(Request $request)
    {
        $mucRuiRoLoc = strtoupper((string)$request->query('risk', ''));
        $trangThaiLoc = strtoupper((string)$request->query('trang_thai', ''));
        $nguoiDungIdLoc = $request->query('user_id');
        $gioiHan = (int)$request->query('limit', 20);
        $gioiHan = max(1, min($gioiHan, 100));

        $truyVan = CanhBaoGianLan::query()->orderByDesc('created_at');
        if (in_array($mucRuiRoLoc, ['HIGH', 'LOW'], true)) {
            if ($mucRuiRoLoc === 'HIGH') {
                $truyVan->where('diem_rui_ro', '>=', 70);
            } else {
                $truyVan->where('diem_rui_ro', '<', 70);
            }
        }

        $cacTrangThaiHopLe = ['CHO_XU_LY', 'DA_KIEM_TRA', 'CANH_BAO_SAI'];
        if ($trangThaiLoc !== '' && in_array($trangThaiLoc, $cacTrangThaiHopLe, true)) {
            $truyVan->where('trang_thai', $trangThaiLoc);
        }

        if ($nguoiDungIdLoc !== null && $nguoiDungIdLoc !== '') {
            $idNguoiDung = (int)$nguoiDungIdLoc;
            if ($idNguoiDung > 0) {
                $truyVan->where('nguoi_dung_id', $idNguoiDung);
            }
        }

        $danhSachCanhBao = $truyVan->limit($gioiHan)->get()->map(function (CanhBaoGianLan $canhBao) {
            $moTa = trim((string)($canhBao->mo_ta ?? ''));
            $danhSachLyDo = $moTa === '' ? [] : array_values(array_filter(array_map('trim', explode(' | ', $moTa))));

            return [
                'id' => (int)$canhBao->id,
                'user_id' => (int)$canhBao->nguoi_dung_id,
                'chien_dich_id' => $canhBao->chien_dich_id ? (int)$canhBao->chien_dich_id : null,
                'loai_gian_lan' => $canhBao->loai_gian_lan,
                'trang_thai' => $canhBao->trang_thai,
                'muc_rui_ro' => ((float)$canhBao->diem_rui_ro >= 70.0) ? 'HIGH' : 'LOW',
                'diem_rui_ro' => round((float)$canhBao->diem_rui_ro, 2),
                'ly_do' => $danhSachLyDo,
                'created_at' => $canhBao->created_at?->toIso8601String(),
            ];
        })->values();

        return response()->json([
            'data' => $danhSachCanhBao,
        ]);
    }

    /**
     * PATCH /api/admin/fraud-alerts/{id}
     */
    public function updateAlert(UpdateFraudAlertRequest $request, CanhBaoGianLan $canhBao)
    {
        $canhBao->update([
            'trang_thai' => $request->validated()['trang_thai'],
        ]);

        return response()->json([
            'data' => [
                'id' => (int)$canhBao->id,
                'trang_thai' => $canhBao->trang_thai,
            ],
        ]);
    }

    /**
     * Function name in English per requirement.
     *
     * @param array<string, mixed> $feature_data
     * @return array<int, string>
     */
    private function getFraudReasons(array $feature_data): array
    {
        $ly_do = [];

        if (($feature_data['posts_per_day'] ?? 0) > 5) {
            $ly_do[] = 'Spam bài đăng';
        }

        if (($feature_data['content_similarity'] ?? 0) > 0.8) {
            $ly_do[] = 'Nội dung trùng lặp';
        }

        if (($feature_data['donation_growth'] ?? 0) > 200) {
            $ly_do[] = 'Tăng tiền bất thường';
        }

        if (($feature_data['same_ip_accounts'] ?? 0) > 3) {
            $ly_do[] = 'Nhiều tài khoản cùng IP';
        }

        if (($feature_data['activity_score'] ?? 0) > 10) {
            $ly_do[] = 'Hành vi bất thường';
        }

        return $ly_do;
    }

    /**
     * @param  array<string, mixed>  $muc_dac_trung
     * @return array<int, string>
     */
    private function getCampaignFraudReasons(array $muc_dac_trung): array
    {
        $ly_do = [];

        if (($muc_dac_trung['campaigns_per_user'] ?? 0) >= 4) {
            $ly_do[] = 'Nhiều chiến dịch cùng tổ chức';
        }

        if (($muc_dac_trung['donation_growth'] ?? 0) >= 200) {
            $ly_do[] = 'Tăng ủng hộ bất thường (chiến dịch)';
        }

        if (($muc_dac_trung['self_donation_ratio'] ?? 0) >= 0.5) {
            $ly_do[] = 'Tỷ lệ tự ủng hộ cao';
        }

        if (($muc_dac_trung['unique_donors'] ?? 99) <= 3) {
            $ly_do[] = 'Ít người ủng hộ';
        }

        if (($muc_dac_trung['donation_frequency'] ?? 0) >= 8) {
            $ly_do[] = 'Ủng hộ dày đặc (7 ngày gần đây)';
        }

        return $ly_do;
    }

    /**
     * Function name in English per requirement.
     *
     * @param array<int, array<string, mixed>> $danh_sach_ket_qua
     */
    private function saveFraudAlerts(array $danh_sach_ket_qua): void
    {
        foreach ($danh_sach_ket_qua as $dong) {
            $mucRuiRo = (string)($dong['muc_rui_ro'] ?? 'LOW');
            if ($mucRuiRo !== 'HIGH') {
                continue;
            }

            $userId = (int)$dong['user_id'];
            if ($this->shouldSkipDuplicateAlert($userId)) {
                continue;
            }

            $danhSachLyDo = is_array($dong['ly_do'] ?? null) ? $dong['ly_do'] : [];
            $duLieuDacTrung = is_array($dong['dac_trung'] ?? null) ? $dong['dac_trung'] : [];
            $diemRuiRo = $this->computeStoredRiskScore($duLieuDacTrung, $danhSachLyDo);

            $loaiGianLan = !empty($danhSachLyDo) ? (string)$danhSachLyDo[0] : 'Hành vi bất thường';
            $moTa = implode(' | ', $danhSachLyDo);
            if ($moTa === '') {
                $moTa = 'Hành vi bất thường';
            }
            $moTa = mb_substr($moTa, 0, 255);

            $chienDichId = $this->detectCampaignId($userId);

            CanhBaoGianLan::create([
                'nguoi_dung_id' => $userId,
                'chien_dich_id' => $chienDichId,
                'loai_gian_lan' => $loaiGianLan,
                'diem_rui_ro' => $diemRuiRo,
                'mo_ta' => $moTa,
                'trang_thai' => 'CHO_XU_LY',
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Điểm lưu DB: đồng bộ với ngưỡng HIGH (>= 70) khi AI/rule đánh HIGH;
     * không chỉ dựa activity_score*5 (có thể thấp dù IsolationForest báo bất thường).
     *
     * @param array<string, mixed> $dacTrung
     * @param array<int, string> $lyDo
     */
    private function computeStoredRiskScore(array $dacTrung, array $lyDo): float
    {
        $posts = (float)($dacTrung['posts_per_day'] ?? 0);
        $sim = (float)($dacTrung['content_similarity'] ?? 0);
        $don = max(0.0, (float)($dacTrung['donation_growth'] ?? 0));
        $sameIp = max(1, (int)($dacTrung['same_ip_accounts'] ?? 1));
        $activity = (float)($dacTrung['activity_score'] ?? 0);

        $phanTram = 0.0;
        $phanTram += min(1.0, $posts / 12.0) * 22.0;
        $phanTram += min(1.0, $sim) * 22.0;
        $phanTram += min(1.0, $don / 300.0) * 18.0;
        $phanTram += min(1.0, ($sameIp - 1) / 7.0) * 18.0;
        $phanTram += min(1.0, $activity / 22.0) * 20.0;

        $diem = min(100.0, max(0.0, $phanTram));

        if ($diem < 70.0 && count($lyDo) > 0) {
            $diem = max($diem, min(95.0, 68.0 + count($lyDo) * 6.0));
        }
        if ($diem < 70.0) {
            $diem = 72.0;
        }

        return round($diem, 2);
    }

    /**
     * @param  array<string, mixed>  $dacTrung
     * @param  array<int, string>  $lyDo
     */
    private function computeStoredCampaignRiskScore(array $dacTrung, array $lyDo): float
    {
        $soChienDich = (float) ($dacTrung['campaigns_per_user'] ?? 0);
        $tangTruong = max(0.0, (float) ($dacTrung['donation_growth'] ?? 0));
        $tiLeTu = (float) ($dacTrung['self_donation_ratio'] ?? 0);
        $soNguoi = (float) ($dacTrung['unique_donors'] ?? 0);
        $tanSuat = (float) ($dacTrung['donation_frequency'] ?? 0);

        $phanTram = 0.0;
        $phanTram += min(1.0, $soChienDich / 10.0) * 22.0;
        $phanTram += min(1.0, $tangTruong / 300.0) * 22.0;
        $phanTram += min(1.0, $tiLeTu) * 18.0;
        $phanTram += min(1.0, max(0.0, (10.0 - $soNguoi) / 10.0)) * 18.0;
        $phanTram += min(1.0, $tanSuat / 25.0) * 20.0;

        $diem = min(100.0, max(0.0, $phanTram));

        if ($diem < 70.0 && count($lyDo) > 0) {
            $diem = max($diem, min(95.0, 68.0 + count($lyDo) * 6.0));
        }
        if ($diem < 70.0) {
            $diem = 72.0;
        }

        return round($diem, 2);
    }

    /**
     * @param  array<int, array<string, mixed>>  $danh_sach_ket_qua
     */
    private function saveCampaignFraudAlerts(array $danh_sach_ket_qua): void
    {
        foreach ($danh_sach_ket_qua as $dong) {
            $mucRuiRo = (string) ($dong['muc_rui_ro'] ?? 'LOW');
            if ($mucRuiRo !== 'HIGH') {
                continue;
            }

            $idChuSoHuu = (int) ($dong['chu_so_huu_id'] ?? 0);
            $idChienDich = (int) ($dong['campaign_id'] ?? 0);
            if ($idChuSoHuu <= 0 || $idChienDich <= 0) {
                continue;
            }

            if ($this->shouldSkipDuplicateCampaignAlert($idChuSoHuu, $idChienDich)) {
                continue;
            }

            $danhSachLyDo = is_array($dong['ly_do'] ?? null) ? $dong['ly_do'] : [];
            $duLieuDacTrung = is_array($dong['dac_trung'] ?? null) ? $dong['dac_trung'] : [];
            $diemRuiRo = $this->computeStoredCampaignRiskScore($duLieuDacTrung, $danhSachLyDo);

            $loaiGianLan = ! empty($danhSachLyDo) ? (string) $danhSachLyDo[0] : 'Gian lận chiến dịch / ủng hộ';
            $moTa = implode(' | ', $danhSachLyDo);
            if ($moTa === '') {
                $moTa = $loaiGianLan;
            }
            $moTa = mb_substr($moTa, 0, 255);

            CanhBaoGianLan::create([
                'nguoi_dung_id' => $idChuSoHuu,
                'chien_dich_id' => $idChienDich,
                'loai_gian_lan' => $loaiGianLan,
                'diem_rui_ro' => $diemRuiRo,
                'mo_ta' => $moTa,
                'trang_thai' => 'CHO_XU_LY',
                'created_at' => now(),
            ]);
        }
    }

    private function shouldSkipDuplicateAlert(int $userId): bool
    {
        return CanhBaoGianLan::query()
            ->where('nguoi_dung_id', $userId)
            ->where('trang_thai', 'CHO_XU_LY')
            ->where('created_at', '>=', now()->subHours(self::DEDUPE_HOURS))
            ->exists();
    }

    private function shouldSkipDuplicateCampaignAlert(int $userId, int $campaignId): bool
    {
        return CanhBaoGianLan::query()
            ->where('nguoi_dung_id', $userId)
            ->where('chien_dich_id', $campaignId)
            ->where('trang_thai', 'CHO_XU_LY')
            ->where('created_at', '>=', now()->subHours(self::DEDUPE_HOURS))
            ->exists();
    }

    /**
     * Tim chien dich gay quy nghi ngo cua user de gan vao canh bao.
     * Luu y: bai_dang feed KHONG phai chien dich gay quy.
     * Function name in English per requirement.
     */
    private function detectCampaignId(int $userId): ?int
    {
        if (!Schema::hasTable('to_chuc') || !Schema::hasTable('chien_dich_gay_quy')) {
            return null;
        }

        // Nghiệp vụ đúng: user -> to_chuc.nguoi_dung_id -> chien_dich_gay_quy.to_chuc_id
        $chienDichId = DB::table('chien_dich_gay_quy as cd')
            ->join('to_chuc as tc', 'tc.id', '=', 'cd.to_chuc_id')
            ->where('tc.nguoi_dung_id', $userId)
            ->whereIn('cd.trang_thai', ['CHO_XU_LY', 'HOAT_DONG', 'TAM_DUNG', 'HOAN_THANH'])
            ->orderByDesc('cd.created_at')
            ->orderByDesc('cd.id')
            ->value('cd.id');

        // Fallback nếu dữ liệu trạng thái campaign không theo bộ enum trên.
        if (!$chienDichId) {
            $chienDichId = DB::table('chien_dich_gay_quy as cd')
                ->join('to_chuc as tc', 'tc.id', '=', 'cd.to_chuc_id')
                ->where('tc.nguoi_dung_id', $userId)
                ->orderByDesc('cd.created_at')
                ->orderByDesc('cd.id')
                ->value('cd.id');
        }

        return $chienDichId ? (int)$chienDichId : null;
    }
}

