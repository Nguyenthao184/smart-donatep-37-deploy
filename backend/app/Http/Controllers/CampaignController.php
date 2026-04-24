<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ChienDichGayQuy;
use App\Models\ToChuc;
use App\Models\TaiKhoanGayQuy;
use App\Models\DanhMuc;
use App\Models\ChiTieuChienDich;
use App\Models\GiaoDichQuy;
use Illuminate\Support\Str;
use App\Http\Requests\Campaign\StoreCampaignRequest;
use App\Http\Requests\Campaign\UpdateCampaignRequest;
use App\Http\Requests\Expense\StoreExpenseRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Services\ApprovalService;
use App\Notifications\ApprovalNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CampaignController extends Controller
{
    //tạo chiến dịch
    public function store(StoreCampaignRequest $request)
    {
        $user = auth()->user();
        $toChuc = $user->toChuc;

        if (!$toChuc) {
            return response()->json([
                'message' => 'Bạn chưa phải tổ chức'
            ], 403);
        }

        // 2. Check xác minh
        if ($toChuc->trang_thai != 'HOAT_DONG') {
            return response()->json([
                'message' => 'Tổ chức chưa được xác minh'
            ], 400);
        }

        $taiKhoan = $toChuc->taiKhoanGayQuy;

        if (!$taiKhoan) {
            return response()->json([
                'message' => 'Tổ chức chưa có tài khoản gây quỹ'
            ], 400);
        }

        // chưa duyệt
        if ($taiKhoan->trang_thai === 'CHO_DUYET') {
            return response()->json([
                'message' => 'Tài khoản gây quỹ đang chờ duyệt'
            ], 400);
        }

        // bị khóa
        if ($taiKhoan->trang_thai === 'KHOA') {
            return response()->json([
                'message' => 'Tài khoản gây quỹ đã bị khóa'
            ], 400);
        }
        // 3. Geocoding
        if ($request->lat < 8 || $request->lat > 24 ||
            $request->lng < 102 || $request->lng > 110) {
            return response()->json([
                'message' => 'Vị trí không hợp lệ'
            ], 400);
        }
        // 4. Lấy tài khoản gây quỹ (tự động)
        $taiKhoan = $toChuc->taiKhoanGayQuy;

        if (!$taiKhoan) {
            return response()->json([
                'message' => 'Tổ chức chưa có tài khoản gây quỹ'
            ], 400);
        }

        // 5. Upload ảnh
        $images = [];

        foreach ($request->file('hinh_anh') as $file) {
            $path = $file->store('campaigns', 'public');
            $images[] = $path;
        }

        // 6. Tạo mã chuyển tiền UNIQUE
        do {
            $maCK = 'CT' . strtoupper(\Illuminate\Support\Str::random(8));
        } while (ChienDichGayQuy::where('ma_noi_dung_ck', $maCK)->exists());

        // 7. Tạo chiến dịch
        $chienDich = ChienDichGayQuy::create([
            'tai_khoan_gay_quy_id' => $taiKhoan->id,
            'to_chuc_id' => $toChuc->id,
            'danh_muc_id' => $request->danh_muc_id,

            'ten_chien_dich' => $request->ten_chien_dich,
            'mo_ta' => $request->mo_ta,
            'hinh_anh' => json_encode($images),

            'muc_tieu_tien' => $request->muc_tieu_tien,
            'so_tien_da_nhan' => 0,

            'ngay_ket_thuc' => $request->ngay_ket_thuc,
            'vi_tri' => $request->vi_tri,
            'lat' => $request->lat,
            'lng' => $request->lng,
            
            'ma_noi_dung_ck' => $maCK,
            'trang_thai' => 'CHO_XU_LY'
        ]);

        return response()->json([
            'message' => 'Tạo chiến dịch thành công, chờ duyệt',
            'data' => $chienDich
        ]);
    }

    //hiển thị đơn tạo chiến dịch
    public function edit($id)
    {
        $user = auth()->user();
        $toChuc = $user->toChuc;

        if (!$toChuc) {
            return response()->json([
                'message' => 'Bạn chưa phải tổ chức'
            ], 403);
        }

        $chienDich = ChienDichGayQuy::where('id', $id)
            ->where('to_chuc_id', $toChuc->id)
            ->first();

        if (!$chienDich) {
            return response()->json([
                'message' => 'Không tìm thấy chiến dịch'
            ], 404);
        }

        // chỉ cho edit khi CHO_XU_LY
        if ($chienDich->trang_thai !== 'CHO_XU_LY') {
            return response()->json([
                'message' => 'Không thể chỉnh sửa chiến dịch này'
            ], 400);
        }

        $images = is_array($chienDich->hinh_anh)
            ? $chienDich->hinh_anh
            : json_decode($chienDich->hinh_anh, true);

        $images = $images ?? [];

        // normalize về PATH (rất quan trọng)
        $images = array_map(function ($img) {
            // nếu đã là URL thì giữ nguyên
            if (str_starts_with($img, 'http')) {
                return $img;
            }

            // nếu là path thì convert sang URL
            return asset('storage/' . ltrim($img, '/'));

        }, $images);

        return response()->json([
            'id' => $chienDich->id,
            'ten_chien_dich' => $chienDich->ten_chien_dich,
            'mo_ta' => $chienDich->mo_ta,
            'danh_muc_id' => $chienDich->danh_muc_id,

            'muc_tieu_tien' => $chienDich->muc_tieu_tien,
            'ngay_ket_thuc' => $chienDich->ngay_ket_thuc, 

            'vi_tri' => $chienDich->vi_tri,
            'lat' => $chienDich->lat,
            'lng' => $chienDich->lng,

            // QUAN TRỌNG: giữ raw path
            'hinh_anh' => $images,
        ]);
    }

    //cập nhật chiến dịch
    public function update(UpdateCampaignRequest $request, $id)
    {
        $user = auth()->user();
        $toChuc = $user->toChuc;

        if (!$toChuc) {
            return response()->json([
                'message' => 'Bạn chưa phải tổ chức'
            ], 403);
        }

        $chienDich = ChienDichGayQuy::where('id', $id)
            ->where('to_chuc_id', $toChuc->id)
            ->first();

        if (!$chienDich) {
            return response()->json([
                'message' => 'Không tìm thấy chiến dịch'
            ], 404);
        }

        // chỉ cho sửa khi CHO_XU_LY
        if ($chienDich->trang_thai !== 'CHO_XU_LY') {
            return response()->json([
                'message' => 'Bạn chỉ được chỉnh sửa chiến dịch khi đang chờ duyệt'
            ], 400);
        }

        // Check vị trí
        if ($request->lat < 8 || $request->lat > 24 ||
            $request->lng < 102 || $request->lng > 110) {
            return response()->json([
                'message' => 'Vị trí không hợp lệ'
            ], 400);
        }

        $anh_hien_tai = is_array($chienDich->hinh_anh)
            ? $chienDich->hinh_anh
            : json_decode($chienDich->hinh_anh, true);

        $anh_hien_tai = $anh_hien_tai ?? [];

        $anh_cu = $request->anh_cu ?? [];

        $anh_cu = array_map(function ($img) {
            // nếu là URL → chuyển về path
            if (str_starts_with($img, asset('storage/'))) {
                return str_replace(asset('storage/'), '', $img);
            }

            return $img; 
        }, $anh_cu);

        $xoa_anh = $request->xoa_anh ?? [];

        foreach ($xoa_anh as $img) {

            // convert URL -> path
            if (str_starts_with($img, asset('storage/'))) {
                $path = str_replace(asset('storage/'), '', $img);
            } else {
                $path = $img;
            }

            Storage::disk('public')->delete($path);
        }

        $anh_moi = [];

        if ($request->hasFile('anh_moi')) {
            foreach ($request->file('anh_moi') as $file) {
                $anh_moi[] = $file->store('campaigns', 'public'); // lưu path
            }
        }

        $finalImages = array_values(array_merge($anh_cu, $anh_moi));
        
        $chienDich->update([
            'danh_muc_id' => $request->danh_muc_id,
            'ten_chien_dich' => $request->ten_chien_dich,
            'mo_ta' => $request->mo_ta,
            'hinh_anh' => $finalImages,

            'muc_tieu_tien' => $request->muc_tieu_tien,
            'ngay_ket_thuc' => $request->ngay_ket_thuc,

            'vi_tri' => $request->vi_tri,
            'lat' => $request->lat,
            'lng' => $request->lng,
        ]);

        $images = array_map(function ($img) {
            if (str_starts_with($img, 'http')) {
                return $img;
            }

            return asset('storage/' . ltrim($img, '/'));
        }, $finalImages);
        $chienDich->hinh_anh = $images;

        return response()->json([
            'message' => 'Cập nhật chiến dịch thành công',
            'data' => [
                'id' => $chienDich->id,
                'ten_chien_dich' => $chienDich->ten_chien_dich,
                'mo_ta' => $chienDich->mo_ta,
                'danh_muc_id' => $chienDich->danh_muc_id,

                'muc_tieu_tien' => $chienDich->muc_tieu_tien,
                'ngay_ket_thuc' => $chienDich->ngay_ket_thuc,

                'vi_tri' => $chienDich->vi_tri,
                'lat' => $chienDich->lat,
                'lng' => $chienDich->lng,

                'hinh_anh' => $images
            ]
        ]);
    }

    //danh sách chiến dịch
    public function index(Request $request)
    {
        $query = ChienDichGayQuy::with(['toChuc', 'danhMuc'])
            ->withCount('ungHos')
            ->whereIn('trang_thai', ['HOAT_DONG', 'HOAN_THANH', 'TAM_DUNG', 'DA_KET_THUC']);

        if ($request->has(['min_lat', 'max_lat', 'min_lng', 'max_lng'])) {
            $query->whereBetween('lat', [$request->min_lat, $request->max_lat])
                ->whereBetween('lng', [$request->min_lng, $request->max_lng]);
        }

        if ($request->keyword) {
            $query->where('ten_chien_dich', 'like', '%' . $request->keyword . '%');
        }

        if ($request->danh_muc_id) {
            $query->where('danh_muc_id', $request->danh_muc_id);
        }

        if ($request->trang_thai) {
            $query->where('trang_thai', $request->trang_thai);
        }

        $query->orderByRaw("
            CASE 
                WHEN trang_thai = 'HOAT_DONG' THEN 1
                WHEN trang_thai = 'HOAN_THANH' THEN 2
                WHEN trang_thai = 'TAM_DUNG' THEN 3
                WHEN trang_thai = 'DA_KET_THUC' THEN 4
                ELSE 5
            END
        ");

        $campaigns = $query->paginate(8);

        // format lại dữ liệu cho FE
        $campaigns->getCollection()->transform(fn($item) 
            => $this->formatCampaign($item));

        return response()->json($campaigns);
    }

    //danh sách chiến dịch của tôi
    public function myCampaigns(Request $request)
    {
        $user = auth()->user();
        $toChuc = $user->toChuc;

        if (!$toChuc) {
            return response()->json([
                'message' => 'Bạn không phải tổ chức'
            ], 403);
        }

        $query = ChienDichGayQuy::where('to_chuc_id', $toChuc->id);

        // tìm kiếm 
        if ($request->keyword) {
            $query->where('ten_chien_dich', 'like', '%' . $request->keyword . '%');
        }

        if ($request->danh_muc_id) {
            $query->where('danh_muc_id', $request->danh_muc_id);
        }

        //  sort 
        if ($request->trang_thai) {
            $query->where('trang_thai', $request->trang_thai);
        }

        $query->orderByRaw("
            CASE 
                WHEN trang_thai = 'CHO_XU_LY' THEN 1
                WHEN trang_thai = 'HOAT_DONG' THEN 2
                WHEN trang_thai = 'TAM_DUNG' THEN 3
                WHEN trang_thai = 'HOAN_THANH' THEN 4
                WHEN trang_thai = 'DA_KET_THUC' THEN 5
                ELSE 6
            END
        ");

        $campaigns = $query->paginate(8);

        $campaigns->getCollection()->transform(fn($item) 
            => $this->formatCampaign($item));

        return response()->json($campaigns);
    }

    //chi tiết chiến dịch
    public function show($id)
    {
        $chienDich = ChienDichGayQuy::with(['toChuc', 'danhMuc'])
            ->find($id);

        if (!$chienDich) {
            return response()->json([
                'message' => 'Không tìm thấy chiến dịch'
            ], 404);
        }

        $phanTram = $chienDich->muc_tieu_tien > 0
            ? round(($chienDich->so_tien_da_nhan / $chienDich->muc_tieu_tien) * 100)
            : 0;

        $ngayConLai = max(0, floor(
            now()->diffInDays($chienDich->ngay_ket_thuc, false)
        ));

        $images = $chienDich->hinh_anh;
        if (is_string($images)) {
            $images = json_decode($images, true);
        }
        $images = $images ?? [];

        $pageSize = 6;

        $donations = DB::table('ung_ho as uh')
            ->leftJoin('nguoi_dung as nd', 'uh.nguoi_dung_id', '=', 'nd.id')
            ->select(
                'uh.so_tien',
                'uh.created_at',
                'nd.ho_ten'
            )
            ->where('uh.chien_dich_gay_quy_id', $chienDich->id)
            ->orderByDesc('uh.created_at')
            ->paginate($pageSize);

        $donationsFormatted = collect($donations->items())->map(function ($item) {
            return [
                'ten_nguoi_ung_ho' => $item->ho_ten ?? 'Người ủng hộ ẩn danh',
                'so_tien' => number_format($item->so_tien, 0, ',', '.') . 'đ',
                'thoi_gian' => \Carbon\Carbon::parse($item->created_at)->format('d/m/Y H:i')
            ];
        });
        
        $soLuotUngHo = DB::table('ung_ho')
            ->where('chien_dich_gay_quy_id', $chienDich->id)
            ->count();

        $expensesGrouped = $chienDich->chiTieus
            ->groupBy('giao_dich_quy_id')
            ->map(function ($items, $giaoDichId) {

                $giaoDich = $items->first()->giaoDich;

                return [
                    'giao_dich_id' => $giaoDichId,
                    'tong_tien_dot' => (float) optional($giaoDich)->so_tien,

                    'chi_tieu' => $items->map(function ($item) {
                        return [
                            'ten' => $item->ten_hoat_dong,
                            'mo_ta' => $item->mo_ta,
                            'so_tien' => (float) $item->so_tien
                        ];
                    })->values()
                ];
            })->values();

        return response()->json([
            'id' => $chienDich->id,
            'ten_chien_dich' => $chienDich->ten_chien_dich,
            'mo_ta' => $chienDich->mo_ta,
            'ten_danh_muc' => $chienDich->danhMuc->ten_danh_muc ?? null,
            'trang_thai' => $chienDich->trang_thai,


            'hinh_anh' => $images,

            'so_tien_da_nhan' => $chienDich->so_tien_da_nhan,
            'muc_tieu_tien' => $chienDich->muc_tieu_tien,
            'phan_tram' => $phanTram,
            'ma_noi_dung_ck' => $chienDich->ma_noi_dung_ck,

            'ngay_bat_dau' => optional($chienDich->created_at)->format('d/m/Y'),
            'ngay_ket_thuc' => \Carbon\Carbon::parse($chienDich->ngay_ket_thuc)->format('d/m/Y'),
            'so_ngay_con_lai' => $ngayConLai,

            'vi_tri' => $chienDich->vi_tri,
            'lat' => $chienDich->lat,
            'lng' => $chienDich->lng,

            'to_chuc' => [
                'id' => $chienDich->toChuc->id ?? null,
                'ten_to_chuc' => $chienDich->toChuc->ten_to_chuc ?? null,
                'logo' => $chienDich->toChuc->logo ? asset('storage/' . $chienDich->toChuc->logo) : null,
                'mo_ta' => $chienDich->toChuc->mo_ta ?? null,
                'dia_chi' => $chienDich->toChuc->dia_chi ?? null,
                'email' => $chienDich->toChuc->email ?? null,
                'so_dien_thoai' => $chienDich->toChuc->so_dien_thoai ?? null,
            ],
            'danh_sach_ung_ho' => $donations,
            'so_luot_ung_ho' => $soLuotUngHo,
            
            'chi_tieu_theo_dot' => $expensesGrouped
        ]);
    }

    //duyệt chiến dịch
    public function approveCampaign($id, ApprovalService $service)
    {
        $campaign = ChienDichGayQuy::findOrFail($id);

        $service->approve($campaign);

        $user = $campaign->toChuc->user;

        $user->notify(new ApprovalNotification(
            'approve',
            'Chiến dịch',
            null,
            'campaign',
            (int) $campaign->id
        ));

        return response()->json([
            'message' => 'Đã duyệt chiến dịch'
        ]);
    }

    //từ chối chiến dịch
    public function rejectCampaign(Request $request, $id, ApprovalService $service)
    {
        $campaign = ChienDichGayQuy::findOrFail($id);

        $service->reject($campaign, $request->ly_do);

        $user = $campaign->toChuc->user;

        $user->notify(new ApprovalNotification(
            'reject',
            'Chiến dịch',
            $request->ly_do,
            'campaign',
            (int) $campaign->id
        ));

        return response()->json([
            'message' => 'Đã từ chối chiến dịch'
        ]);
    }

    //chiến dịch nổi bật
    public function featured()
    {
        $campaigns = ChienDichGayQuy::withCount('ungHos')
            ->where('trang_thai', 'HOAT_DONG')
            ->whereColumn('so_tien_da_nhan', '<', 'muc_tieu_tien') // chưa đạt target
            ->orderByRaw('(so_tien_da_nhan / NULLIF(muc_tieu_tien, 0)) DESC')
            ->orderByDesc('ung_hos_count')
            ->limit(10)
            ->get();

        $campaigns->transform(fn($item) => $this->formatCampaign($item));

        return response()->json($campaigns);
    }

    private function formatCampaign($item)
    {
        $soTien = $item->so_tien_da_nhan;
        $mucTieu = $item->muc_tieu_tien;

        $phanTram = $mucTieu > 0 
            ? round(($soTien / $mucTieu) * 100) 
            : 0;

        $ngayConLai = max(0, floor(now()->diffInDays($item->ngay_ket_thuc, false)));

        $images = $item->hinh_anh;
        if (is_string($images)) {
            $images = json_decode($images, true);
        }
        $images = $images ?? [];

        $soTienConThieu = 0;
        if (
            $item->trang_thai === 'HOAT_DONG' &&
            $soTien < $mucTieu
        ) {
            $soTienConThieu = $mucTieu - $soTien;
        }
        return [
            'id' => $item->id,
            'ten_chien_dich' => $item->ten_chien_dich,
            'hinh_anh' => $image ? asset('storage/' . $image) : null,
            'so_tien_da_nhan' => $soTien,
            'muc_tieu_tien' => $mucTieu,
            'phan_tram' => $phanTram,
            'so_ngay_con_lai' => $ngayConLai,
            'trang_thai' => $item->trang_thai,
            'so_tien_con_thieu' => $soTienConThieu,
            'so_luot_ung_ho' => $item->ung_hos_count ?? 0,
        ];
    }

    public function getDanhMuc()
    {
        $danhMucs = DB::table('danh_muc')
            ->select('id', 'ten_danh_muc', 'hinh_anh')
            ->get()
            ->map(function ($item) {
                $item->hinh_anh = asset('storage/' . $item->hinh_anh);
                return $item;
            });

        return response()->json($danhMucs);
    }

    public function endingSoon()
    {
        $campaigns = ChienDichGayQuy::with(['toChuc', 'danhMuc'])
            ->where('trang_thai', 'HOAT_DONG') // chỉ lấy đang hoạt động
            ->whereDate('ngay_ket_thuc', '>', now()) // chưa hết hạn
            ->orderBy('ngay_ket_thuc', 'asc') // sắp hết hạn lên đầu
            ->limit(5)
            ->get();

        // format lại cho FE
        $campaigns = $campaigns->map(fn($item) => $this->formatCampaign($item));

        return response()->json($campaigns);
    }

    public function map(Request $request)
    {
        $query = ChienDichGayQuy::whereNotNull('lat')
            ->whereNotNull('lng')
            ->where('trang_thai', 'HOAT_DONG');

        // optional: filter theo vùng map
        if ($request->has(['min_lat', 'max_lat', 'min_lng', 'max_lng'])) {
            $query->whereBetween('lat', [$request->min_lat, $request->max_lat])
                ->whereBetween('lng', [$request->min_lng, $request->max_lng]);
        }

        return response()->json(
            $query->get(['id', 'lat', 'lng'])
        );
    }

    //tạo hoạt động cho giao dịch rút
    public function storeExpense(StoreExpenseRequest $request, $campaignId)
    {
        $user = auth()->user();
        $toChuc = $user->toChuc;

        if (!$toChuc) {
            return response()->json([
                'message' => 'Bạn chưa phải tổ chức'
            ], 403);
        }

        $chienDich = ChienDichGayQuy::where('id', $campaignId)
            ->where('to_chuc_id', $toChuc->id)
            ->firstOrFail();

        $giaoDich = GiaoDichQuy::where('id', $request->giao_dich_quy_id)
            ->where('chien_dich_gay_quy_id', $chienDich->id)
            ->where('loai_giao_dich', 'RUT')
            ->first();

        if (!$giaoDich) {
            return response()->json([
                'message' => 'Giao dịch rút không hợp lệ'
            ], 400);
        }

        DB::beginTransaction();

        try {

            $tongNhap = collect($request->chi_tiet)->sum('so_tien');

            if ($tongNhap != $giaoDich->so_tien) {
                return response()->json([
                    'message' => 'Tổng chi phải bằng số tiền đã rút'
                ], 400);
            }

            $data = [];

            foreach ($request->chi_tiet as $item) {
                $data[] = ChiTieuChienDich::create([
                    'chien_dich_gay_quy_id' => $chienDich->id,
                    'giao_dich_quy_id' => $giaoDich->id,
                    'ten_hoat_dong' => $item['ten_hoat_dong'],
                    'so_tien' => $item['so_tien'],
                    'mo_ta' => $request->mo_ta
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Khai báo chi tiêu thành công',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Có lỗi xảy ra'
            ], 500);
        }
    }

    //danh sách giải ngân của tổ chức đối với mỗi chiến dịch
    public function getWithdrawTransactions($campaignId)
    {
        $chienDich = ChienDichGayQuy::find($campaignId);

        if (!$chienDich) {
            return response()->json([
                'message' => 'Không tìm thấy chiến dịch'
            ], 404);
        }

        $data = DB::table('giao_dich_quy as gd')
            ->leftJoin('chi_tieu_chien_dich as ct', 'gd.id', '=', 'ct.giao_dich_quy_id')
            ->where('gd.chien_dich_gay_quy_id', $campaignId)
            ->where('gd.loai_giao_dich', 'RUT')

            ->select(
                'gd.id',
                'gd.so_tien',
                'gd.created_at',
                'gd.mo_ta'
            )
            ->orderByDesc('gd.created_at')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'so_tien' => (float) $item->so_tien,
                    'thoi_gian' => \Carbon\Carbon::parse($item->created_at)->format('d/m/Y H:i'),
                    'mo_ta' => $item->mo_ta
                ];
            });

        return response()->json([
            'data' => $data
        ]);
    }
}
