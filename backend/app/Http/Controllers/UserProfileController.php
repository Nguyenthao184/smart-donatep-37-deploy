<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\UpdateProfileRequest;
use App\Http\Requests\User\ChangePasswordRequest;
use App\Http\Requests\User\UpdateDiaChiRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
            ->sum('so_tien');
        
        $user = $user->fresh()->load('toChuc');

        $user->anh_dai_dien = $user->anh_dai_dien
            ? asset('storage/' . $user->anh_dai_dien)
            : null;

        if ($user->toChuc) {
            $user->toChuc->logo = $user->toChuc->logo
                ? asset('storage/' . $user->toChuc->logo)
                : null;
        }

        $taiKhoan = optional($user->toChuc)->taiKhoanGayQuy;
        if ($taiKhoan && $taiKhoan->qr_code) {
            $taiKhoan->qr_code = asset($taiKhoan->qr_code);
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

        if ($request->has('dia_chi_user')) {
            $userData['dia_chi'] = $request->dia_chi_user;
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
            ? asset('storage/' . $user->anh_dai_dien)
            : null;

        if ($user->toChuc) {
            $user->toChuc->logo = $user->toChuc->logo
                ? asset('storage/' . $user->toChuc->logo)
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
        $user = User::select('ho_ten', 'ten_tai_khoan', 'anh_dai_dien')
            ->findOrFail($id);

        if ($user->anh_dai_dien) {
            $user->anh_dai_dien = asset('storage/' . $user->anh_dai_dien);
        }

        // 2. Tổ chức
        $xacMinh = XacMinhToChuc::where('nguoi_dung_id', $id)
            ->select('ten_to_chuc', 'mo_ta', 'loai_hinh')
            ->latest()
            ->first();

        $toChuc = ToChuc::where('nguoi_dung_id', $id)
            ->select('id', 'logo')
            ->first();

        if ($toChuc && $toChuc->logo) {
            $toChuc->logo = asset('storage/' . $toChuc->logo);
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
        $baiDang = BaiDang::where('nguoi_dung_id', $id)
            ->select('tieu_de', 'mo_ta', 'dia_diem', 'trang_thai', 'created_at')
            ->latest()
            ->get()
            ->map(function ($item) {
                return [
                    'tieu_de' => $item->tieu_de,
                    'mo_ta' => $item->mo_ta,
                    'dia_diem' => $item->dia_diem,
                    'trang_thai' => $item->trang_thai,
                    'ngay_dang' => $item->created_at->format('d/m/Y H:i'),
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

        $user->update([
            'dia_chi' => $request->dia_chi
        ]);

        return response()->json([
            'message' => 'Cập nhật địa chỉ thành công',
            'data' => $user
        ]);
    }
}
