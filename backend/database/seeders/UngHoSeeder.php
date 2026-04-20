<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class UngHoSeeder extends Seeder
{
    public function run(): void
    {
        $users = DB::table('nguoi_dung')
            ->whereNotIn('id', [1, 2])
            ->get();
        $campaigns = DB::table('chien_dich_gay_quy')->get();

        foreach ($campaigns as $campaign) {
            $rows = [];

            // bỏ campaign chưa duyệt
            if (in_array($campaign->trang_thai, ['CHO_XU_LY', 'TU_CHOI'])) {
                continue;
            }

            switch ($campaign->trang_thai) { 
                case 'HOAN_THANH': 
                    if ($campaign->ngay_ket_thuc >= now()) {
                        // còn hạn → vẫn donate mạnh
                        $targetPercent = rand(120, 180);
                    } else {
                        // hết hạn → gần đủ thôi
                        $targetPercent = rand(100, 120);
                    }
                    break; 
                case 'DA_KET_THUC': 
                    $targetPercent = rand(40, 90); 
                    break; 
                case 'TAM_DUNG': 
                    $targetPercent = rand(20, 70); 
                    break; 
                default: 
                    $targetPercent = rand(30, 80); 
                    break; 
            }

            if ($campaign->trang_thai === 'HOAN_THANH' && $campaign->ngay_ket_thuc >= now()) {
                // thêm 5–20 giao dịch donate sau khi đã hoàn thành
                $extraCount = rand(5, 20);

                for ($j = 0; $j < $extraCount; $j++) {

                    DB::table('ung_ho')->insert([
                        'nguoi_dung_id' => $users->random()->id,
                        'chien_dich_gay_quy_id' => $campaign->id,
                        'so_tien' => rand(50, 500) * 1000,
                        'phuong_thuc_thanh_toan' => 'vnpay',
                        'trang_thai' => 'THANH_CONG',
                        'payment_ref' => Str::uuid(),
                        'vnp_transaction_no' => rand(10000000, 99999999),
                        'created_at' => now()->subDays(rand(0, 5)),
                        'updated_at' => now(),
                    ]);
                }
            }

            $tongTienTarget = ($campaign->muc_tieu_tien * $targetPercent) / 100;

            // top donor 
            $topUsers = $users->random(min(5, $users->count()));

            $tong = 0;
            for ($i = 0; $i < 500; $i++) {

                // 30% là top donor
                $user = rand(0, 100) < 30
                    ? $topUsers->random()
                    : $users->random();

                $rand = rand(1, 100);

                if ($topUsers->contains('id', $user->id)) {
                    // top donor
                    $soTien = rand(200, 1000) * 1000;
                } else {
                    if ($rand <= 60) {
                        $soTien = rand(10, 500) * 1000;   // đa số donate nhỏ
                    } elseif ($rand <= 90) {
                        $soTien = rand(500, 5000) * 1000;  // trung bình
                    } elseif ($rand <= 98) {
                        $soTien = rand(5000, 10000) * 1000; // hiếm
                    } else{
                        $soTien = rand(10000, 50000) * 1000; // rất hiếm
                    }
                }

                if ($tong + $soTien > $tongTienTarget) {
                    $soTien = max(10000, $tongTienTarget - $tong);
                }

                $tong += $soTien;

                // thời gian
                $createdAt = Carbon::now()
                    ->subDays(rand(0, 30))
                    ->setHour(rand(0, 23))
                    ->setMinute(rand(0, 59));

                if ($createdAt > now()) {
                    $createdAt = now();
                }

                $method = rand(0, 1) ? 'vnpay' : 'momo';

                $rows[] = [
                    'nguoi_dung_id' => $user->id,
                    'chien_dich_gay_quy_id' => $campaign->id,
                    'so_tien' => $soTien,
                    'phuong_thuc_thanh_toan' => $method,
                    'trang_thai' => 'THANH_CONG',
                    'payment_ref' => Str::uuid(),
                    'gateway_transaction_id' => rand(10000000, 99999999),
                    'created_at' => $createdAt,
                    'updated_at' => now(),
                ];

                if ($tong >= $tongTienTarget) break;
            }

            if ($campaign->trang_thai === 'HOAN_THANH' && $tong < $campaign->muc_tieu_tien) {
                $conThieu = $campaign->muc_tieu_tien - $tong;

                $rows[] = [
                    'nguoi_dung_id' => $user->id,
                    'chien_dich_gay_quy_id' => $campaign->id,
                    'so_tien' => $soTien,
                    'phuong_thuc_thanh_toan' => $method,
                    'trang_thai' => 'THANH_CONG',
                    'payment_ref' => Str::uuid(),
                    'gateway_transaction_id' => rand(10000000, 99999999),
                    'created_at' => $createdAt,
                    'updated_at' => now(),
                ];
            }

            collect($rows)->chunk(500)->each(function ($chunk) {
                DB::table('ung_ho')->insert($chunk->toArray());
            });
        }
    }
}