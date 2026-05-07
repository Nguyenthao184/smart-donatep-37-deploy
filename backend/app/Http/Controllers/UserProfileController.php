<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\UpdateProfileRequest;
use App\Http\Requests\User\ChangePasswordRequest;
use App\Http\Requests\User\UpdateDiaChiRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Services\GeocodingService;
use App\Models\ToChuc;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\XacMinhToChuc;
use App\Models\BaiDang;

class UserProfileController extends Controller
{
    //lấy ttcn
    public function getProfile()
    {
        $user = Auth::user();

        // load quan hệ
        $user->load(['toChuc.taiKhoanGayQuy']);

        $tongTienUngHo = DB::table('ung_ho')
            ->where('nguoi_dung_id', $user->id)
            ->where('trang_thai', 'THANH_CONG')
            ->sum('so_tien');
        
        $user = $user->fresh()->load('toChuc');

        $user->anh_dai_dien = $user->anh_dai_dien
            ? $this->resolveMediaUrl($user->anh_dai_dien)
            : null;

        if ($user->toChuc) {
            $user->toChuc->logo = $user->toChuc->logo
                ? $this->resolveMediaUrl($user->toChuc->logo)
                : null;
        }

        $taiKhoan = optional($user->toChuc)->taiKhoanGayQuy;
        if ($taiKhoan && $taiKhoan->qr_code) {
            $taiKhoan->qr_code = $this->resolveMediaUrl($taiKhoan->qr_code);
        }

        return response()->json([
            'user' => $user,
            'tong_tien_ung_ho' => $tongTienUngHo,
        ]);
    }

    //cập nhật ttcn
    public function updateProfile(UpdateProfileRequest $request)
    {
        $user = Auth::user();

        // UPDATE USER
        $userData = $request->only([
            'ho_ten',
        ]);

        if ($request->filled('dia_chi_user')) {

            $diaChi = $request->dia_chi_user;
        
            $geo = app(GeocodingService::class);
            $coords = $geo->geocode($diaChi);
        
            $userData['dia_chi'] = $diaChi;
        
            if ($coords) {
                $userData['lat'] = $coords['lat'];
                $userData['lng'] = $coords['lng'];
                $userData['region'] = $geo->makeRegion($coords['lat'], $coords['lng']);
            }
        }

        if ($request->hasFile('anh_dai_dien')) {

            if ($user->anh_dai_dien && Storage::disk('public')->exists($user->anh_dai_dien)) {
                Storage::disk('public')->delete($user->anh_dai_dien);
            }

            $userData['anh_dai_dien'] = $request->file('anh_dai_dien')
                ->store('avatars', 'public');
        }

        if (!empty(array_filter($userData))) {
            $user->update($userData);
        }

        //UPDATE TO_CHUC (nếu có)

        $toChuc = $user->toChuc;

        if ($toChuc) {

            $orgData = $request->only([
                'email',
                'mo_ta',
                'dia_chi',
                'so_dien_thoai'
            ]);

            if ($request->hasFile('logo')) {

                if ($toChuc->logo && Storage::disk('public')->exists($toChuc->logo)) {
                    Storage::disk('public')->delete($toChuc->logo);
                }

                $orgData['logo'] = $request->file('logo')
                    ->store('logos', 'public');
            }

            // chỉ update khi có data
            if (!empty(array_filter($orgData))) {
                $toChuc->update($orgData);
            }
        }

        $user = $user->fresh()->load('toChuc');

        $user->anh_dai_dien = $user->anh_dai_dien
            ? $this->resolveMediaUrl($user->anh_dai_dien)
            : null;

        if ($user->toChuc) {
            $user->toChuc->logo = $user->toChuc->logo
                ? $this->resolveMediaUrl($user->toChuc->logo)
                : null;
        }

        return response()->json([
            'message' => 'Cập nhật profile thành công',
            'user' => $user
        ]);
    }

    //đổi mật khẩu
    public function changePassword(ChangePasswordRequest $request)
    {
        $user = Auth::user();

        // Trường hợp user đăng nhập Google (chưa có mật khẩu)
        if (!$user->mat_khau) {
            $user->update([
                'mat_khau' => Hash::make($request->new_password)
            ]);

            return response()->json([
                'message' => 'Tạo mật khẩu thành công'
            ]);
        }

        // kiểm tra mật khẩu hiện tại
        if (!Hash::check($request->current_password, $user->mat_khau)) {
            return response()->json([
                'message' => 'Mật khẩu hiện tại không đúng.'
            ], 400);
        }

        // kiểm tra mật khẩu mới trùng mật khẩu cũ
        if (Hash::check($request->new_password, $user->mat_khau)) {
            return response()->json([
                'message' => 'Mật khẩu mới không được trùng mật khẩu cũ.'
            ], 400);
        }

        // cập nhật mật khẩu mới
        $user->update([
            'mat_khau' => Hash::make($request->new_password)
        ]);

        return response()->json([
            'message' => 'Đổi mật khẩu thành công'
        ]);
    }

    // Xem profile người dùng khác
    public function show($id)
    {
        // 1. Người dùng
        $user = User::select('id','ho_ten', 'ten_tai_khoan', 'anh_dai_dien', 'created_at')
            ->findOrFail($id);

        if ($user->anh_dai_dien) {
            $user->anh_dai_dien = $this->resolveMediaUrl($user->anh_dai_dien);
        }

        // 2. Tổ chức
        $xacMinh = XacMinhToChuc::where('nguoi_dung_id', $id)
            ->where('trang_thai', 'CHAP_NHAN')
            ->select('ten_to_chuc', 'mo_ta', 'loai_hinh')
            ->latest()
            ->first();

        $toChuc = ToChuc::where('nguoi_dung_id', $id)
            ->select('id', 'logo')
            ->first();

        if ($toChuc && $toChuc->logo) {
            $toChuc->logo = $this->resolveMediaUrl($toChuc->logo);
        }

        // gộp lại đúng format yêu cầu
        $org = null;
        if ($xacMinh) {
            $org = [
                'id' => $toChuc->id ?? null,
                'ten_to_chuc' => $xacMinh->ten_to_chuc,
                'mo_ta' => "Đại diện {$xacMinh->ten_to_chuc}. {$xacMinh->mo_ta}",
                'logo' => $toChuc->logo ?? null,
                'loai_hinh' => $xacMinh->loai_hinh,
            ];
        }

        // 3. Bài đăng
        $query = BaiDang::where('nguoi_dung_id', $id)
            ->with(['nguoiDung'])
            ->latest();

        $this->applyPostLikeAggregates($query);

        $baiDang = $query->get()->map(function (BaiDang $post) {
            // xử lý ảnh (string hoặc array)
            if (is_array($post->hinh_anh)) {
                $paths = $post->hinh_anh;
            } elseif (is_string($post->hinh_anh)) {
                $paths = [$post->hinh_anh];
            } else {
                $paths = [];
            }

            $hinhAnhUrls = array_map(
                fn ($p) => $this->resolveMediaUrl($p),
                $paths
            );

            // avatar
            $avatar = $post->nguoiDung && $post->nguoiDung->anh_dai_dien
                ? $this->resolveMediaUrl($post->nguoiDung->anh_dai_dien)
                : null;

            $this->decoratePostLikeFields($post);

            return [
                'id' => $post->id,
                'tieu_de' => $post->tieu_de,
                'mo_ta' => $post->mo_ta,
                'dia_diem' => $post->dia_diem,
                'trang_thai' => $post->trang_thai,
                'ngay_dang' => $post->created_at->format('d/m/Y H:i'),
                'loai_bai' => $post->loai_bai,
                'so_luong' => $post->so_luong,

                'hinh_anh_urls' => $hinhAnhUrls,
                'hinh_anh_url' => $hinhAnhUrls[0] ?? null,
                'avatar_url' => $avatar,
                'nguoi_dung_ten' => $post->nguoiDung?->ho_ten,

                'so_luot_thich' => $post->so_luot_thich,
                'so_binh_luan' => $post->so_binh_luan,
                'da_thich' => $post->da_thich,
            ];
        });

        return response()->json([
            'nguoi_dung' => $user,
            'to_chuc' => $org,
            'bai_dang' => $baiDang,
        ]);
    }

    // Cập nhật địa chỉ
    public function updateDiaChi(UpdateDiaChiRequest $request)
    {
        $user = auth()->user();

        $diaChi = $request->dia_chi;

        $geo = app(GeocodingService::class);
        $coords = $geo->geocode($diaChi);

        $lat = null;
        $lng = null;
        $region = null;

        if ($coords) {
            $lat = $coords['lat'];
            $lng = $coords['lng'];
            $region = $geo->makeRegion($lat, $lng);
        }

        $user->update([
            'dia_chi' => $diaChi,
            'lat' => $lat,
            'lng' => $lng,
            'region' => $region,
        ]);

        return response()->json([
            'message' => 'Cập nhật địa chỉ thành công',
            'data' => $user
        ]);
    }

    private function resolveMediaUrl(?string $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $raw = trim($value);
        return preg_match('/^https?:\/\//i', $raw) === 1 ? $raw : secure_asset('storage/' . ltrim($raw, '/'));
    }

    private function applyPostLikeAggregates($query): void
    {
        $query->withCount([
            'thichs as so_luot_thich',
            'binhLuans as so_binh_luan',
        ]);
        if (Auth::check()) {
            $uid = (int) Auth::id();
            $query->withExists(['thichs as da_thich' => function ($q) use ($uid) {
                $q->where('nguoi_dung_id', $uid);
            }]);
        }
    }

    private function decoratePostLikeFields(BaiDang $post): void
    {
        $post->setAttribute('so_luot_thich', (int) ($post->getAttribute('so_luot_thich') ?? 0));
        $post->setAttribute('so_binh_luan', (int) ($post->getAttribute('so_binh_luan') ?? 0));
        $post->setAttribute(
            'da_thich',
            Auth::check() ? (bool) $post->getAttribute('da_thich') : false
        );
    }
}
