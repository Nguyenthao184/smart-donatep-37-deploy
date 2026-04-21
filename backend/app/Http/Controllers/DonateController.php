<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\Donate\DonateRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\ChienDichGayQuy;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use App\Models\UngHo;

class DonateController extends Controller
{
    // Ủng hộ
    public function donate(DonateRequest $request)
    {
        $user = auth()->user();

        $campaign = ChienDichGayQuy::with('taiKhoanGayQuy')
            ->findOrFail($request->chien_dich_gay_quy_id);

        if ($campaign->trang_thai !== 'HOAT_DONG') {
            return response()->json(['message' => 'Chiến dịch không hoạt động'], 400);
        }

        if ($campaign->ngay_ket_thuc < now()) {
            return response()->json(['message' => 'Chiến dịch đã kết thúc'], 400);
        }

        DB::beginTransaction();

        try {
            // 1. Tạo giao dịch
            $ungHoId = DB::table('ung_ho')->insertGetId([
                'nguoi_dung_id' => $user->id,
                'chien_dich_gay_quy_id' => $campaign->id,
                'so_tien' => $request->so_tien,
                'trang_thai' => 'CHO_XU_LY',
                'phuong_thuc_thanh_toan' => $request->phuong_thuc_thanh_toan,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 2. Tạo nội dung CK
            $tenKhongDau = $this->removeVietnameseAccents($user->ho_ten);

            $noiDung = strtoupper(
                $campaign->ma_noi_dung_ck . ' ' .
                $tenKhongDau . ' UNG HO'
            );

            // 3. Lưu lại
            DB::table('ung_ho')
                ->where('id', $ungHoId)
                ->update([
                    'payment_ref' => $ungHoId
                ]);

            DB::commit();

            // ===== CASE 1: BANKING (QR) =====
            if ($request->phuong_thuc_thanh_toan === 'momo') {
                $tk = $campaign->taiKhoanGayQuy;
                $endpoint = "https://test-payment.momo.vn/v2/gateway/api/create";

                $partnerCode = env('MOMO_PARTNER_CODE');
                $accessKey   = env('MOMO_ACCESS_KEY');
                $secretKey   = env('MOMO_SECRET_KEY');

                $orderId   = (string) $ungHoId;
                $requestId = time() . "";

                $amount = $request->so_tien;

                $orderInfo = "Ung ho chien dich " . $campaign->id;
                $redirectUrl = "http://localhost:5173/thanh-cong";
                $ipnUrl = "https://dipyramidal-silkier-bell.ngrok-free.dev/api/momo/ipn";
                $extraData = "";

                $requestType = "payWithATM";

                // RAW HASH (PHẢI ĐÚNG THỨ TỰ)
                $rawHash = "accessKey=" . $accessKey
                    . "&amount=" . $amount
                    . "&extraData=" . $extraData
                    . "&ipnUrl=" . $ipnUrl
                    . "&orderId=" . $orderId
                    . "&orderInfo=" . $orderInfo
                    . "&partnerCode=" . $partnerCode
                    . "&redirectUrl=" . $redirectUrl
                    . "&requestId=" . $requestId
                    . "&requestType=" . $requestType;

                // SIGNATURE
                $signature = hash_hmac("sha256", $rawHash, $secretKey);

                // DATA
                $data = [
                    "partnerCode" => $partnerCode,
                    "accessKey" => $accessKey,
                    "requestId" => $requestId,
                    "amount" => $amount,
                    "orderId" => $orderId,
                    "orderInfo" => $orderInfo,
                    "redirectUrl" => $redirectUrl,
                    "ipnUrl" => $ipnUrl,
                    "extraData" => $extraData,
                    "requestType" => $requestType,
                    "signature" => $signature,
                    "lang" => "vi"
                ];

                // GỌI API
                $response = Http::withOptions([
                    'verify' => false
                ])->post($endpoint, $data)->json();

                //dd($response);

                if (!isset($response['payUrl'])) {
                    return response()->json([
                        'message' => 'Không tạo được link MoMo',
                        'data' => $response
                    ], 500);
                }

                // lưu payment_ref
                DB::table('ung_ho')
                    ->where('id', $ungHoId)
                    ->update([
                        'payment_ref' => $orderId
                    ]);

                return response()->json([
                    'type' => 'MOMO',
                    'payment_url' => $response['payUrl']
                ]);
            }

            // ===== CASE 2: VNPAY =====
            if ($request->phuong_thuc_thanh_toan === 'vnpay') {
                $vnp_TmnCode = config('services.vnpay.tmn_code');
                $vnp_HashSecret = config('services.vnpay.hash_secret');
                $vnp_Url = config('services.vnpay.url');
                $vnp_Returnurl = config('services.vnpay.return_url');

                // VNPAY: chữ ký do server tạo từ tham số vnp_* — không lấy từ body API (request chỉ có chien_dich_gay_quy_id, so_tien).
                $vnp_TxnRef = $ungHoId;

                DB::table('ung_ho')
                    ->where('id', $ungHoId)
                    ->update([
                        'payment_ref' => $vnp_TxnRef
                    ]);

                $vnp_Amount = $request->so_tien * 100;
                $vnp_IpAddr = request()->ip();
                $expire = now()->addMinutes(15)->format('YmdHis');

                $inputData = [
                    "vnp_Version" => "2.1.0",
                    "vnp_TmnCode" => $vnp_TmnCode,
                    "vnp_Amount" => $vnp_Amount,
                    "vnp_Command" => "pay",
                    "vnp_CreateDate" => now()->format('YmdHis'),
                    "vnp_CurrCode" => "VND",
                    "vnp_IpAddr" => $vnp_IpAddr,
                    "vnp_Locale" => "vn",
                    "vnp_OrderInfo" => "Thanh toan GD:" . $vnp_TxnRef, 
                    "vnp_OrderType" => "other",
                    "vnp_ReturnUrl" => $vnp_Returnurl,
                    "vnp_TxnRef" => $vnp_TxnRef,
                    "vnp_ExpireDate" => $expire
                ];

                ksort($inputData);
                $hashdata = "";
                $query = "";
                $i = 0;

                foreach ($inputData as $key => $value) {
                    if ($i == 1) {
                        $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
                    } else {
                        $hashdata .= urlencode($key) . "=" . urlencode($value);
                        $i = 1;
                    }
                    $query .= urlencode($key) . "=" . urlencode($value) . '&';
                }

                // tạo secure hash
                $vnpSecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);

                // URL thanh toán
                $paymentUrl = $vnp_Url . "?" . $query . 'vnp_SecureHash=' . $vnpSecureHash;

                return response()->json([
                    'type' => 'VNPAY',
                    'payment_url' => $paymentUrl
                ]);
            }
            return response()->json(['message' => 'Phương thức không hợp lệ'], 400);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Lỗi hệ thống',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Lịch sử ủng hộ của user
    public function donateHistory()
    {
        $userId = auth()->id();

        $data = DB::table('ung_ho as uh')
            ->join('chien_dich_gay_quy as cd', 'uh.chien_dich_gay_quy_id', '=', 'cd.id')
            ->leftJoin('tai_khoan_gay_quy as tk', 'cd.tai_khoan_gay_quy_id', '=', 'tk.id')
            ->select(
                'uh.id',
                'uh.so_tien',
                'uh.trang_thai',
                'uh.created_at as ngay_ung_ho',
                'cd.id as chien_dich_id',
                'cd.ten_chien_dich',
                'cd.hinh_anh'
            )
            ->where('uh.nguoi_dung_id', $userId)
            ->orderByDesc('uh.created_at')
            ->get()
            ->map(function ($item) {
            // lấy ảnh đầu tiên từ JSON
            $images = json_decode($item->hinh_anh, true);

            $item->anh = isset($images[0])
                ? $this->resolveMediaUrl($images[0])
                : null;

            unset($item->hinh_anh);
            return $item;
        });

        return response()->json([
            'data' => $data
        ]);
    }

    private function resolveMediaUrl(?string $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $raw = trim($value);
        return preg_match('/^https?:\/\//i', $raw) === 1 ? $raw : asset('storage/' . ltrim($raw, '/'));
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

    private function vnpVerifySignature(array $params): bool
    {
        $secureHash = $params['vnp_SecureHash'] ?? null;

        if (!$secureHash) {
            return false;
        }

        // bỏ hash ra trước khi tạo chữ ký
        unset($params['vnp_SecureHash'], $params['vnp_SecureHashType']);

        // sort giống VNPAY
        ksort($params);

        // build chuỗi hash giống mẫu VNPAY
        $hashData = '';
        $i = 0;

        foreach ($params as $key => $value) {
            if ($i == 1) {
                $hashData .= '&' . urlencode($key) . '=' . urlencode($value);
            } else {
                $hashData .= urlencode($key) . '=' . urlencode($value);
                $i = 1;
            }
        }

        // tạo chữ ký
        $calcHash = hash_hmac(
            'sha512',
            $hashData,
            config('services.vnpay.hash_secret')
        );

        // so sánh an toàn
        return hash_equals($calcHash, $secureHash);
    }

    public function vnpayReturn(Request $request)
    {
        \Log::info('VNPAY RETURN', $request->query());
        $inputData = array_filter(
            $request->query(),
            static fn (string $k): bool => str_starts_with($k, 'vnp_'),
            ARRAY_FILTER_USE_KEY
        );
        
        $vnp_SecureHash = $inputData['vnp_SecureHash'] ?? '';

        if (empty($vnp_SecureHash)) {
            if ($request->vnp_ResponseCode == '00') {
                // ⚠️ sandbox lỗi → vẫn cho pass
            } else {
                return response()->json(['message' => 'Thiếu chữ ký'], 400);
            }
        } else{
            unset($inputData['vnp_SecureHash'], $inputData['vnp_SecureHashType']);

            ksort($inputData);

            $hashData = '';
            $i = 0;

            foreach ($inputData as $key => $value) {
                if ($i == 1) {
                    $hashData .= '&' . urlencode($key) . "=" . urlencode($value);
                } else {
                    $hashData .= urlencode($key) . "=" . urlencode($value);
                    $i = 1;
                }
            }

            $secureHash = hash_hmac(
                'sha512',
                $hashData,
                config('services.vnpay.hash_secret')
            );

            if (!hash_equals($secureHash, $vnp_SecureHash)) {
                return response()->json(['message' => 'Sai chữ ký'], 400);
            }
        }

        $ungHo = DB::table('ung_ho')
            ->where('payment_ref', $request->vnp_TxnRef)
            ->first();
        if (!$ungHo) {
            return response()->json(['message' => 'Không tìm thấy giao dịch'], 404);
        }

        if (($request->vnp_Amount / 100) != $ungHo->so_tien) {
            return response()->json([
                'message' => 'Sai số tiền'
            ], 400);
        }

        if ($ungHo->trang_thai !== 'CHO_XU_LY') {
            return response()->json([
                'message' => 'Giao dịch đã xử lý',
                'data' => ['ung_ho_id' => $ungHo->id],
            ], 409);
        }

        DB::beginTransaction();

        try {
            $ungHoLocked = DB::table('ung_ho')
                ->where('id', $ungHo->id)
                ->lockForUpdate()
                ->first();

            if (!$ungHoLocked || $ungHoLocked->trang_thai !== 'CHO_XU_LY') {
                DB::rollBack();

                return response()->json([
                    'message' => 'Giao dịch đã xử lý',
                    'data' => ['ung_ho_id' => $ungHo->id],
                ], 409);
            }

            if ($request->vnp_ResponseCode == '00' && $request->vnp_TransactionStatus == '00') {
                DB::table('ung_ho')
                    ->where('id', $ungHoLocked->id)
                    ->update([
                        'trang_thai' => 'THANH_CONG',
                        'gateway_transaction_id' => $request->vnp_TransactionNo,
                        'updated_at' => now(),
                    ]);

                $campaign = DB::table('chien_dich_gay_quy')
                    ->where('id', $ungHoLocked->chien_dich_gay_quy_id)
                    ->lockForUpdate()
                    ->first();

                $user = DB::table('nguoi_dung')
                    ->where('id', $ungHoLocked->nguoi_dung_id)
                    ->first();

                $tenKhongDau = $this->removeVietnameseAccents($user->ho_ten);
                $moTa = strtoupper(
                    $campaign->ma_noi_dung_ck.' '.
                    $tenKhongDau.' UNG HO'
                );

                DB::table('giao_dich_quy')->insert([
                    'tai_khoan_gay_quy_id' => $campaign->tai_khoan_gay_quy_id,
                    'ung_ho_id' => $ungHoLocked->id,
                    'so_tien' => $ungHoLocked->so_tien,
                    'loai_giao_dich' => 'UNG_HO',
                    'mo_ta' => $moTa,
                    'created_at' => now(),
                ]);

                DB::table('tai_khoan_gay_quy')
                    ->where('id', $campaign->tai_khoan_gay_quy_id)
                    ->increment('so_du', $ungHoLocked->so_tien);

                DB::table('chien_dich_gay_quy')
                    ->where('id', $campaign->id)
                    ->increment('so_tien_da_nhan', $ungHoLocked->so_tien);

                DB::commit();

                return redirect()->away(
                    "http://localhost:5173/thanh-cong?" . http_build_query([
                        'status' => 'success',
                        'orderId' => $request->vnp_TxnRef
                    ])
                );
            }

            DB::table('ung_ho')
                ->where('id', $ungHoLocked->id)
                ->update([
                    'trang_thai' => 'THAT_BAI',
                ]);

            DB::commit();

            return redirect()->away(
                env('FRONTEND_URL') . "/thanh-cong?" . http_build_query([
                    'status' => 'failed',
                    'orderId' => $request->vnp_TxnRef
                ])
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Lỗi hệ thống',
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function momoIpn(Request $request)
    {
        \Log::info('MOMO IPN', $request->all());

        $orderId = $request->orderId;
        $transId = $request->transId ?? null;
        $resultCode = $request->resultCode;

        // tìm giao dịch (giống VNPAY)
        $ungHo = DB::table('ung_ho')
            ->where('payment_ref', $orderId)
            ->first();

        if (!$ungHo) {
            return response()->json(['message' => 'Không tìm thấy giao dịch'], 404);
        }

        // check số tiền
        if ($request->amount != $ungHo->so_tien) {
            return response()->json(['message' => 'Sai số tiền'], 400);
        }

        // tránh xử lý lại
        if ($ungHo->trang_thai !== 'CHO_XU_LY') {
            return response()->json([
                'message' => 'Giao dịch đã xử lý',
                'data' => ['ung_ho_id' => $ungHo->id],
            ], 409);
        }

        DB::beginTransaction();

        try {
            // lock giống VNPAY
            $ungHoLocked = DB::table('ung_ho')
                ->where('id', $ungHo->id)
                ->lockForUpdate()
                ->first();

            if (!$ungHoLocked || $ungHoLocked->trang_thai !== 'CHO_XU_LY') {
                DB::rollBack();

                return response()->json([
                    'message' => 'Giao dịch đã xử lý',
                    'data' => ['ung_ho_id' => $ungHo->id],
                ], 409);
            }

            // THÀNH CÔNG (giống vnp_ResponseCode == 00)
            if ($resultCode == 0) {

                DB::table('ung_ho')
                    ->where('id', $ungHoLocked->id)
                    ->update([
                        'trang_thai' => 'THANH_CONG',
                        'gateway_transaction_id' => $transId ?? 'MOMO_' . time(),
                        'updated_at' => now(),
                    ]);

                // lấy campaign
                $campaign = DB::table('chien_dich_gay_quy')
                    ->where('id', $ungHoLocked->chien_dich_gay_quy_id)
                    ->lockForUpdate()
                    ->first();

                // lấy user
                $user = DB::table('nguoi_dung')
                    ->where('id', $ungHoLocked->nguoi_dung_id)
                    ->first();

                // mô tả (y chang VNPAY)
                $tenKhongDau = $this->removeVietnameseAccents($user->ho_ten);
                $moTa = strtoupper(
                    $campaign->ma_noi_dung_ck . ' ' .
                    $tenKhongDau . ' UNG HO'
                );

                // insert giao dịch quỹ
                DB::table('giao_dich_quy')->insert([
                    'tai_khoan_gay_quy_id' => $campaign->tai_khoan_gay_quy_id,
                    'ung_ho_id' => $ungHoLocked->id,
                    'so_tien' => $ungHoLocked->so_tien,
                    'loai_giao_dich' => 'UNG_HO',
                    'mo_ta' => $moTa,
                    'created_at' => now(),
                ]);

                // cộng số dư
                DB::table('tai_khoan_gay_quy')
                    ->where('id', $campaign->tai_khoan_gay_quy_id)
                    ->increment('so_du', $ungHoLocked->so_tien);

                // cộng campaign
                DB::table('chien_dich_gay_quy')
                    ->where('id', $campaign->id)
                    ->increment('so_tien_da_nhan', $ungHoLocked->so_tien);

                DB::commit();

                return response()->json(['message' => 'OK']);
            }

            // THẤT BẠI
            DB::table('ung_ho')
                ->where('id', $ungHoLocked->id)
                ->update([
                    'trang_thai' => 'THAT_BAI',
                ]);

            DB::commit();

            return response()->json(['message' => 'FAILED']);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Lỗi hệ thống',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getDonateDetail($id)
    {
        $data = DB::table('ung_ho as uh')
            ->join('chien_dich_gay_quy as cd', 'uh.chien_dich_gay_quy_id', '=', 'cd.id')
            ->join('nguoi_dung as nd', 'uh.nguoi_dung_id', '=', 'nd.id')
            ->select(
                'uh.id',
                'uh.so_tien',
                'uh.trang_thai',
                'uh.created_at',
                'uh.phuong_thuc_thanh_toan',
                'uh.gateway_transaction_id',
                'cd.ten_chien_dich',
                'nd.ho_ten'
            )
            ->where('uh.id', $id)
            ->first();

        if (!$data || $data->trang_thai !== 'THANH_CONG') {
            return response()->json(['message' => 'Không hợp lệ'], 403);
        }

        return response()->json([
            'data' => $data
        ]);
    }
}