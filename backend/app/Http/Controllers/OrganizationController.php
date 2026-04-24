<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\XacMinhToChuc;
use App\Models\TaiKhoanGayQuy;
use App\Models\ToChuc;
use App\Http\Requests\Organization\OrganizationRegisterRequest;
use Illuminate\Support\Facades\DB;
use App\Notifications\ApprovalNotification;
use App\Services\ApprovalService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use App\Models\ChienDichGayQuy;

class OrganizationController extends Controller
{
    // USER đăng ký tổ chức
    public function register(OrganizationRegisterRequest $request)
    {
        DB::beginTransaction();

        try {
            // upload file
            $file = $request->file('giay_phep');
            if (!$file) {
                return response()->json([
                    'error' => 'Không nhận được file'
                ], 400);
            }
            $path = $file->store('giay_phep', 'public');

            // upload logo
            $logoPath = null;
            if ($request->hasFile('logo')) {
                $logoPath = $request->file('logo')->store('logos', 'public');
            }

            $org = XacMinhToChuc::create([
                'nguoi_dung_id' => auth()->id(),
                'ten_to_chuc' => $request->ten_to_chuc,
                'ma_so_thue' => $request->ma_so_thue,
                'nguoi_dai_dien' => $request->nguoi_dai_dien,
                'giay_phep' => $path,
                'loai_hinh' => $request->loai_hinh,
                'mo_ta' => $request->mo_ta,
                'dia_chi' => $request->dia_chi,
                'so_dien_thoai' => $request->so_dien_thoai,
                'logo' => $logoPath,
            ]);

            $org->giay_phep = $org->giay_phep 
                ? asset('storage/' . $org->giay_phep) 
                : null;

            $org->logo = $org->logo 
                ? asset('storage/' . $org->logo) 
                : null;

            DB::commit();

            return response()->json([
                'message' => 'Đăng ký thành công, vui lòng chờ admin duyệt',
                'data' => $org
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // USER xem trạng thái
    public function status()
    {
        return XacMinhToChuc::where('nguoi_dung_id', auth()->id())->latest()->first();
    }

    // ADMIN duyệt tổ chức và tạo tài khoản gây quỹ
    public function approve($id)
    {
        DB::beginTransaction();

        try {
            $org = XacMinhToChuc::with('user')->findOrFail($id);

            // nếu đã xử lý rồi thì không duyệt lại
            if ($org->trang_thai !== 'CHO_XU_LY') {
                return response()->json([
                    'message' => 'Yêu cầu đã được xử lý trước đó'
                ], 400);
            }

            // duyệt tổ chức
            $org->update([
                'trang_thai' => 'CHAP_NHAN',
                'duyet_boi' => auth()->id(),
                'duyet_luc' => now()
            ]);

            // nâng quyền user thành tổ chức
            DB::table('nguoi_dung_vai_tro')->updateOrInsert([
                'nguoi_dung_id' => $org->nguoi_dung_id,
                'vai_tro_id' => 3, 
            ]);

            $toChuc = ToChuc::updateOrCreate(
                ['nguoi_dung_id' => $org->nguoi_dung_id],
                [
                    'xac_minh_to_chuc_id' => $org->id,
                    'ten_to_chuc' => $org->ten_to_chuc,
                    'email' => $org->user->email,
                    'trang_thai' => 'HOAT_DONG',
                    'mo_ta' => $org->mo_ta,
                    'dia_chi' => $org->dia_chi,
                    'so_dien_thoai' => $org->so_dien_thoai,
                    'logo' => $org->logo,
                ]
            );

            $exists = TaiKhoanGayQuy::where('to_chuc_id', $toChuc->id)
                ->whereIn('trang_thai', ['CHO_DUYET', 'HOAT_DONG'])
                ->exists();

            if (!$exists) {

                // fake MB account
                $mb = $this->fakeMBBank($toChuc->ten_to_chuc, $org->user);

                // nội dung QR
                $qrContent = json_encode([
                    'bank' => $mb['ngan_hang'],
                    'account' => $mb['so_tai_khoan'],
                    'name' => $mb['chu_tai_khoan']
                ]);

                // tạo QR
                $result = Builder::create()
                    ->writer(new PngWriter())
                    ->data($qrContent)
                    ->size(300)
                    ->margin(10)
                    ->build();

                $fileName = 'qr_' . time() . '_' . Str::random(5) . '.png';

                Storage::disk('public')->put($fileName, $result->getString());

                // tạo tài khoản gây quỹ
                TaiKhoanGayQuy::create([
                    'to_chuc_id' => $toChuc->id,
                    'ten_quy' => "Quỹ {$toChuc->ten_to_chuc}",
                    'ngan_hang' => $mb['ngan_hang'],
                    'so_tai_khoan' => $mb['so_tai_khoan'],
                    'chu_tai_khoan' => $mb['chu_tai_khoan'],
                    'ma_yeu_cau_mb' => $mb['request_id'],
                    'so_du' => 0,
                    'trang_thai' => 'HOAT_DONG',
                    'qr_code' => 'storage/' . $fileName
                ]);
            }

            DB::commit();

            // notification
            $org->user->notify(
                new ApprovalNotification(
                    'approve',
                    'Tổ chức & tài khoản gây quỹ',
                    null,
                    'organization',
                    (int) $org->id
                )
            );

            return response()->json([
                'message' => 'Duyệt tổ chức thành công và đã tạo tài khoản gây quỹ'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ADMIN từ chối tổ chức
    public function reject(Request $request, $id)
    {
        $request->validate([
            'ly_do' => 'required|string|max:255'
        ]);

        $org = XacMinhToChuc::findOrFail($id);

        // nếu đã xử lý rồi thì không reject lại
        if ($org->trang_thai !== 'CHO_XU_LY') {
            return response()->json([
                'message' => 'Yêu cầu đã được xử lý trước đó'
            ], 400);
        }

        $org->update([
            'trang_thai' => 'TU_CHOI',
            'duyet_boi' => auth()->id(),
            'duyet_luc' => now()
        ]);

        $user = $org->user;
        $user->notify(
            new ApprovalNotification(
                'reject',
                'Tổ chức',
                $request->ly_do,
                'organization',
                (int) $org->id
            )
        );

        return response()->json([
            'message' => 'Đã từ chối',
            'ly_do' => $request->ly_do
        ]);
    }

    //danh sách tổ chức admin
    public function adminIndex(Request $request)
    {
        $query = XacMinhToChuc::with('user');

        if ($request->trang_thai) {
            $query->where('trang_thai', $request->trang_thai);
        }

        return $query->latest()->get();
    }

    // Danh sách tổ chức
    public function index(Request $request)
    {
        $query = ToChuc::query()
            ->with('taiKhoanGayQuy')
            ->leftJoin('xac_minh_to_chuc', 'to_chuc.xac_minh_to_chuc_id', '=', 'xac_minh_to_chuc.id')
            ->leftJoin('chien_dich_gay_quy', 'to_chuc.id', '=', 'chien_dich_gay_quy.to_chuc_id')
            ->select(
                'to_chuc.id',
                'to_chuc.ten_to_chuc',
                'to_chuc.logo',
                'to_chuc.dia_chi',
                'xac_minh_to_chuc.loai_hinh',
                'to_chuc.created_at',
                DB::raw('COALESCE(SUM(chien_dich_gay_quy.so_tien_da_nhan), 0) as tong_gay_quy')
            )
            ->groupBy(
                'to_chuc.id',
                'to_chuc.ten_to_chuc',
                'to_chuc.logo',
                'to_chuc.dia_chi',
                'xac_minh_to_chuc.loai_hinh',
                'to_chuc.created_at'
            );

        if ($request->keyword) {
            $query->where('to_chuc.ten_to_chuc', 'like', '%' . $request->keyword . '%');
        }

        if ($request->loai_hinh) {
            $query->where('xac_minh_to_chuc.loai_hinh', $request->loai_hinh);
        }

        // TOP theo tổng gây quỹ
        $query->orderByDesc('tong_gay_quy')
            ->orderByDesc('to_chuc.created_at');

        $orgs = $query->paginate(8);

        // Tổng tổ chức
        $totalAll = ToChuc::count();

        $totalByType = DB::table('to_chuc')
            ->join('xac_minh_to_chuc', 'to_chuc.xac_minh_to_chuc_id', '=', 'xac_minh_to_chuc.id')
            ->select(
                'xac_minh_to_chuc.loai_hinh',
                DB::raw('COUNT(DISTINCT to_chuc.id) as total')
            )
            ->groupBy('xac_minh_to_chuc.loai_hinh')
            ->get();

        $orgs->through(function ($org) {
            return [
                'id' => $org->id,
                'ten_to_chuc' => $org->ten_to_chuc,
                'logo' => $org->logo ? asset('storage/' . $org->logo) : null,
                'dia_chi' => $org->dia_chi,
                'tong_gay_quy' => (float) $org->tong_gay_quy,
                'so_tai_khoan' => optional($org->taiKhoanGayQuy)->so_tai_khoan,
                'tham_gia' => optional($org->created_at)->format('m/Y'),
            ];
        });

        return response()->json([
            'data' => $orgs,
            'tong_to_chuc' => $totalAll,
            'theo_loai' => $totalByType
        ]);
    }

    // Thông tin tổ chức + chiến dịch
    public function show($id)
    {
        // Thông tin tổ chức + tài khoản
        $org = ToChuc::with('taiKhoanGayQuy')->findOrFail($id);

        // Danh sách chiến dịch
        $chienDichs = DB::table('chien_dich_gay_quy')
            ->where('to_chuc_id', $id)
            ->latest()
            ->get()
            ->map(function ($cd) {

                // ảnh
                $hinhAnh = null;
                if ($cd->hinh_anh) {
                    $arr = json_decode($cd->hinh_anh, true);
                    $hinhAnh = isset($arr[0]) ? asset('storage/' . $arr[0]) : null;
                }

                // % hoàn thành
                $phanTram = $cd->muc_tieu_tien > 0
                    ? round(($cd->so_tien_da_nhan / $cd->muc_tieu_tien) * 100)
                    : 0;

                // số ngày còn lại
                $soNgayConLai = null;
                if ($cd->trang_thai === 'HOAT_DONG') {
                    $soNgayConLai = (int) max(
                        0,
                        now()->diffInDays($cd->ngay_ket_thuc, false)
                    );
                }

                // số lượt ủng hộ
                $soLuotUngHo = DB::table('ung_ho')
                    ->where('chien_dich_gay_quy_id', $cd->id)
                    ->count();

                return [
                    'id' => $cd->id,
                    'hinh_anh' => $hinhAnh,
                    'ten_chien_dich' => $cd->ten_chien_dich,

                    'so_tien_da_nhan' => (float) $cd->so_tien_da_nhan,
                    'muc_tieu_tien' => (float) $cd->muc_tieu_tien,

                    'phan_tram' => $phanTram,
                    'trang_thai' => $cd->trang_thai,
                    'so_ngay_con_lai' => $soNgayConLai,
                    'so_luot_ung_ho' => $soLuotUngHo,
                ];
            });

        // Tổng thu
        $tongThu = DB::table('chien_dich_gay_quy')
            ->where('to_chuc_id', $id)
            ->sum('so_tien_da_nhan');

        // Tổng chi (từ giao_dich_quy)
        $tongChi = DB::table('giao_dich_quy')
            ->join('tai_khoan_gay_quy', 'giao_dich_quy.tai_khoan_gay_quy_id', '=', 'tai_khoan_gay_quy.id')
            ->where('tai_khoan_gay_quy.to_chuc_id', $id)
            ->where('loai_giao_dich', 'RUT')
            ->sum('so_tien');

        // Tổng chiến dịch
        $tongChienDich = DB::table('chien_dich_gay_quy')
            ->where('to_chuc_id', $id)
            ->count();

        // Tổng lượt ủng hộ (cho box bên phải)
        $tongLuotUngHo = DB::table('ung_ho')
            ->join('chien_dich_gay_quy', 'ung_ho.chien_dich_gay_quy_id', '=', 'chien_dich_gay_quy.id')
            ->where('chien_dich_gay_quy.to_chuc_id', $id)
            ->count();

        $tk = $org->taiKhoanGayQuy;

        $expenseSummary = DB::table('chi_tieu_chien_dich')
            ->join('chien_dich_gay_quy', 'chi_tieu_chien_dich.chien_dich_gay_quy_id', '=', 'chien_dich_gay_quy.id')
            ->where('chien_dich_gay_quy.to_chuc_id', $id)
            ->select(
                'ten_hoat_dong',
                DB::raw('SUM(so_tien) as tong_tien')
            )
            ->groupBy('ten_hoat_dong')
            ->orderByDesc('tong_tien')
            ->get();

        return response()->json([
            // thông tin tổ chức
            'id' => $org->id,
            'ten_to_chuc' => $org->ten_to_chuc,
            'logo' => $org->logo ? asset('storage/' . $org->logo) : null,
            'mo_ta' => $org->mo_ta,
            'dia_chi' => $org->dia_chi,
            'so_dien_thoai' => $org->so_dien_thoai,
            'email' => $org->email,

            // danh sách chiến dịch
            'chien_dichs' => $chienDichs,

            // tài khoản
            'ten_tai_khoan' => optional($tk)->chu_tai_khoan,
            'so_tai_khoan' => optional($tk)->so_tai_khoan,
            'so_du_hien_tai' => (float) optional($tk)->so_du ?? 0,
            'qr_code' => optional($tk)->qr_code 
                ? asset('storage/' . $tk->qr_code) 
                : null,

            // thống kê (match UI)
            'tong_thu' => (float) $tongThu,
            'tong_chi' => (float) $tongChi,
            'tong_chien_dich' => $tongChienDich,
            'tong_luot_ung_ho' => $tongLuotUngHo,

            'expense_summary' => $expenseSummary,
        ]);
    }

    // Admin khóa tài khoản gây quỹ của tổ chức (và tạm dừng chiến dịch)
    public function lock(Request $request, $id, ApprovalService $service)
    {
        $request->validate([
            'ly_do' => 'required|string|max:255'
        ]);

        $tk = TaiKhoanGayQuy::findOrFail($id);

        if ($tk->trang_thai === 'KHOA') {
            return response()->json([
                'message' => 'Tài khoản đã bị khóa trước đó'
            ], 400);
        }

        $service->lock($tk, 'KHOA');

        ChienDichGayQuy::where('to_chuc_id', $tk->to_chuc_id)
            ->where('trang_thai', 'HOAT_DONG')
            ->update([
                'trang_thai' => 'TAM_DUNG'
            ]);

        // gửi notification
        $user = $tk->toChuc->user;

        $user->notify(
            new ApprovalNotification(
                'lock',
                'Tài khoản gây quỹ',
                $request->ly_do,
                'fund_account',
                (int) $tk->id
            )
        );

        return response()->json([
            'message' => 'Đã khóa tài khoản',
            'ly_do' => $request->ly_do
        ]);
    }

    // FAKE MBBANK SERVICE
    private function fakeMBBank($tenQuy, $user)
    {
        return [
            'so_tai_khoan' => rand(1000000000,9999999999),
            'chu_tai_khoan' => strtoupper($this->removeVietnameseAccents($tenQuy)),
            'ngan_hang' => 'MB Bank',
            'request_id' => 'MB_'.rand(1000,9999)
        ];
    }

    private function removeVietnameseAccents($str) {
        $str = mb_strtolower($str, 'UTF-8');

        $accents = [
            'a' => ['à','á','ạ','ả','ã','â','ầ','ấ','ậ','ẩ','ẫ','ă','ằ','ắ','ặ','ẳ','ẵ'],
            'e' => ['è','é','ẹ','ẻ','ẽ','ê','ề','ế','ệ','ể','ễ'],
            'i' => ['ì','í','ị','ỉ','ĩ'],
            'o' => ['ò','ó','ọ','ỏ','õ','ô','ồ','ố','ộ','ổ','ỗ','ơ','ờ','ớ','ợ','ở','ỡ'],
            'u' => ['ù','ú','ụ','ủ','ũ','ư','ừ','ứ','ự','ử','ữ'],
            'y' => ['ỳ','ý','ỵ','ỷ','ỹ'],
            'd' => ['đ']
        ];

        foreach ($accents as $nonAccent => $accentChars) {
            foreach ($accentChars as $accent) {
                $str = str_replace($accent, $nonAccent, $str);
            }
        }

        return $str;
    }
}
