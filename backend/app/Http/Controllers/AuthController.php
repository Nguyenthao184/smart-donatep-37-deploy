<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\User\ForgotPasswordRequest;
use App\Http\Requests\User\ResetPasswordRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    // đăng ký
    public function register(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required'
        ]);

        $data = Cache::get('register_' . $request->email);

        if (!$data || $data['otp'] != $request->otp) {
            return response()->json([
                'message' => 'OTP không hợp lệ hoặc đã hết hạn'
            ], 400);
        }

        // tạo username
        $baseUsername = Str::before($request->email, '@');
        $username = $baseUsername;
        $count = 1;

        while (User::where('ten_tai_khoan', $username)->exists()) {
            $username = $baseUsername . $count;
            $count++;
        }

        $user = User::create([
            'ho_ten' => $data['ho_ten'],
            'ten_tai_khoan' => $username,
            'email' => $request->email,
            'mat_khau' => $data['password'],
            'trang_thai' => 'HOAT_DONG'
        ]);

        $user->roles()->attach(2);

        Cache::forget('register_' . $request->email);

        return response()->json([
            'message' => 'Đăng ký thành công'
        ]);
    }

    public function sendOtp(RegisterRequest $request)
    {
        $data = $request->validated();

        if (User::where('email', $data['email'])->exists()) {
            return response()->json([
                'message' => 'Email đã tồn tại'
            ], 400);
        }

        // chống spam
        if (Cache::has('otp_limit_' . $data['email'])) {
            return response()->json([
                'message' => 'Vui lòng đợi 1 phút trước khi gửi lại OTP'
            ], 429);
        }

        $otp = rand(100000, 999999);

        // lưu tạm toàn bộ dữ liệu
        Cache::put('register_' . $data['email'], [
            'otp' => $otp,
            'ho_ten' => $data['ho_ten'],
            'password' => Hash::make($data['password'])
        ], now()->addMinutes(5));

        Cache::put('otp_limit_' . $data['email'], true, 60);

        Mail::send('emails.otp', ['otp' => $otp], function ($message) use ($data) {
            $message->to($data['email'])
                    ->subject('Mã xác minh đăng ký');
        });

        return response()->json([
            'message' => 'OTP đã được gửi về email'
        ]);
    }

    public function verifyRegister(Request $request)
    {
        $token = $request->token;

        $data = Cache::get('register_token_' . $token);

        if (!$data) {
            return redirect("http://localhost:5173/dang-nhap?verified=invalid");
        }

        // tạo username
        $baseUsername = Str::before($data['email'], '@');
        $username = $baseUsername;
        $count = 1;

        while (User::where('ten_tai_khoan', $username)->exists()) {
            $username = $baseUsername . $count;
            $count++;
        }

        // tạo user
        $user = User::create([
            'ho_ten' => $data['ho_ten'],
            'ten_tai_khoan' => $username,
            'email' => $data['email'],
            'mat_khau' => $data['password'],
            'trang_thai' => 'HOAT_DONG'
        ]);

        $user->roles()->attach(2);

        Cache::forget('register_token_' . $token);

        return redirect("http://localhost:5173/dang-nhap?verified=success");
    }

    // đăng nhập
    public function login(LoginRequest $request)
    {

        $data = $request->validated();

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->mat_khau)) {
            return response()->json([
                'message' => 'Sai email hoặc mật khẩu'
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
            'roles' => $user->roles->pluck('ten_vai_tro') 
        ]);
    }

    //đăng xuất
    public function logout()
    {
        Auth::user()->tokens()->delete();

        return response()->json([
            'message' => 'Đăng xuất thành công'
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load('roles');
        return response()->json([
            'user' => $request->user(),
            'roles' => $user->roles->pluck('ten_vai_tro'),
            'has_password' => $user->mat_khau ? true : false
        ]);
    }

    public function resendOtp(Request $request)
    {
        $email = $request->email;

        if (!Cache::has('register_' . $email)) {
            return response()->json(['message' => 'Không tìm thấy yêu cầu'], 400);
        }

        $otp = rand(100000, 999999);

        $data = Cache::get('register_' . $email);
        $data['otp'] = $otp;

        Cache::put('register_' . $email, $data, now()->addMinutes(5));

        Mail::send('emails.otp', ['otp' => $otp], function ($message) use ($data) {
            $message->to($data['email'])
                    ->subject('Mã xác minh đăng ký');
        });

        return response()->json(['message' => 'OTP đã được gửi lại']);
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $email = $request->email;

        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'Email không tồn tại'
            ], 404);
        }

        // chống spam
        if (Cache::has('forgot_limit_' . $email)) {
            return response()->json([
                'message' => 'Vui lòng đợi trước khi gửi lại OTP'
            ], 429);
        }

        $otp = rand(100000, 999999);

        Cache::put('forgot_' . $email, $otp, now()->addMinutes(5));
        Cache::put('forgot_limit_' . $email, true, 60);

        Mail::send('emails.otp', ['otp' => $otp], function ($message) use ($email) {
            $message->to($email)
                    ->subject('Quên mật khẩu');
        });

        return response()->json([
            'message' => 'OTP đã được gửi về email'
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $email = $request->email;
        $otp = $request->otp;

        $savedOtp = Cache::get('forgot_' . $email);

        if (!$savedOtp || $savedOtp != $otp) {
            return response()->json([
                'message' => 'OTP không hợp lệ hoặc đã hết hạn'
            ], 400);
        }

        // đánh dấu đã verify OTP (cho đổi mật khẩu)
        Cache::put('forgot_verified_' . $email, true, now()->addMinutes(5));

        return response()->json([
            'message' => 'Xác thực OTP thành công'
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $email = $request->email;

        // kiểm tra đã verify OTP chưa
        if (!Cache::has('forgot_verified_' . $email)) {
            return response()->json([
                'message' => 'Vui lòng xác thực OTP trước'
            ], 403);
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'Email không tồn tại'
            ], 404);
        }

        $user->update([
            'mat_khau' => Hash::make($request->new_password)
        ]);

        // xoá cache sau khi dùng
        Cache::forget('forgot_' . $email);
        Cache::forget('forgot_verified_' . $email);

        return response()->json([
            'message' => 'Đặt lại mật khẩu thành công'
        ]);
    }
}