<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\ChienDichGayQuy;
use App\Models\UngHo;

class DashboardController extends Controller
{
    public function index()
    {
        $org = auth()->user()->toChuc;
        $orgId = $org->id;

        // tổng chiến dịch
        $totalCampaigns = ChienDichGayQuy::where('to_chuc_id', $orgId)->count();

        // chiến dịch đang hoạt động
        $activeCampaigns = ChienDichGayQuy::where('to_chuc_id', $orgId)
            ->where('trang_thai', 'HOAT_DONG')
            ->count();

        // tổng tiền
        $totalAmount = UngHo::whereHas('chienDich', function ($q) use ($orgId) {
            $q->where('to_chuc_id', $orgId);
        })->sum('so_tien');

        // tổng lượt ủng hộ
        $totalDonations = UngHo::whereHas('chienDich', function ($q) use ($orgId) {
            $q->where('to_chuc_id', $orgId);
        })->count();

        return response()->json([
            'ten_to_chuc' => $org->ten_to_chuc,
            'tong_chien_dich' => $totalCampaigns,
            'tong_chien_dich_hd' => $activeCampaigns,
            'tong_tien_nhan' => $totalAmount,
            'tong_luot_ung_ho' => $totalDonations,
        ]);
    }

    // Thống kê tài chính theo khoảng thời gian
    public function financialSummary(Request $request)
    {
        $orgId = auth()->user()->toChuc->id;
        $type = $request->get('type', 'thang'); // week | month | quarter | year

        $query = DB::table('giao_dich_quy')
            ->where('tai_khoan_gay_quy_id', $orgId);

        // ===== FILTER THEO THỜI GIAN =====
        switch ($type) {
            case 'tuan':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;

            case 'thang':
                $query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
                break;

            case 'quy':
                $query->whereBetween('created_at', [
                    now()->firstOfQuarter(),
                    now()->lastOfQuarter()
                ]);
                break;

            case 'nam':
                $query->whereYear('created_at', now()->year);
                break;
        }

        $data = $query->selectRaw("
            SUM(CASE WHEN loai_giao_dich = 'UNG_HO' THEN so_tien ELSE 0 END) as total_received,
            SUM(CASE WHEN loai_giao_dich = 'RUT' THEN so_tien ELSE 0 END) as total_spent
        ")->first();

        $received = (float) $data->total_received;
        $spent = (float) $data->total_spent;
        $balance = $received - $spent;

        return response()->json([
            'loai' => $type,
            'tien_nhan' => $received,
            'tien_chi' => $spent,
            'so_du' => $balance,
        ]);
    }

    // Thống kê theo tháng
    public function monthlyStatistics()
    {
        $orgId = auth()->user()->toChuc->id;

        $data = DB::table('giao_dich_quy')
            ->selectRaw("
                MONTH(created_at) as month,
                SUM(CASE WHEN loai_giao_dich = 'UNG_HO' THEN so_tien ELSE 0 END) as tien_nhan,
                SUM(CASE WHEN loai_giao_dich = 'RUT' THEN so_tien ELSE 0 END) as tien_chi
            ")
            ->where('tai_khoan_gay_quy_id', $orgId)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $labels = [];
        $tienNhan = [];
        $tienChi = [];

        $map = [];
        foreach ($data as $item) {
            $map[$item->month] = $item;
        }

        for ($i = 1; $i <= 12; $i++) {
            $labels[] = 'T' . $i;
            $tienNhan[] = isset($map[$i]) ? (float)$map[$i]->tien_nhan : 0;
            $tienChi[] = isset($map[$i]) ? (float)$map[$i]->tien_chi : 0;
        }

        return response()->json([
            'labels' => $labels,
            'tien_nhan' => $tienNhan,
            'tien_chi' => $tienChi,
        ]);
    }

    // Chiến dịch đang chạy
    public function activeCampaigns()
    {
        $orgId = auth()->user()->toChuc->id;

        $data = DB::table('chien_dich_gay_quy')
            ->where('to_chuc_id', $orgId)
            ->where('trang_thai', 'HOAT_DONG')
            ->select(
                'id',
                'ten_chien_dich',
                'muc_tieu_tien',
                'so_tien_da_nhan',
                'ngay_ket_thuc'
            )
            ->get();

        $result = $data->map(function ($item) {
            $percent = $item->muc_tieu_tien > 0
                ? round(($item->so_tien_da_nhan / $item->muc_tieu_tien) * 100)
                : 0;

            $ngayConLai = max(0, floor(now()->diffInDays($item->ngay_ket_thuc, false)));

            return [
                'id' => $item->id,
                'ten_chien_dich' => $item->ten_chien_dich,
                'so_tien_da_nhan' => (float)$item->so_tien_da_nhan,
                'muc_tieu_tien' => (float)$item->muc_tieu_tien,
                'phan_tram' => $percent,
                'so_ngay_con_lai' => $ngayConLai,
            ];
        });

        return response()->json($result);
    }

    // Hoạt động gần đây
    public function recentActivities()
    {
        $orgId = auth()->user()->toChuc->id;

        $data = DB::table('giao_dich_quy as gd')
            ->leftJoin('ung_ho as uh', 'gd.ung_ho_id', '=', 'uh.id')
            ->leftJoin('nguoi_dung as nd', 'uh.nguoi_dung_id', '=', 'nd.id')
            ->leftJoin('chien_dich_gay_quy as cd', function ($join) {
                $join->on('cd.id', '=', 'uh.chien_dich_gay_quy_id')
                    ->orOn('cd.id', '=', 'gd.chien_dich_gay_quy_id');
            })
            ->where('cd.to_chuc_id', $orgId)
            ->select(
                'gd.so_tien',
                'gd.loai_giao_dich',
                'gd.created_at',
                'nd.ho_ten',
                'cd.ten_chien_dich',
                'gd.mo_ta'
            )
            ->orderByDesc('gd.created_at')
            ->limit(20)
            ->get();

        $result = $data->map(function ($item) {
            return [
                'ten' => $item->ho_ten ?? 'Hệ thống',
                'chien_dich' => $item->ten_chien_dich ?? '',
                'mo_ta' => $item->mo_ta,
                'so_tien' => (float)$item->so_tien,
                'loai' => $item->loai_giao_dich,
                'thoi_gian' => \Carbon\Carbon::parse($item->created_at)
                    ->locale('vi')
                    ->diffForHumans(),
            ];
        });

        return response()->json($result);
    }
    public function communityStats()
    {
        $soBaiTrongTuan = DB::table('bai_dang')
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->count();

        $tongDoDaTang = DB::table('bai_dang')
            ->where('loai_bai', 'CHO')
            ->where('trang_thai', 'DA_TANG')
            ->sum('so_luong');

        $soNguoiDuocHoTro = DB::table('bai_dang')
            ->where('loai_bai', 'NHAN')
            ->where('trang_thai', 'DA_NHAN')
            ->whereNotNull('nguoi_dung_id')
            ->distinct()
            ->count('nguoi_dung_id');
        return response()->json([
            'so_bai_trong_tuan' => $soBaiTrongTuan,
            'tong_do_da_tang' => $tongDoDaTang,
            'so_nguoi_duoc_tang' => $soNguoiDuocHoTro,
        ]);
    }

    //các chiến dịch khác
    public function otherCampaigns()
    {
        $orgId = auth()->user()->toChuc->id;

        $data = DB::table('chien_dich_gay_quy')
            ->where('to_chuc_id', $orgId)
            ->where('trang_thai', '!=', 'HOAT_DONG')
            ->select(
                'id',
                'ten_chien_dich',
                'muc_tieu_tien',
                'so_tien_da_nhan',
                'ngay_ket_thuc',
                'trang_thai'
            )
            ->get();

        $result = $data->map(function ($item) {
            $percent = $item->muc_tieu_tien > 0
                ? round(($item->so_tien_da_nhan / $item->muc_tieu_tien) * 100)
                : 0;

            $ngayConLai = max(0, floor(now()->diffInDays($item->ngay_ket_thuc, false)));

            return [
                'id' => $item->id,
                'ten_chien_dich' => $item->ten_chien_dich,
                'so_tien_da_nhan' => (float)$item->so_tien_da_nhan,
                'muc_tieu_tien' => (float)$item->muc_tieu_tien,
                'phan_tram' => $percent,
                'so_ngay_con_lai' => $ngayConLai,
                'trang_thai' => $item->trang_thai,
            ];
        });

        return response()->json($result);
    }
}
