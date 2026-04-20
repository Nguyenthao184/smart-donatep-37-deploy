<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TaiKhoanGayQuySeeder extends Seeder
{
    public function run(): void
    {
        $orgs = DB::table('to_chuc')->get();

        $qrMap = [
            'Thiên tai' => 'qr_thientai.png',
            'Trẻ em' => 'qr_treem.png',
            'Giáo dục' => 'qr_giaoduc.png',
            'Môi trường' => 'qr_moitruong.png',
            'Xóa đói' => 'qr_xoadoi.png',
            'An sinh' => 'qr_ansinh.png',
        ];

        $orgCategoryMap = [
            'Trung Tâm Hỗ Trợ Cứu Trợ Thiên Tai Việt' => 'Thiên tai',
            'Chung Tay Xóa Đói Giảm Nghèo' => 'Xóa đói',
            'An Sinh Cộng Đồng Việt Nam' => 'An sinh',
            'Bảo Vệ và Phát Triển Trẻ Em Việt' => 'Trẻ em',
            'Liên Minh Hành Động Vì Môi Trường Xanh' => 'Môi trường',
            'Hỗ Trợ Giáo Dục và Tri Thức Trẻ' => 'Giáo dục',
        ];
        

        foreach ($orgs as $org) {
            $categoryName = $orgCategoryMap[$org->ten_to_chuc] ?? null;
            if (!$categoryName) continue;
            $qrFile = $qrMap[$categoryName] ?? 'default.png';

            $exists = DB::table('tai_khoan_gay_quy')
                ->where('to_chuc_id', $org->id)
                ->exists();

            if ($exists) continue;

            DB::table('tai_khoan_gay_quy')->insert([
                'to_chuc_id' => $org->id,
                'ten_quy' => "Quỹ {$org->ten_to_chuc}",
                'ngan_hang' => "MB Bank",
                'so_tai_khoan' => rand(1000000000,9999999999),
                'chu_tai_khoan' => strtoupper($this->removeVietnameseAccents($org->ten_to_chuc)),
                'so_du' => rand(100000,10000000),
                'qr_code' => 'qr_code/' . $qrFile,
                'trang_thai' => 'HOAT_DONG',
                'ma_yeu_cau_mb' => 'MB_'.rand(1000,9999),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
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