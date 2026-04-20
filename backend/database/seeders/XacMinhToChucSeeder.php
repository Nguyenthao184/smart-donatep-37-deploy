<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class XacMinhToChucSeeder extends Seeder
{
    public function run(): void
    {        
        $tenToChucList = [
            'Trung Tâm Hỗ Trợ Cứu Trợ Thiên Tai Việt',
            'Chung Tay Xóa Đói Giảm Nghèo',
            'An Sinh Cộng Đồng Việt Nam',
            'Bảo Vệ và Phát Triển Trẻ Em Việt',
            'Liên Minh Hành Động Vì Môi Trường Xanh',
            'Hỗ Trợ Giáo Dục và Tri Thức Trẻ',
            'Cứu Trợ Nạn Nhân Thiên Tai Việt',
            'Quỹ Hỗ Trợ Người Khuyết Tật Việt',
            'Tổ Chức Hành Động Vì Người Nghèo Việt',
            'Quỹ Cứu Trợ Đồng Bào Lũ Lụt Việt',
        ];

        // map org → danh mục
        $orgCategoryMap = [
            'Trung Tâm Hỗ Trợ Cứu Trợ Thiên Tai Việt' => 'Thiên tai',
            'Chung Tay Xóa Đói Giảm Nghèo' => 'Xóa đói',
            'An Sinh Cộng Đồng Việt Nam' => 'An sinh',
            'Bảo Vệ và Phát Triển Trẻ Em Việt' => 'Trẻ em',
            'Liên Minh Hành Động Vì Môi Trường Xanh' => 'Môi trường',
            'Hỗ Trợ Giáo Dục và Tri Thức Trẻ' => 'Giáo dục',
        ];

        $loaiHinhMap = [
            'Trung Tâm Hỗ Trợ Cứu Trợ Thiên Tai Việt' => 'QUY_TU_THIEN',
            'Chung Tay Xóa Đói Giảm Nghèo' => 'QUY_TU_THIEN',
            'An Sinh Cộng Đồng Việt Nam' => 'QUY_TU_THIEN',
            'Bảo Vệ và Phát Triển Trẻ Em Việt' => 'QUY_TU_THIEN',
            'Liên Minh Hành Động Vì Môi Trường Xanh' => 'DOANH_NGHIEP',
            'Hỗ Trợ Giáo Dục và Tri Thức Trẻ' => 'NHA_NUOC',
        ];

        $users = DB::table('nguoi_dung')
            ->whereNotIn('ten_tai_khoan', [
                'admin',
                'user',
                'tochuc'
            ])
            ->limit(10)
            ->get();

        foreach ($users as $index => $user) {

            $ten = $tenToChucList[$index];

            $isPriority = array_key_exists($ten, $orgCategoryMap);

            DB::table('xac_minh_to_chuc')->insert([
                'nguoi_dung_id' => $user->id,
                'ten_to_chuc' => $ten,
                'ma_so_thue' => rand(1000000000,9999999999),
                'nguoi_dai_dien' => $user->ho_ten,
                'giay_phep' => 'license.pdf',
                'mo_ta' => 'Tổ chức hoạt động vì cộng đồng tại Việt Nam',
                'dia_chi' => collect(['Đà Nẵng','Hà Nội','TP.HCM'])->random(),
                'so_dien_thoai' => '0' . rand(300000000,999999999),
                'trang_thai' => $isPriority ? 'CHAP_NHAN' : 'CHO_XU_LY',
                'loai_hinh' => $loaiHinhMap[$ten] 
                    ?? collect([
                        'NHA_NUOC',
                        'QUY_TU_THIEN',
                        'DOANH_NGHIEP'
                    ])->random(),
                'duyet_boi' => $isPriority ? 1 : null,
                'duyet_luc' => $isPriority ? now() : null,

                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}