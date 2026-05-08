<?php

namespace App\Http\Controllers;

use App\Http\Requests\Fraud\FraudAutoCheckRequest;
use App\Http\Requests\Fraud\FraudCampaignAutoCheckRequest;
use App\Http\Requests\Fraud\UpdateFraudAlertRequest;
use App\Models\CanhBaoGianLan;
use App\Models\BaiDang;
use App\Models\User;
use App\Models\ChienDichGayQuy;
use App\Notifications\AdminViolationDetectedNotification;
use App\Notifications\ApprovalNotification;
use App\Services\CampaignFraudFeatureService;
use App\Services\AlertAggregationService;
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
        $bangAi = collect($duLieuRuiRoAi)->keyBy(fn(array $dong) => (int) ($dong['campaign_id'] ?? -1));

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
     * Query: risk=HIGH|LOW, trang_thai=CHO_XU_LY|DA_XU_LY, user_id=1, limit=20
     */
    public function getAlerts(Request $request)
    {
        $mucRuiRoLoc = strtoupper((string)$request->query('risk', ''));
        $trangThaiLoc = strtoupper((string)$request->query('trang_thai', ''));
        $nguoiDungIdLoc = $request->query('user_id');
        $gioiHan = (int)$request->query('limit', 20);
        $gioiHan = max(1, min($gioiHan, 100));

        $truyVan = CanhBaoGianLan::query()->orderByDesc('created_at');
        if (in_array($mucRuiRoLoc, ['HIGH', 'MEDIUM', 'LOW'], true)) {
            if (Schema::hasColumn('canh_bao_gian_lan', 'muc_rui_ro')) {
                $truyVan->where('muc_rui_ro', $mucRuiRoLoc);
            } elseif ($mucRuiRoLoc === 'HIGH') {
                $truyVan->where('diem_rui_ro', '>=', 70);
            } elseif ($mucRuiRoLoc === 'MEDIUM') {
                $truyVan->whereBetween('diem_rui_ro', [40, 69.99]);
            } else {
                $truyVan->where('diem_rui_ro', '<', 40);
            }
        }

        $cacTrangThaiHopLe = ['CHO_XU_LY', 'DA_XU_LY'];
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
            $targetType = strtolower((string) ($canhBao->target_type ?? ''));
            $targetId = (int) ($canhBao->target_id ?? 0);
            if ($targetType === '' || $targetId <= 0) {
                $targetType = $canhBao->chien_dich_id ? 'campaign' : 'user';
                $targetId = $canhBao->chien_dich_id ? (int) $canhBao->chien_dich_id : (int) $canhBao->nguoi_dung_id;
            }
            $score = round((float) $canhBao->diem_rui_ro, 2);
            $risk = (string) ($canhBao->muc_rui_ro ?? ($score >= 70 ? 'HIGH' : ($score >= 40 ? 'MEDIUM' : 'LOW')));
            $post = null;
            $campaign = null;

            if (strtolower($targetType) === 'post') {
                $post = BaiDang::where('id', $targetId)->first();
            }

            if (strtolower($targetType) === 'campaign') {
                $campaign = DB::table('chien_dich_gay_quy as cd')
                    ->leftJoin('to_chuc as tc', 'tc.id', '=', 'cd.to_chuc_id')
                    ->where('cd.id', $targetId)
                    ->select(
                        'cd.id',
                        'cd.ten_chien_dich',
                        'cd.hinh_anh',

                        'tc.ten_to_chuc'
                    )
                    ->first();
            }

            $source = strtoupper((string) ($canhBao->source ?? 'AI'));
            $loaiCanhBao = $canhBao->loai_canh_bao ?? $canhBao->loai_gian_lan;
            $lyDoOut = $canhBao->ly_do ?? $danhSachLyDo;

            // Convert USER_REPORT codes (spam_post/abusive_content/...) to Vietnamese friendly titles/descriptions.
            if ($source === 'USER_REPORT' || str_starts_with($moTa, 'USER_REPORT|')) {
                [$title, $desc, $code, $userDesc] = $this->formatUserReportReasonFromMeta($moTa);
                if ($title !== null) {
                    $loaiCanhBao = $title;
                }
                if ($desc !== null) {
                    $lyDoOut = $desc . ($userDesc ? (' | ' . $userDesc) : '');
                }
            }

            return [
                'id' => (int)$canhBao->id,
                'report_id' => $canhBao->details['report_id'] ?? null,
                'source' => $source,
                'type' => $targetType,
                'target' => $targetType . ' #' . $targetId,

                'target_type' => $targetType,
                'target_id' => $targetId,

                'user_id' => (int)$canhBao->nguoi_dung_id,
                'chien_dich_id' => $canhBao->chien_dich_id ? (int)$canhBao->chien_dich_id : null,

                'loai_gian_lan' => $canhBao->loai_gian_lan,
                'loai_canh_bao' => $loaiCanhBao,

                'trang_thai' => $canhBao->trang_thai,
                'decision' => $canhBao->decision ?? $canhBao->trang_thai,

                'admin_id' => $canhBao->admin_id ? (int) $canhBao->admin_id : null,
                'admin_note' => $canhBao->admin_note,
                'reviewed_at' => $canhBao->reviewed_at?->toIso8601String(),

                'muc_rui_ro' => $risk,
                'diem_rui_ro' => $score,

                'ly_do' => $lyDoOut,
                'created_at' => $canhBao->created_at?->toIso8601String(),

                // ✅ QUAN TRỌNG: check null
                'post' => $post ? [
                    'id' => $post->id,
                    'tieu_de' => $post->tieu_de,
                    'mo_ta' => $post->mo_ta,
                    'hinh_anh' => $post->hinh_anh ? ($post->hinh_anh[0] ?? null) : null,
                ] : null,

                'campaign' => $campaign ? [
                    'id' => $campaign->id,
                    'ten' => $campaign->ten_chien_dich ?? null,
                    'hinh_anh' => $campaign->hinh_anh ?? null,

                    'organization_name' => $campaign->ten_to_chuc ?? null,
                ] : null,
            ];
        })->values();

        return response()->json([
            'data' => $danhSachCanhBao,
        ]);
    }

    /**
     * Parse meta stored in `mo_ta` for USER_REPORT and map to friendly VN texts.
     *
     * @return array{0:?string,1:?string,2:?string,3:?string} [title, description, code, user_description]
     */
    private function formatUserReportReasonFromMeta(string $rawMeta): array
    {
        $code = null;
        $userDesc = null;

        // rawMeta example: USER_REPORT|post:12|report:34|ly_do:SPAM|mo_ta:...
        foreach (explode('|', $rawMeta) as $part) {
            $part = trim($part);
            if (str_starts_with($part, 'ly_do:')) {
                $code = strtoupper(trim(substr($part, strlen('ly_do:'))));
            }
            if (str_starts_with($part, 'mo_ta:')) {
                $userDesc = trim(substr($part, strlen('mo_ta:')));
            }
        }

        $labels = [
            'SPAM' => ['title' => 'Spam bài đăng', 'description' => 'Bài đăng này có dấu hiệu spam, vui lòng xem xét.'],
            'LUA_DAO' => ['title' => 'Dấu hiệu lừa đảo', 'description' => 'Bài đăng này có dấu hiệu lừa đảo, vui lòng xem xét.'],
            'NOI_DUNG_XAU' => ['title' => 'Nội dung xấu/không phù hợp', 'description' => 'Bài đăng có nội dung xấu/không phù hợp, vui lòng xem xét.'],
            'KHAC' => ['title' => 'Lý do khác', 'description' => 'Lý do khác do người dùng cung cấp, cần admin xác minh.'],
        ];

        if ($code === null || !isset($labels[$code])) {
            return [null, null, $code, $userDesc];
        }

        return [$labels[$code]['title'], $labels[$code]['description'], $code, $userDesc];
    }

    /**
     * PATCH /api/admin/fraud-alerts/{id}
     */
    public function updateAlert(UpdateFraudAlertRequest $request, CanhBaoGianLan $canhBao)
    {
        $data = $request->validated();
        $trangThai = $data['trang_thai'];
        $decision = $data['decision'] ?? match ($trangThai) {
            'DA_XU_LY' => 'VI_PHAM',
            default => 'CHO_XU_LY',
        };

        $canhBao->update([
            'trang_thai' => $trangThai,
            'decision' => $decision,
            'admin_id' => (int) $request->user()->id,
            'admin_note' => $data['admin_note'] ?? null,
            'reviewed_at' => now(),
        ]);

        if ($trangThai === 'DA_XU_LY' && $decision === 'VI_PHAM') {
            $this->notifyViolationToUser($canhBao, (string) ($data['admin_note'] ?? null));
        }

        return response()->json([
            'data' => [
                'id' => (int)$canhBao->id,
                'trang_thai' => $canhBao->trang_thai,
                'decision' => $canhBao->decision,
                'admin_id' => $canhBao->admin_id,
                'reviewed_at' => $canhBao->reviewed_at?->toIso8601String(),
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

        if (($feature_data['content_similarity'] ?? 0) > 0.9) {
            $ly_do[] = 'Nội dung trùng lặp';
        }

        if (($feature_data['donation_growth'] ?? 0) > 200) {
            $ly_do[] = 'Tăng tiền bất thường';
        }

        if (($feature_data['same_ip_accounts'] ?? 0) > 5) {
            $ly_do[] = 'Nhiều tài khoản cùng IP';
        }

        if (($feature_data['activity_score'] ?? 0) > 10) {
            $ly_do[] = 'Hành vi bất thường';
        }

        if (($feature_data['burst_activity'] ?? 0) > 6) {
            $ly_do[] = 'Bùng nổ hoạt động bất thường';
        }

        if (($feature_data['max_jump'] ?? 0) > 2500000) {
            $ly_do[] = 'Biến động giá trị đột ngột';
        }

        if (($feature_data['variance'] ?? 0) > 0.15) {
            $ly_do[] = 'Độ lệch hành vi cao';
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
        
        $soChienDich = (float) ($muc_dac_trung['campaigns_per_user'] ?? 0);
        $tangTruong = max(0.0, (float) ($muc_dac_trung['donation_growth'] ?? 0));
        $tiLeTu = (float) ($muc_dac_trung['self_donation_ratio'] ?? 0);
        $soNguoi = (float) ($muc_dac_trung['unique_donors'] ?? 0);  
        $tanSuat = (float) ($muc_dac_trung['donation_frequency'] ?? 0);
    
        if ($soChienDich >= 4) {
            $ly_do[] = 'Nhiều chiến dịch cùng tổ chức';
        }
    
        if ($tangTruong >= 200) {
            $ly_do[] = 'Tăng ủng hộ bất thường (chiến dịch)';
        }
    
        if ($tiLeTu >= 0.5) {
            $ly_do[] = 'Tỷ lệ tự ủng hộ cao';
        }
    
        if ($soNguoi > 0 && $soNguoi <= 3) {
            $ly_do[] = 'Ít người ủng hộ';
        }
    
        if ($tanSuat >= 8) {
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
        $aggregator = app(AlertAggregationService::class);
        foreach ($danh_sach_ket_qua as $dong) {
            $mucRuiRo = (string)($dong['muc_rui_ro'] ?? 'LOW');
            if ($mucRuiRo !== 'HIGH') {
                continue;
            }

            $userId = (int)$dong['user_id'];
            $danhSachLyDo = is_array($dong['ly_do'] ?? null) ? $dong['ly_do'] : [];
            $duLieuDacTrung = is_array($dong['dac_trung'] ?? null) ? $dong['dac_trung'] : [];
            $diemRuiRo = $this->computeStoredRiskScore($duLieuDacTrung, $danhSachLyDo);

            $chienDichId = $this->detectCampaignId($userId);
            $targetType = $chienDichId ? 'campaign' : 'user';
            $targetId = $chienDichId ?: $userId;

            foreach ($danhSachLyDo as $lyDo) {
                $violation = $this->mapAiUserReasonToAggregation((string) $lyDo, $duLieuDacTrung, $userId);
                if ($violation === null) {
                    continue;
                }
                if ($this->wasRejectedByAdmin($userId, $chienDichId, $violation['title'], $violation['reason_text'])) {
                    continue;
                }

                $alert = $aggregator->upsertAggregatedAlert(
                    userId: $userId,
                    targetType: $targetType,
                    targetId: $targetId,
                    source: 'AI',
                    violationCode: $violation['code'],
                    title: $violation['title'],
                    reasonText: $violation['reason_text'],
                    score: $diemRuiRo,
                    campaignId: $chienDichId,
                    initialCount: $violation['count'],
                    timeWindowSeconds: $violation['time_window_seconds'],
                    detailItems: $violation['details'],
                    notifyOnCreate: true,
                    notify: function (CanhBaoGianLan $canhBao) {
                        $this->notifyAdminsForAlert($canhBao, 'AI phát hiện hành vi nghi ngờ (đã gom nhóm)');
                    }
                );

                // keep $alert in scope for potential future side effects
                unset($alert);
            }
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
        $aggregator = app(AlertAggregationService::class);
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

            foreach ($danhSachLyDo as $lyDo) {
                $violation = $this->mapAiCampaignReasonToAggregation((string) $lyDo, $duLieuDacTrung, $idChienDich);
                if ($violation === null) {
                    continue;
                }
                if ($this->wasRejectedByAdmin($idChuSoHuu, $idChienDich, $violation['title'], $violation['reason_text'])) {
                    continue;
                }

                $alert = $aggregator->upsertAggregatedAlert(
                    userId: $idChuSoHuu,
                    targetType: 'campaign',
                    targetId: $idChienDich,
                    source: 'AI',
                    violationCode: $violation['code'],
                    title: $violation['title'],
                    reasonText: $violation['reason_text'],
                    score: $diemRuiRo,
                    campaignId: $idChienDich,
                    initialCount: $violation['count'],
                    timeWindowSeconds: $violation['time_window_seconds'],
                    detailItems: $violation['details'],
                    notifyOnCreate: true,
                    notify: function (CanhBaoGianLan $canhBao) {
                        $this->notifyAdminsForAlert($canhBao, 'AI phát hiện chiến dịch nghi ngờ (đã gom nhóm)');
                    }
                );
                unset($alert);
            }
        }
    }

    /**
     * @param array<string, mixed> $features
     * @return array{code:string,title:string,reason_text:string,count:int,time_window_seconds:?int,details:array<int,array<string,mixed>>}|null
     */
    private function mapAiUserReasonToAggregation(string $reason, array $features, int $userId): ?array
    {
        $reason = trim($reason);
        if ($reason === '') {
            return null;
        }

        $code = match ($reason) {
            'Spam bài đăng' => 'AI_SPAM_BAI_DANG',
            'Nội dung trùng lặp' => 'AI_NOI_DUNG_TRUNG_LAP',
            'Tăng tiền bất thường' => 'AI_TANG_TIEN_BAT_THUONG',
            'Nhiều tài khoản cùng IP' => 'AI_NHIEU_IP',
            'Hành vi bất thường' => 'AI_HANH_VI_BAT_THUONG',
            'Bùng nổ hoạt động bất thường' => 'AI_POSTING_TOO_FAST',
            'Biến động giá trị đột ngột' => 'AI_MAX_JUMP',
            'Độ lệch hành vi cao' => 'AI_CONTENT_VARIANCE',
            default => null,
        };
        if ($code === null) {
            return null;
        }

        $timeWindowSeconds = null;
        $count = 1;
        $details = [];

        if ($code === 'AI_POSTING_TOO_FAST') {
            $timeWindowSeconds = 10 * 60;
            $count = (int) max(1, (int) ($features['burst_activity'] ?? 1));
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

        if ($code === 'AI_SPAM_BAI_DANG') {
            $timeWindowSeconds = 24 * 60 * 60;
            $count = (int) max(1, (int) round((float) ($features['posts_per_day'] ?? 1)));
            $ids = DB::table('bai_dang')
                ->where('nguoi_dung_id', $userId)
                ->where('created_at', '>=', now()->subDay())
                ->orderByDesc('id')
                ->limit(20)
                ->pluck('id')
                ->all();
            foreach ($ids as $id) {
                $details[] = ['type' => 'post', 'id' => (int) $id];
            }
        }

        if ($code === 'AI_NOI_DUNG_TRUNG_LAP') {
            $timeWindowSeconds = 10 * 60;
            $count = 0;
            $ids = DB::table('bai_dang')
                ->where('nguoi_dung_id', $userId)
                ->orderByDesc('created_at')
                ->limit(8)
                ->pluck('id')
                ->all();
            foreach ($ids as $id) {
                $count++;
                $details[] = ['type' => 'post', 'id' => (int) $id];
            }
            $count = max(2, $count);
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

    /**
     * @param array<string, mixed> $features
     * @return array{code:string,title:string,reason_text:string,count:int,time_window_seconds:?int,details:array<int,array<string,mixed>>}|null
     */
    private function mapAiCampaignReasonToAggregation(string $reason, array $features, int $campaignId): ?array
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
            default => null,
        };
        if ($code === null) {
            return null;
        }

        $details = [
            ['type' => 'campaign', 'id' => (int) $campaignId],
        ];

        $timeWindowSeconds = null;
        $count = 1;
        if ($code === 'AI_UNG_HO_DAY_DAC') {
            $timeWindowSeconds = 7 * 24 * 60 * 60;
            $count = (int) max(1, (int) round((float) ($features['donation_frequency'] ?? 1)));
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

    private function notifyAdminsForAlert(CanhBaoGianLan $canhBao, string $source): void
    {
        $admins = User::query()
            ->whereHas('roles', fn($q) => $q->where('ten_vai_tro', 'ADMIN'))
            ->get();

        $tt = strtolower((string) ($canhBao->target_type ?? ''));
        $postId = $tt === 'post' ? (int) $canhBao->target_id : null;

        foreach ($admins as $admin) {
            $admin->notify(new AdminViolationDetectedNotification(
                source: $source,
                canhBaoId: (int) $canhBao->id,
                userId: (int) $canhBao->nguoi_dung_id,
                campaignId: $canhBao->chien_dich_id ? (int) $canhBao->chien_dich_id : null,
                postId: $postId,
                reason: $canhBao->loai_gian_lan ?: ($canhBao->mo_ta ?: 'Vi phạm nghi ngờ'),
                description: null,
                scenario: AdminViolationDetectedNotification::SCENARIO_AI
            ));
        }
    }

    private function wasRejectedByAdmin(
        int $userId,
        ?int $campaignId,
        string $loaiGianLan,
        string $moTa
    ): bool {
        $query = CanhBaoGianLan::query()
            ->where('nguoi_dung_id', $userId)
            ->where('trang_thai', 'DA_XU_LY')
            ->where('decision', 'KHONG_VI_PHAM');

        if ($campaignId) {
            $query->where('chien_dich_id', $campaignId);
        } else {
            $query->whereNull('chien_dich_id');
        }

        $query->where(function ($q) use ($loaiGianLan, $moTa) {
            $q->where('loai_gian_lan', $loaiGianLan)
                ->orWhere('mo_ta', $moTa);
        });

        return $query->exists();
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

    private function normalizeAlertType(string $label): string
    {
        $normalized = mb_strtolower(trim($label));
        return str_replace(' ', '_', $normalized);
    }

    private function notifyViolationToUser(CanhBaoGianLan $canhBao, ?string $adminNote): void
    {
        $user = User::query()->find((int) $canhBao->nguoi_dung_id);
        if (!$user) {
            return;
        }

        $targetType = strtolower((string) ($canhBao->target_type ?? 'user'));
        $targetId = (int) ($canhBao->target_id ?? $canhBao->nguoi_dung_id);
        $entity = match ($targetType) {
            'post' => 'Bài đăng',
            'campaign' => 'Chiến dịch',
            default => 'Tài khoản',
        };

        $reason = $adminNote ?: ((string) ($canhBao->ly_do ?? $canhBao->loai_canh_bao ?? 'Vi phạm chính sách'));
        $user->notify(new ApprovalNotification(
            type: 'lock',
            name: $entity,
            reason: $reason,
            targetType: $targetType,
            targetId: $targetId
        ));
    }
}
