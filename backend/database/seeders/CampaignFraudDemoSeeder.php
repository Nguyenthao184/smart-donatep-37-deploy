<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\CampaignFraudDetectionService;

class CampaignFraudDemoSeeder extends Seeder
{
    public function run(): void
    {
        $org = DB::table('to_chuc')->first();

        if (!$org) {
            return;
        }
        $account = DB::table('tai_khoan_gay_quy')
            ->where('to_chuc_id', $org->id)
            ->first();

        if (!$account) {
            return;
        }
        $danhMuc = DB::table('danh_muc')->first();

        if (!$danhMuc) {
            return;
        }
        $campaignId = DB::table('chien_dich_gay_quy')->insertGetId([
            'to_chuc_id' => $org->id,
            'danh_muc_id' => $danhMuc->id,
            'tai_khoan_gay_quy_id' => $account->id,
            'hinh_anh' => json_encode([
    'campaigns/moi_truong/moi_truong1.jpg'
]),
            'ten_chien_dich' => 'Campaign Fraud Demo',
            'mo_ta' => 'Campaign test AI fraud',
            'muc_tieu_tien' => 100000000,
            'so_tien_da_nhan' => 0,
            'vi_tri' => 'TP Hồ Chí Minh',
            'lat' => 10.7769,
            'lng' => 106.7009,
            'ma_noi_dung_ck' => 'CK-' . strtoupper(Str::random(8)),
            'trang_thai' => 'HOAT_DONG',
            'ngay_ket_thuc' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $campaign = DB::table('chien_dich_gay_quy')
            ->where('id', $campaignId)
            ->first();

        if (!$campaign) {
            return;
        }

        $ownerId = $org->nguoi_dung_id;

        DB::table('ung_ho')->insert([
            [
                'nguoi_dung_id' => $ownerId,
                'chien_dich_gay_quy_id' => $campaign->id,
                'so_tien' => 50000000,
                'phuong_thuc_thanh_toan' => 'momo',
                'trang_thai' => 'THANH_CONG',
                'payment_ref' => Str::uuid(),
                'gateway_transaction_id' => rand(10000000, 99999999),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nguoi_dung_id' => $ownerId,
                'chien_dich_gay_quy_id' => $campaign->id,
                'so_tien' => 30000000,
                'phuong_thuc_thanh_toan' => 'momo',
                'trang_thai' => 'THANH_CONG',
                'payment_ref' => Str::uuid(),
                'gateway_transaction_id' => rand(10000000, 99999999),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nguoi_dung_id' => 7,
                'chien_dich_gay_quy_id' => $campaign->id,
                'so_tien' => 1000000,
                'phuong_thuc_thanh_toan' => 'momo',
                'trang_thai' => 'THANH_CONG',
                'payment_ref' => Str::uuid(),
                'gateway_transaction_id' => rand(10000000, 99999999),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        for ($i = 0; $i < 12; $i++) {
            DB::table('ung_ho')->insert([
                'nguoi_dung_id' => $ownerId,
                'chien_dich_gay_quy_id' => $campaign->id,
                
                'so_tien' => 500000,
                'phuong_thuc_thanh_toan' => 'momo',
                
                'trang_thai' => 'THANH_CONG',
                'payment_ref' => Str::uuid(),
                'gateway_transaction_id' => rand(10000000, 99999999),
                'created_at' => now()->subMinutes(rand(1, 30)),
                'updated_at' => now(),
            ]);
        }
        for ($i = 0; $i < 4; $i++) {
            DB::table('chien_dich_gay_quy')->insert([
                'to_chuc_id' => $org->id,
                'hinh_anh' => json_encode([
    'campaigns/moi_truong/moi_truong1.jpg'
]),
                'tai_khoan_gay_quy_id' =>  $account->id,
                'danh_muc_id' => $danhMuc->id,
                'ten_chien_dich' => 'Fake campaign ' . $i,
                'vi_tri' => 'TP Hồ Chí Minh',
                'lat' => 10.7769,
                'lng' => 106.7009,
                'ma_noi_dung_ck' => 'CK-' . strtoupper(Str::random(8)),
                'mo_ta' => 'AI fraud demo',
                'muc_tieu_tien' => 10000000,
                'so_tien_da_nhan' => 0,
                'trang_thai' => 'HOAT_DONG',
                'ngay_ket_thuc' => now()->addDays(20),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $tong = DB::table('ung_ho')
            ->where('chien_dich_gay_quy_id', $campaign->id)
            ->sum('so_tien');

        DB::table('chien_dich_gay_quy')
            ->where('id', $campaign->id)
            ->update([
                'so_tien_da_nhan' => 0,
            ]);

        app(CampaignFraudDetectionService::class)
            ->detectCampaign($campaign->id, 'seed_demo');
    }
}
