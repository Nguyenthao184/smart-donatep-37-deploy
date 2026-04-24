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

        $moTaMap = [
            'Trung Tâm Hỗ Trợ Cứu Trợ Thiên Tai Việt' =>
                'Tổ chức chuyên thực hiện các hoạt động cứu trợ khẩn cấp tại các khu vực chịu ảnh hưởng bởi thiên tai trên toàn quốc. Chúng tôi tập trung cung cấp lương thực, nước sạch và hỗ trợ tái thiết sau bão lũ. Với mạng lưới tình nguyện viên rộng khắp, tổ chức luôn sẵn sàng phản ứng nhanh trong mọi tình huống khẩn cấp. Mục tiêu là giúp người dân ổn định cuộc sống và phục hồi sau thiên tai.',

            'Chung Tay Xóa Đói Giảm Nghèo' =>
                'Tổ chức hướng đến việc hỗ trợ các hộ gia đình có hoàn cảnh khó khăn vươn lên thoát nghèo bền vững. Các chương trình bao gồm hỗ trợ sinh kế, cung cấp giống cây trồng và đào tạo kỹ năng lao động. Chúng tôi hợp tác với nhiều địa phương để triển khai các mô hình kinh tế hiệu quả. Mục tiêu là tạo cơ hội phát triển lâu dài cho người dân.',

            'An Sinh Cộng Đồng Việt Nam' =>
                'Tổ chức hoạt động nhằm nâng cao chất lượng cuộc sống cho các đối tượng yếu thế trong xã hội. Các chương trình bao gồm hỗ trợ tài chính, chăm sóc y tế và cung cấp nhu yếu phẩm thiết yếu. Đội ngũ luôn đồng hành cùng cộng đồng trong các hoạt động thiện nguyện. Chúng tôi hướng đến xây dựng một xã hội nhân ái và bền vững.',

            'Bảo Vệ và Phát Triển Trẻ Em Việt' =>
                'Tổ chức tập trung vào việc bảo vệ quyền lợi và hỗ trợ phát triển toàn diện cho trẻ em. Các hoạt động bao gồm trao học bổng, cung cấp dinh dưỡng và tổ chức các chương trình giáo dục. Chúng tôi đặc biệt quan tâm đến trẻ em có hoàn cảnh khó khăn. Mục tiêu là giúp các em có cơ hội phát triển tốt hơn trong tương lai.',

            'Liên Minh Hành Động Vì Môi Trường Xanh' =>
                'Tổ chức hoạt động trong lĩnh vực bảo vệ môi trường và phát triển bền vững. Các chương trình bao gồm trồng cây xanh, thu gom rác thải và nâng cao nhận thức cộng đồng. Chúng tôi hợp tác với các doanh nghiệp và tình nguyện viên để lan tỏa hành động xanh. Mục tiêu là xây dựng môi trường sống trong lành cho thế hệ tương lai.',

            'Hỗ Trợ Giáo Dục và Tri Thức Trẻ' =>
                'Tổ chức hướng đến việc nâng cao chất lượng giáo dục cho học sinh và sinh viên. Các hoạt động bao gồm trao học bổng, hỗ trợ cơ sở vật chất và tổ chức các chương trình đào tạo kỹ năng. Chúng tôi tin rằng giáo dục là nền tảng phát triển bền vững. Mục tiêu là tạo điều kiện học tập tốt hơn cho thế hệ trẻ.',

            'Cứu Trợ Nạn Nhân Thiên Tai Việt' =>
                'Tổ chức chuyên hỗ trợ các nạn nhân bị ảnh hưởng bởi thiên tai trên cả nước. Chúng tôi cung cấp các gói cứu trợ khẩn cấp và hỗ trợ tái thiết sau thảm họa. Đội ngũ tình nguyện viên luôn có mặt kịp thời tại các khu vực bị ảnh hưởng. Mục tiêu là giúp người dân vượt qua khó khăn và ổn định cuộc sống.',

            'Quỹ Hỗ Trợ Người Khuyết Tật Việt' =>
                'Tổ chức tập trung hỗ trợ người khuyết tật hòa nhập cộng đồng và cải thiện chất lượng cuộc sống. Các chương trình bao gồm hỗ trợ thiết bị y tế, đào tạo nghề và tạo việc làm. Chúng tôi luôn đồng hành cùng người khuyết tật trong hành trình phát triển bản thân. Mục tiêu là xây dựng một xã hội bình đẳng và không rào cản.',

            'Tổ Chức Hành Động Vì Người Nghèo Việt' =>
                'Tổ chức triển khai nhiều hoạt động hỗ trợ người nghèo tại các khu vực khó khăn. Các chương trình bao gồm phát quà, hỗ trợ tài chính và tạo sinh kế bền vững. Chúng tôi kết nối nguồn lực từ cộng đồng để lan tỏa giá trị nhân văn. Mục tiêu là giảm thiểu khoảng cách giàu nghèo trong xã hội.',

            'Quỹ Cứu Trợ Đồng Bào Lũ Lụt Việt' =>
                'Tổ chức chuyên hỗ trợ đồng bào bị ảnh hưởng bởi lũ lụt tại các tỉnh miền Trung. Các hoạt động bao gồm cung cấp nhu yếu phẩm, hỗ trợ tái thiết và phục hồi sinh kế. Đội ngũ luôn sẵn sàng triển khai cứu trợ trong thời gian ngắn nhất. Mục tiêu là giúp người dân nhanh chóng ổn định cuộc sống sau thiên tai.',
        ];

        $users = DB::table('nguoi_dung')
            ->whereNotIn('ten_tai_khoan', [
                'admin',
                'user'
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
                'mo_ta' => $moTaMap[$ten] ?? 'Tổ chức hoạt động vì cộng đồng tại Việt Nam',
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