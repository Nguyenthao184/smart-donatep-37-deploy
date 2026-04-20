<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    /**
     * GET /api/admin/dashboard/summary
     */
    public function summary(Request $request)
    {
        $now = now();

        $calcMoM = function (float|int $current, float|int $prev): float {
            $current = (float) $current;
            $prev = (float) $prev;
            if ($prev > 0) {
                return (($current - $prev) / $prev) * 100.0;
            }
            if ($current > 0) {
                return 100.0;
            }
            return 0.0;
        };

        $tongNguoiDung = (int) DB::table('nguoi_dung')->count();
        $tongToChuc = (int) DB::table('to_chuc')->count();
        $tongChienDich = (int) DB::table('chien_dich_gay_quy')->count();
        $tongTienGayQuy = (float) DB::table('ung_ho')->sum('so_tien');

        $tongCanhBaoChoXuLy = (int) DB::table('canh_bao_gian_lan')
            ->where('trang_thai', 'CHO_XU_LY')
            ->count();

        // Month-over-month (tháng này vs tháng trước)
        $dauThangNay = $now->copy()->startOfMonth();
        $dauThangTruoc = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $cuoiThangTruoc = $now->copy()->subMonthNoOverflow()->endOfMonth();

        // Users created
        $nguoiDungThangNay = (int) DB::table('nguoi_dung')
            ->where('created_at', '>=', $dauThangNay)
            ->count();
        $nguoiDungThangTruoc = (int) DB::table('nguoi_dung')
            ->whereBetween('created_at', [$dauThangTruoc, $cuoiThangTruoc])
            ->count();

        // Organizations created
        $toChucThangNay = (int) DB::table('to_chuc')
            ->where('created_at', '>=', $dauThangNay)
            ->count();
        $toChucThangTruoc = (int) DB::table('to_chuc')
            ->whereBetween('created_at', [$dauThangTruoc, $cuoiThangTruoc])
            ->count();

        // Campaigns created
        $chienDichThangNay = (int) DB::table('chien_dich_gay_quy')
            ->where('created_at', '>=', $dauThangNay)
            ->count();
        $chienDichThangTruoc = (int) DB::table('chien_dich_gay_quy')
            ->whereBetween('created_at', [$dauThangTruoc, $cuoiThangTruoc])
            ->count();

        // Fraud alerts created
        $canhBaoThangNay = (int) DB::table('canh_bao_gian_lan')
            ->where('created_at', '>=', $dauThangNay)
            ->count();
        $canhBaoThangTruoc = (int) DB::table('canh_bao_gian_lan')
            ->whereBetween('created_at', [$dauThangTruoc, $cuoiThangTruoc])
            ->count();

        $tongThangNay = (float) DB::table('ung_ho')
            ->where('created_at', '>=', $dauThangNay)
            ->sum('so_tien');

        $tongThangTruoc = (float) DB::table('ung_ho')
            ->whereBetween('created_at', [$dauThangTruoc, $cuoiThangTruoc])
            ->sum('so_tien');

        $phanTramThayDoiGayQuy = $calcMoM($tongThangNay, $tongThangTruoc);

        return response()->json([
            'data' => [
                'total_nguoi_dung' => $tongNguoiDung,
                'total_to_chuc' => $tongToChuc,
                'total_chien_dich' => $tongChienDich,
                'total_tien_gay_quy' => round($tongTienGayQuy, 2),
                'canh_bao_cho_xu_ly' => $tongCanhBaoChoXuLy,

                // Per-card MoM metrics
                'nguoi_dung_thang_nay' => $nguoiDungThangNay,
                'nguoi_dung_thang_truoc' => $nguoiDungThangTruoc,
                'phan_tram_thay_doi_nguoi_dung' => round($calcMoM($nguoiDungThangNay, $nguoiDungThangTruoc), 2),

                'to_chuc_thang_nay' => $toChucThangNay,
                'to_chuc_thang_truoc' => $toChucThangTruoc,
                'phan_tram_thay_doi_to_chuc' => round($calcMoM($toChucThangNay, $toChucThangTruoc), 2),

                'chien_dich_thang_nay' => $chienDichThangNay,
                'chien_dich_thang_truoc' => $chienDichThangTruoc,
                'phan_tram_thay_doi_chien_dich' => round($calcMoM($chienDichThangNay, $chienDichThangTruoc), 2),

                'canh_bao_thang_nay' => $canhBaoThangNay,
                'canh_bao_thang_truoc' => $canhBaoThangTruoc,
                'phan_tram_thay_doi_canh_bao' => round($calcMoM($canhBaoThangNay, $canhBaoThangTruoc), 2),

                'tong_tien_gay_quy_thang_nay' => round($tongThangNay, 2),
                'tong_tien_gay_quy_thang_truoc' => round($tongThangTruoc, 2),
                // Backward-compatible key: this is fundraising MoM %
                'phan_tram_thay_doi_tien_gay_quy' => round($phanTramThayDoiGayQuy, 2),
            ],
        ]);
    }

    /**
     * GET /api/admin/dashboard/fundraising-by-month?year=2026
     * Always returns 12 months (missing months = 0).
     */
    public function fundraisingByMonth(Request $request)
    {
        $year = $request->query('year');
        $year = is_numeric($year) ? (int) $year : (int) now()->year;

        $currentYear = (int) now()->year;
        $year = max(2000, min($year, $currentYear));

        $rows = DB::table('ung_ho')
            ->selectRaw('MONTH(created_at) as m, SUM(so_tien) as total')
            ->whereYear('created_at', $year)
            ->groupBy(DB::raw('MONTH(created_at)'))
            ->orderBy(DB::raw('MONTH(created_at)'))
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r->m] = (float) $r->total;
        }

        $data = [];
        for ($m = 1; $m <= 12; $m++) {
            $data[] = [
                'month' => $m,
                'total' => round((float) ($map[$m] ?? 0.0), 2),
            ];
        }

        return response()->json([
            'data' => [
                'year' => $year,
                'months' => $data,
            ],
        ]);
    }

    /**
     * GET /api/admin/dashboard/featured-campaigns?limit=3
     */
    public function featuredCampaigns(Request $request)
    {
        $limit = (int) $request->query('limit', 3);
        $limit = max(1, min($limit, 10));

        $rows = DB::table('chien_dich_gay_quy as cd')
            ->leftJoin('to_chuc as tc', 'tc.id', '=', 'cd.to_chuc_id')
            ->select([
                'cd.id',
                'cd.ten_chien_dich',
                'cd.muc_tieu_tien',
                'cd.so_tien_da_nhan',
                'cd.ngay_ket_thuc',
                'tc.ten_to_chuc',
            ])
            ->orderByDesc('cd.so_tien_da_nhan')
            ->limit($limit)
            ->get();

        $data = $rows->map(function ($r) {
            $goal = (float) $r->muc_tieu_tien;
            $raised = (float) $r->so_tien_da_nhan;
            $progress = 0.0;
            if ($goal > 0) {
                $progress = ($raised / $goal) * 100.0;
            }
            // Clamp to avoid crazy UI bars when data inconsistent
            $progress = max(0.0, min($progress, 100.0));

            return [
                'id' => (int) $r->id,
                'title' => $r->ten_chien_dich,
                'organization' => $r->ten_to_chuc,
                'goal' => round($goal, 2),
                'raised' => round($raised, 2),
                'progress_percent' => round($progress, 2),
                'end_date' => $r->ngay_ket_thuc,
            ];
        })->values();

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * GET /api/admin/dashboard/recent-activities?limit=10
     */
    public function recentActivities(Request $request)
    {
        $limit = (int) $request->query('limit', 10);
        $limit = max(1, min($limit, 50));
        // Lấy đủ bản ghi mỗi loại để sau khi ưu tiên 1 dòng/loại vẫn còn dữ liệu lấp chỗ trống.
        $fetchLimit = min(50, max($limit * 3, 20));

        $alerts = DB::table('canh_bao_gian_lan as cb')
            ->leftJoin('nguoi_dung as nd', 'nd.id', '=', 'cb.nguoi_dung_id')
            ->select([
                'cb.id',
                'cb.loai_gian_lan',
                'cb.diem_rui_ro',
                'cb.trang_thai',
                'cb.created_at',
                'nd.ho_ten as user_name',
            ])
            ->orderByDesc('cb.created_at')
            ->limit($fetchLimit)
            ->get()
            ->map(function ($r) {
                return [
                    'type' => 'fraud_alert',
                    'source_id' => (int) $r->id,
                    'title' => 'Cảnh báo gian lận',
                    'detail' => trim((string) ($r->loai_gian_lan ?? '')),
                    'user' => $r->user_name,
                    'status' => $r->trang_thai,
                    'score' => (float) $r->diem_rui_ro,
                    'time' => $r->created_at,
                ];
            });

        $orgApprovals = DB::table('xac_minh_to_chuc as xmtc')
            ->leftJoin('nguoi_dung as nd', 'nd.id', '=', 'xmtc.nguoi_dung_id')
            ->select([
                'xmtc.id',
                'xmtc.ten_to_chuc',
                'xmtc.trang_thai',
                'xmtc.duyet_luc',
                'xmtc.created_at',
                'nd.ho_ten as user_name',
            ])
            ->orderByDesc(DB::raw('COALESCE(xmtc.duyet_luc, xmtc.created_at)'))
            ->limit($fetchLimit)
            ->get()
            ->map(function ($r) {
                $t = $r->duyet_luc ?? $r->created_at;
                $label = strtoupper((string) $r->trang_thai) === 'CHAP_NHAN'
                    ? 'Tổ chức được duyệt'
                    : 'Đăng ký tổ chức';
                return [
                    'type' => 'organization',
                    'source_id' => (int) $r->id,
                    'title' => $label,
                    'detail' => $r->ten_to_chuc,
                    'user' => $r->user_name,
                    'status' => $r->trang_thai,
                    'time' => $t,
                ];
            });

        $posts = DB::table('bai_dang as bd')
            ->leftJoin('nguoi_dung as nd', 'nd.id', '=', 'bd.nguoi_dung_id')
            ->select([
                'bd.id',
                'bd.tieu_de',
                'bd.created_at',
                'nd.ho_ten as user_name',
            ])
            ->orderByDesc('bd.created_at')
            ->limit($fetchLimit)
            ->get()
            ->map(function ($r) {
                return [
                    'type' => 'post',
                    'source_id' => (int) $r->id,
                    'title' => 'Bài đăng mới',
                    'detail' => $r->tieu_de,
                    'user' => $r->user_name,
                    'time' => $r->created_at,
                ];
            });

        $donations = DB::table('ung_ho as uh')
            ->leftJoin('nguoi_dung as nd', 'nd.id', '=', 'uh.nguoi_dung_id')
            ->leftJoin('chien_dich_gay_quy as cd', 'cd.id', '=', 'uh.chien_dich_gay_quy_id')
            ->select([
                'uh.id',
                'uh.so_tien',
                'uh.created_at',
                'nd.ho_ten as user_name',
                'cd.ten_chien_dich',
            ])
            ->orderByDesc('uh.created_at')
            ->limit($fetchLimit)
            ->get()
            ->map(function ($r) {
                return [
                    'type' => 'donation',
                    'source_id' => (int) $r->id,
                    'title' => 'Ủng hộ mới',
                    'detail' => $r->ten_chien_dich,
                    'user' => $r->user_name,
                    'amount' => (float) $r->so_tien,
                    'time' => $r->created_at,
                ];
            });

        $fingerprint = static function (array $item): string {
            $type = (string) ($item['type'] ?? '');
            $sid = (string) ($item['source_id'] ?? '');

            return $type . ':' . $sid;
        };

        // Quota: mỗi loại tối thiểu 1 dòng (bản mới nhất của loại đó), nếu loại không có dữ liệu thì bỏ qua.
        $streams = [$alerts, $orgApprovals, $posts, $donations];
        $quotaPicks = collect();
        foreach ($streams as $stream) {
            // $first = $stream->first();
            // if ($first !== null) {
            //     $quotaPicks->push($first);
            // }
            $topItems = $stream->take(2);
            foreach ($topItems as $item) {
                $quotaPicks->push($item);
            }
        }
        $quotaPicks = $quotaPicks
            ->sortByDesc(function (array $item) {
                return (string) ($item['time'] ?? '');
            })
            ->values();

        $pickedKeys = $quotaPicks->map($fingerprint)->all();
        $all = collect()
            ->merge($alerts)
            ->merge($orgApprovals)
            ->merge($posts)
            ->merge($donations)
            ->unique($fingerprint)
            ->sortByDesc(function (array $item) {
                return (string) ($item['time'] ?? '');
            })
            ->values();

        $merged = collect();
        foreach ($quotaPicks as $row) {
            if ($merged->count() >= $limit) {
                break;
            }
            $merged->push($row);
        }
        foreach ($all as $row) {
            if ($merged->count() >= $limit) {
                break;
            }
            $key = $fingerprint($row);
            if (in_array($key, $pickedKeys, true)) {
                continue;
            }
            $merged->push($row);
            $pickedKeys[] = $key;
        }

        return response()->json([
            'data' => $merged->values(),
        ]);
    }
}
