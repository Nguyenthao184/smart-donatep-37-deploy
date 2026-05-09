<?php

namespace Database\Seeders;

use App\Services\GeocodingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FraudDemoSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $geo = app(GeocodingService::class);

        /**
         * User 2: mo phong hanh vi bat thuong
         * - Dang nhieu bai trong 24h
         * - Noi dung lap lai cao
         */
        $rows = [];
        for ($i = 1; $i <= 12; $i++) {
            $created = $now->copy()->subMinutes($i * 20);
            $rows[] = [
                'nguoi_dung_id' => 2,
                'loai_bai' => 'NHAN',
                'tieu_de' => 'NHAN GAP vat pham ho tro',
                'mo_ta' => 'Can nhan gap vat pham ho tro cho truong hop khan cap.',
                'dia_diem' => 'Da Nang',
                'so_luong' => 1,
                'trang_thai' => 'CON_NHAN',
                'lat' => 16.0544,
                'lng' => 108.2022,
                'region' => $geo->makeRegion(16.0544, 108.2022),
                'created_at' => $created,
                'updated_at' => $created,
            ];
        }
        DB::table('bai_dang')->insert($rows);

        /**
         * User 3: mo phong hanh vi binh thuong
         * - It bai moi, noi dung da dang hon
         */
        DB::table('bai_dang')->insert([
            [
                'nguoi_dung_id' => 3,
                'loai_bai' => 'CHO',
                'tieu_de' => 'CHO ao am mua dong',
                'mo_ta' => 'Tang ao am cho tre em kho khan.',
                'dia_diem' => 'Da Nang',
                'so_luong' => 8,
                'trang_thai' => 'CON_TANG',
                'lat' => 16.0600,
                'lng' => 108.2050,
                'region' => $geo->makeRegion(16.0600, 108.2050),
                'created_at' => $now->copy()->subDays(2),
                'updated_at' => $now->copy()->subDays(2),
            ],
            [
                'nguoi_dung_id' => 3,
                'loai_bai' => 'NHAN',
                'tieu_de' => 'NHAN sach giao khoa',
                'mo_ta' => 'Can nhan them sach giao khoa cho hoc sinh.',
                'dia_diem' => 'Da Nang',
                'so_luong' => 5,
                'trang_thai' => 'CON_NHAN',
                'lat' => 16.0520,
                'lng' => 108.1980,
                'region' => $geo->makeRegion(16.0520, 108.1980),
                'created_at' => $now->copy()->subDays(5),
                'updated_at' => $now->copy()->subDays(5),
            ],
        ]);

        /**
         * Session/IP demo:
         * Cho user 1,2,3 cung 1 IP -> same_ip_accounts cua user 2 = 3.
         */
        $sharedIp = '14.161.10.10';
        $ua = 'FraudDemoSeeder-Agent';
        $lastActivity = time();

        DB::table('sessions')->whereIn('user_id', [1, 2, 3])->delete();

        DB::table('sessions')->insert([
            [
                'id' => (string) Str::uuid(),
                'user_id' => 1,
                'ip_address' => $sharedIp,
                'user_agent' => $ua,
                'payload' => base64_encode('demo'),
                'last_activity' => $lastActivity - 10,
            ],
            [
                'id' => (string) Str::uuid(),
                'user_id' => 2,
                'ip_address' => $sharedIp,
                'user_agent' => $ua,
                'payload' => base64_encode('demo'),
                'last_activity' => $lastActivity,
            ],
            [
                'id' => (string) Str::uuid(),
                'user_id' => 3,
                'ip_address' => $sharedIp,
                'user_agent' => $ua,
                'payload' => base64_encode('demo'),
                'last_activity' => $lastActivity - 5,
            ],
        ]);
    }
}

