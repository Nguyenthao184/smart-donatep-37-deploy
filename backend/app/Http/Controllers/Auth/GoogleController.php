<?php

namespace App\Http\Controllers\Auth;

use Laravel\Socialite\Facades\Socialite;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VaiTro;
use Illuminate\Support\Str;

class GoogleController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function callback()
    {
        $frontendUrl = rtrim((string) env('FRONTEND_URL', config('app.url')), '/');

        $googleUser = Socialite::driver('google')->stateless()->user();

        $googleId = $googleUser->getId();
        $email = $googleUser->getEmail();

        // tìm user theo google_id hoặc email
        $user = User::where('google_id', $googleId)
                    ->orWhere('email', $email)
                    ->first();

        // nếu bị cấm
        if ($user && $user->trang_thai == 'BI_CAM') {
            return redirect("{$frontendUrl}/login?error=blocked");
        }

        // nếu chưa có user → tạo mới
        if (!$user) {
            $baseUsername = Str::before($email, '@');
            $username = $baseUsername;
            $count = 1;

            while (User::where('ten_tai_khoan', $username)->exists()) {
                $username = $baseUsername . $count;
                $count++;
            }

            $user = User::create([
                'google_id' => $googleId,
                'ho_ten' => $googleUser->getName(),
                'ten_tai_khoan' => $username,
                'email' => $email,
                'anh_dai_dien' => $googleUser->getAvatar(),
                'mat_khau' => null, 
                'trang_thai' => 'HOAT_DONG'
            ]);

            // gán role mặc định bằng tên role (tránh hard-code id)
            $nguoiDungRoleId = VaiTro::where('ten_vai_tro', 'NGUOI_DUNG')->value('id');
            if (!$nguoiDungRoleId) {
                return redirect("{$frontendUrl}/login?error=role_config");
            }
            $user->roles()->syncWithoutDetaching([$nguoiDungRoleId]);
        } else {
            // nếu user đã tồn tại nhưng chưa có google_id → cập nhật
            if (!$user->google_id) {
                $user->update([
                    'google_id' => $googleId
                ]);
            }
        }

        // tạo token
        $token = $user->createToken('auth_token')->plainTextToken;

        $roles = $user->roles->pluck('ten_vai_tro')->implode(',');

        return redirect("{$frontendUrl}/bang-tin?token={$token}&roles={$roles}");
    }
}