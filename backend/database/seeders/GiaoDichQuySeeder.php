<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GiaoDichQuySeeder extends Seeder
{
    public function run(): void
    {
        $users = DB::table('nguoi_dung')->get()->keyBy('id');
        $campaigns = DB::table('chien_dich_gay_quy')->get()->keyBy('id');

        $ungHos = DB::table('ung_ho')
            ->where('trang_thai', 'THANH_CONG') 
            ->get();

        $tongTheoTaiKhoan = [];
        $rows = [];

        foreach ($ungHos as $uh) {
            $user = $users[$uh->nguoi_dung_id];
            $campaignItem = $campaigns[$uh->chien_dich_gay_quy_id];

            $ten = strtoupper($this->removeVietnameseAccents($user->ho_ten));
            $moTa = $campaignItem->ma_noi_dung_ck . ' ' . $ten . ' UNG HO';

            $rows[] = [
                'tai_khoan_gay_quy_id' => $campaignItem->tai_khoan_gay_quy_id,
                'chien_dich_gay_quy_id' => $campaignItem->id,
                'ung_ho_id' => $uh->id,
                'so_tien' => $uh->so_tien,
                'loai_giao_dich' => 'UNG_HO',
                'mo_ta' => $moTa,
                'created_at' => $uh->created_at,
            ];

            // gom tiền theo tài khoản
            $tongTheoTaiKhoan[$campaignItem->tai_khoan_gay_quy_id] =
                ($tongTheoTaiKhoan[$campaignItem->tai_khoan_gay_quy_id] ?? 0)
                + $uh->so_tien;
        }

        collect($rows)->chunk(500)->each(function ($chunk) {
            DB::table('giao_dich_quy')->insert($chunk->toArray());
        });

        // update số dư tài khoản
        foreach ($tongTheoTaiKhoan as $tkId => $tongTien) {
            DB::table('tai_khoan_gay_quy')
                ->where('id', $tkId)
                ->update(['so_du' => $tongTien]);
        }

        // update campaign
        $tongTheoCampaign = DB::table('ung_ho')
            ->where('trang_thai', 'THANH_CONG')
            ->select('chien_dich_gay_quy_id', DB::raw('SUM(so_tien) as tong'))
            ->groupBy('chien_dich_gay_quy_id')
            ->pluck('tong', 'chien_dich_gay_quy_id');

        foreach ($campaigns as $campaign) {
            $tong = $tongTheoCampaign[$campaign->id] ?? 0;

            DB::table('chien_dich_gay_quy')
                ->where('id', $campaign->id)
                ->update([
                    'so_tien_da_nhan' => $tong,
                ]);
        }

        // update tổ chức
        $orgIds = DB::table('to_chuc')->pluck('id');

        foreach ($orgIds as $orgId) {
            $count = DB::table('chien_dich_gay_quy')
                ->where('to_chuc_id', $orgId)
                ->where('trang_thai', 'HOAT_DONG')
                ->count();

            DB::table('to_chuc')
                ->where('id', $orgId)
                ->update([
                    'so_cd_dang_hd' => $count
                ]);
        }

        // FAKE RÚT TIỀN (REALISTIC)
        if (rand(0, 1)) {
            $randomCampaign = $campaigns->random();
            $soDu = DB::table('tai_khoan_gay_quy')
                ->where('id', $randomCampaign->tai_khoan_gay_quy_id)
                ->value('so_du');

            if ($soDu > 5000000) {

                $rut = min(rand(100, 500) * 1000, $soDu);

                DB::table('giao_dich_quy')->insert([
                    'tai_khoan_gay_quy_id' => $randomCampaign->tai_khoan_gay_quy_id,
                    'chien_dich_gay_quy_id' => $randomCampaign->id,
                    'ung_ho_id' => null,
                    'so_tien' => $rut,
                    'loai_giao_dich' => 'RUT',
                    'mo_ta' => 'GIAI NGAN CHIEN DICH ' . $randomCampaign->ten_chien_dich,
                    'created_at' => now(),
                ]);

                DB::table('tai_khoan_gay_quy')
                    ->where('id', $randomCampaign->tai_khoan_gay_quy_id)
                    ->decrement('so_du', $rut);
            }
        }            
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

    