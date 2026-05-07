<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ChienDichGayQuy;
use App\Models\GiaoDichQuy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WithdrawRequestController extends Controller
{
    // GET /withdraw-requests
    public function index()
    {
        $orgId = auth()->user()->toChuc->id;

        $data = DB::table('giao_dich_quy as gd')
            ->join('chien_dich_gay_quy as cd', 'gd.chien_dich_gay_quy_id', '=', 'cd.id')
            ->where('cd.to_chuc_id', $orgId)
            ->where('gd.loai_giao_dich', 'RUT')
            ->select(
                'gd.id',
                'gd.so_tien',
                'gd.mo_ta',
                'gd.trang_thai',
                'gd.ma_giao_dich_ngan_hang',
                'gd.ghi_chu_admin',
                'gd.ngay_giao_dich',
                'gd.created_at as thoi_gian',
                'cd.ten_chien_dich'
            )
            ->latest('gd.id')
            ->get()
            ->map(function ($item) {

                $item->thoi_gian = \Carbon\Carbon::parse($item->thoi_gian)
                    ->format('d/m/Y H:i');

                return $item;
            });

        return response()->json($data);
    }

    // POST /withdraw-requests
    public function store(Request $request)
    {
        $request->validate([
            'chien_dich_gay_quy_id' => 'required|exists:chien_dich_gay_quy,id',
            'so_tien' => 'required|numeric|min:1000',
            'mo_ta' => 'nullable|string|max:255'
        ]);

        $orgId = auth()->user()->toChuc->id;

        $campaign = ChienDichGayQuy::where('id', $request->chien_dich_gay_quy_id)
            ->where('to_chuc_id', $orgId)
            ->firstOrFail();

        $taiKhoan = DB::table('tai_khoan_gay_quy')
            ->where('to_chuc_id', $orgId)
            ->first();

        GiaoDichQuy::create([
            'tai_khoan_gay_quy_id' => $taiKhoan->id,
            'chien_dich_gay_quy_id' => $campaign->id,
            'so_tien' => $request->so_tien,
            'loai_giao_dich' => 'RUT',
            'mo_ta' => $request->mo_ta,
            'trang_thai' => 'CHO_DUYET'
        ]);

        return response()->json([
            'message' => 'Tạo yêu cầu rút tiền thành công'
        ]);
    }

    // GET /withdraw-requests/campaigns
    public function campaigns()
    {
        $orgId = auth()->user()->toChuc->id;

        $campaigns = ChienDichGayQuy::where('to_chuc_id', $orgId)
            ->where('trang_thai', 'HOAT_DONG')
            ->get()
            ->map(function ($campaign) {

                $tongUngHo = GiaoDichQuy::where('chien_dich_gay_quy_id', $campaign->id)
                    ->where('loai_giao_dich', 'UNG_HO')
                    ->sum('so_tien');

                $tongRut = GiaoDichQuy::where('chien_dich_gay_quy_id', $campaign->id)
                    ->where('loai_giao_dich', 'RUT')
                    ->where('trang_thai', 'DA_DUYET')
                    ->sum('so_tien');

                $campaign->so_tien_co_the_rut = $tongUngHo - $tongRut;

                return $campaign;
            });

        return response()->json($campaigns);
    }

    // =====================================================
    // ADMIN
    // =====================================================

    // GET /admin/withdraw-requests
    public function adminIndex(Request $request)
    {
        $query = DB::table('giao_dich_quy as gd')
            ->join('chien_dich_gay_quy as cd', 'gd.chien_dich_gay_quy_id', '=', 'cd.id')
            ->join('to_chuc as tc', 'cd.to_chuc_id', '=', 'tc.id')
            ->where('gd.loai_giao_dich', 'RUT');

        if ($request->trang_thai) {
            $query->where('gd.trang_thai', $request->trang_thai);
        }

        $data = $query->select(
            'gd.*',
            'cd.ten_chien_dich',
            'tc.ten_to_chuc'
        )
        ->latest('gd.id')
        ->get();

        // Đếm theo từng trạng thái cho tab badges
        $counts = DB::table('giao_dich_quy')
            ->where('loai_giao_dich', 'RUT')
            ->selectRaw("
                SUM(CASE WHEN trang_thai = 'CHO_DUYET' THEN 1 ELSE 0 END) as cho_duyet,
                SUM(CASE WHEN trang_thai = 'DA_DUYET'  THEN 1 ELSE 0 END) as da_duyet,
                SUM(CASE WHEN trang_thai = 'TU_CHOI'   THEN 1 ELSE 0 END) as tu_choi
            ")
            ->first();

        return response()->json([
            'data' => $data,
            'counts' => $counts
        ]);
    }

    // PUT /admin/withdraw-requests/{id}/confirm
    public function confirm(Request $request, $id)
    {
        $request->validate([
            'ma_giao_dich_ngan_hang' => 'required|string|max:255',
            'ngay_giao_dich' => 'required|date',
            'ghi_chu_admin' => 'nullable|string'
        ]);

        $giaoDich = GiaoDichQuy::where('id', $id)
            ->where('loai_giao_dich', 'RUT')
            ->firstOrFail();

        $giaoDich->update([
            'trang_thai' => 'DA_DUYET',
            'ma_giao_dich_ngan_hang' => $request->ma_giao_dich_ngan_hang,
            'ngay_giao_dich' => $request->ngay_giao_dich,
            'ghi_chu_admin' => $request->ghi_chu_admin
        ]);

        return response()->json([
            'message' => 'Duyệt yêu cầu rút tiền thành công'
        ]);
    }

    // PUT /admin/withdraw-requests/{id}/reject
    public function reject(Request $request, $id)
    {
        $request->validate([
            'ghi_chu_admin' => 'required|string'
        ]);

        $giaoDich = GiaoDichQuy::where('id', $id)
            ->where('loai_giao_dich', 'RUT')
            ->firstOrFail();

        $giaoDich->update([
            'trang_thai' => 'TU_CHOI',
            'ghi_chu_admin' => $request->ghi_chu_admin
        ]);

        return response()->json([
            'message' => 'Đã từ chối yêu cầu rút tiền'
        ]);
    }
}
