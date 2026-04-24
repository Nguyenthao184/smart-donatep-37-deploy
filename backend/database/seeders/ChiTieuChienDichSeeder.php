<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChiTieuChienDichSeeder extends Seeder
{
    public function run(): void
    {
        // lấy các giao dịch RÚT
        $campaigns = DB::table('chien_dich_gay_quy')
            ->whereIn('id', function ($query) {
                $query->select('chien_dich_gay_quy_id')
                    ->from('giao_dich_quy')
                    ->where('loai_giao_dich', 'RUT');
            })
            ->get();

        if ($campaigns->isEmpty()) return;

       $selectedCampaigns = $campaigns->random(min(10, $campaigns->count()));

        $danhMucs = DB::table('danh_muc')->pluck('ten_danh_muc', 'id');

        foreach ($selectedCampaigns as $campaign) {
            $tenDanhMuc = $danhMucs[$campaign->danh_muc_id] ?? 'khac';

            // 3. lấy TẤT CẢ giao dịch RÚT của campaign
            $ruts = DB::table('giao_dich_quy')
                ->where('chien_dich_gay_quy_id', $campaign->id)
                ->where('loai_giao_dich', 'RUT')
                ->get();
            
            foreach ($ruts as $gd) {
                $items = match ($tenDanhMuc) {
                    'Thiên tai' => [
                        'Mua lương thực và nước uống',
                        'Phát mì gói và nhu yếu phẩm',
                        'Hỗ trợ tiền mặt cho hộ dân',
                        'Cấp phát nước sạch',
                        'Chi phí vận chuyển hàng cứu trợ',
                        'Thuê xe vận chuyển',
                        'Thuốc men và vật tư y tế',
                        'Hỗ trợ người bị thương',
                        'Dựng lều tạm trú',
                        'Cung cấp chăn màn',
                        'Sửa chữa nhà tạm',
                        'Hỗ trợ khẩn cấp cho trẻ em',
                        'Phát áo phao cứu sinh',
                        'Cứu trợ vùng bị cô lập',
                        'Chi phí hậu cần đội cứu trợ',
                    ],

                    'An sinh' => [
                        'Trao quà cho hộ khó khăn',
                        'Tặng gạo và thực phẩm',
                        'Hỗ trợ chi phí sinh hoạt',
                        'Tặng nhu yếu phẩm hàng tháng',
                        'Hỗ trợ tiền điện nước',
                        'Hỗ trợ người già neo đơn',
                        'Tặng quà cho người khuyết tật',
                        'Chi phí tổ chức phát quà',
                        'Hỗ trợ viện phí',
                        'Cấp phát thực phẩm miễn phí',
                        'Hỗ trợ người vô gia cư',
                        'Phát quà dịp lễ',
                        'Hỗ trợ chi phí đi lại',
                        'Tặng quần áo',
                        'Chăm sóc người có hoàn cảnh khó khăn',
                    ],

                    'Trẻ em' => [
                        'Trao học bổng',
                        'Tặng sách vở',
                        'Phát đồ dùng học tập',
                        'Hỗ trợ sữa dinh dưỡng',
                        'Tặng quà cho trẻ em',
                        'Tổ chức trung thu',
                        'Hỗ trợ chi phí học tập',
                        'Xây khu vui chơi',
                        'Tổ chức hoạt động ngoại khóa',
                        'Hỗ trợ trẻ em mồ côi',
                        'Tặng balo cho học sinh',
                        'Chăm sóc dinh dưỡng',
                        'Tặng áo ấm mùa đông',
                        'Hỗ trợ học phí',
                        'Tổ chức lớp học miễn phí',
                    ],

                    'Giáo dục' => [
                        'Mua thiết bị học tập',
                        'Trang bị máy tính',
                        'Tặng bàn ghế',
                        'Trao học bổng học sinh',
                        'Hỗ trợ giáo viên',
                        'Cải tạo lớp học',
                        'Xây phòng học mới',
                        'Mua sách giáo khoa',
                        'Tổ chức lớp học tình nguyện',
                        'Hỗ trợ chi phí giảng dạy',
                        'Trang bị thư viện',
                        'Cải thiện cơ sở vật chất',
                        'Hỗ trợ internet học tập',
                        'Tổ chức đào tạo kỹ năng',
                        'Phát triển chương trình giáo dục',
                    ],

                    'Môi trường' => [
                        'Dọn rác khu dân cư',
                        'Thu gom rác thải',
                        'Xử lý rác thải',
                        'Trồng cây xanh',
                        'Phát động chiến dịch xanh',
                        'Làm sạch sông suối',
                        'Mua dụng cụ vệ sinh',
                        'Tổ chức tình nguyện viên',
                        'Tuyên truyền bảo vệ môi trường',
                        'Giảm thiểu rác thải nhựa',
                        'Phân loại rác tại nguồn',
                        'Dọn bãi biển',
                        'Cải tạo môi trường ô nhiễm',
                        'Trồng cây phủ xanh',
                        'Bảo vệ nguồn nước',
                    ],

                    'Xóa đói' => [
                        'Hỗ trợ giống cây trồng',
                        'Cấp phát phân bón',
                        'Hỗ trợ lương thực',
                        'Tặng công cụ sản xuất',
                        'Hỗ trợ chăn nuôi',
                        'Cung cấp hạt giống',
                        'Đào tạo kỹ thuật canh tác',
                        'Hỗ trợ vốn sản xuất',
                        'Xây dựng mô hình kinh tế',
                        'Hỗ trợ thu hoạch',
                        'Phát triển nông nghiệp',
                        'Tư vấn sản xuất',
                        'Cải thiện sinh kế',
                        'Hỗ trợ máy móc nông nghiệp',
                        'Tạo việc làm cho người dân',
                    ],

                    default => [
                        'Chi phí hoạt động chung',
                        'Chi phí vận hành',
                        'Hỗ trợ chương trình',
                        'Chi phí nhân sự',
                        'Chi phí hậu cần',
                        'Chi phí tổ chức',
                        'Hỗ trợ hoạt động cộng đồng',
                        'Chi phí quản lý',
                        'Chi phí triển khai',
                        'Hỗ trợ vận hành',
                        'Chi phí dịch vụ',
                        'Chi phí bảo trì',
                        'Hỗ trợ dự án',
                        'Chi phí điều phối',
                        'Chi phí khác',
                    ]
                };

                $soTien = (int) $gd->so_tien;

                // ===== chia thành 3–5 hoạt động =====
                $soPhan = rand(3, 5);
                $selectedItems = collect($items)->random($soPhan)->values();

                $tong = 0;

                for ($i = 0; $i < $soPhan; $i++) {

                    if ($tong >= $soTien) break;

                    if ($i == $soPhan - 1) {
                        $tien = $soTien - $tong;
                    } else {
                        $tien = round(rand(10, 35) * $soTien / 100 / 1000000) * 1000000;
                        $tien = min($tien, $soTien - $tong);
                    }

                    if ($tien <= 0) continue;

                    $tong += $tien;

                    DB::table('chi_tieu_chien_dich')->insert([
                        'chien_dich_gay_quy_id' => $campaign->id,
                        'giao_dich_quy_id' => $gd->id,
                        'ten_hoat_dong' => $selectedItems[$i],
                        'mo_ta' => null,
                        'so_tien' => $tien,
                        'created_at' => $gd->created_at,
                    ]);
                }
            }
        }
    }
}