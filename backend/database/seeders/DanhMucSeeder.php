<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DanhMucSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('danh_muc')->insert([
            ['ten_danh_muc' => 'Thiên tai', 'hinh_anh' => 'danh_muc/thientai.png'],
            ['ten_danh_muc' => 'Xóa đói', 'hinh_anh' => 'danh_muc/xoadoi.png'],
            ['ten_danh_muc' => 'An sinh', 'hinh_anh' => 'danh_muc/ansinh.png'],
            ['ten_danh_muc' => 'Trẻ em', 'hinh_anh' => 'danh_muc/treem.png'],
            ['ten_danh_muc' => 'Môi trường', 'hinh_anh' => 'danh_muc/moitruong.png'],
            ['ten_danh_muc' => 'Giáo dục', 'hinh_anh' => 'danh_muc/giaoduc.png'],
        ]);
    }
}

