<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class ChienDichGayQuySeeder extends Seeder
{
    public function run(): void
    {
        $orgCategoryMap = [
            'Trung Tâm Hỗ Trợ Cứu Trợ Thiên Tai Việt' => 'Thiên tai',
            'Chung Tay Xóa Đói Giảm Nghèo' => 'Xóa đói',
            'An Sinh Cộng Đồng Việt Nam' => 'An sinh',
            'Bảo Vệ và Phát Triển Trẻ Em Việt' => 'Trẻ em',
            'Liên Minh Hành Động Vì Môi Trường Xanh' => 'Môi trường',
            'Hỗ Trợ Giáo Dục và Tri Thức Trẻ' => 'Giáo dục',
        ];

        $folderMap = [
            'Trẻ em' => 'tre_em',
            'Giáo dục' => 'giao_duc',
            'Thiên tai' => 'thien_tai',
            'Xóa đói' => 'xoa_doi',
            'Môi trường' => 'moi_truong',
            'An sinh' => 'an_sinh',
        ];

        $nameTemplates = [
            'Thiên tai' => [
                'Chung tay cứu trợ khẩn cấp vùng bị thiên tai',
                'Hỗ trợ người dân vượt qua hậu quả bão lũ',
                'Tiếp sức đồng bào vùng lũ tái thiết cuộc sống',
                'Cứu trợ khẩn cấp cho khu vực bị ảnh hưởng nặng',
                'Chung sức khắc phục hậu quả thiên tai',
                'Hành trình cứu trợ đến vùng lũ khó khăn',
                'Hỗ trợ tái thiết sau thiên tai cho người dân',
                'Chia sẻ yêu thương đến vùng bị thiên tai',
                'Tiếp tế khẩn cấp cho người dân vùng lũ',
                'Đồng hành cùng người dân sau thiên tai',
            ],
            'Trẻ em' => [
                'Vì nụ cười trẻ thơ ngày mai',
                'Chung tay bảo vệ tuổi thơ em nhỏ',
                'Mang yêu thương đến trẻ em vùng khó',
                'Tiếp bước tương lai cho trẻ em nghèo',
                'Trao yêu thương – vun đắp tuổi thơ',
                'Hành trình vì trẻ em kém may mắn',
                'Chắp cánh ước mơ cho em nhỏ vùng cao',
                'Nâng đỡ tuổi thơ – thắp sáng tương lai',
                'Đồng hành cùng trẻ em trên con đường đến trường',
                'Vì một tuổi thơ đủ đầy và hạnh phúc',
            ],
            'Giáo dục' => [
                'Ươm mầm tri thức cho thế hệ tương lai',
                'Chung tay xây dựng ước mơ đến trường',
                'Tiếp bước em đến lớp mỗi ngày',
                'Trao học bổng – gửi gắm tương lai',
                'Vì một hành trình học tập không gián đoạn',
                'Nâng cao tri thức cho trẻ em vùng khó',
                'Cùng em viết tiếp giấc mơ học đường',
                'Hỗ trợ điều kiện học tập cho học sinh nghèo',
                'Gieo chữ – gặt tương lai',
                'Chắp cánh tri thức cho vùng sâu vùng xa',
            ],
            'Môi trường' => [
                'Chung tay giảm thiểu ô nhiễm môi trường',
                'Hành động nhỏ – thay đổi lớn cho môi trường',
                'Vì một hành tinh xanh không rác thải',
                'Bảo vệ thiên nhiên – bảo vệ tương lai',
                'Lan tỏa lối sống xanh trong cộng đồng',
                'Chung sức tái chế vì môi trường bền vững',
                'Giữ màu xanh cho trái đất hôm nay',
                'Hành trình xanh – vì cuộc sống trong lành',
                'Cùng nhau kiến tạo môi trường sạch đẹp',
                'Nói không với rác thải nhựa dùng một lần',
            ],
            'Xóa đói' => [
                'Tiếp sức sinh kế cho hộ nghèo bền vững',
                'Chung tay mang bữa cơm ấm đến mọi nhà',
                'Hành trình xóa đói – trao hy vọng',
                'Nâng bước người nghèo vượt qua khó khăn',
                'Trao cần câu – không trao con cá',
                'Chia sẻ yêu thương đến vùng thiếu thốn',
                'Hỗ trợ sinh kế cho người dân nghèo',
                'Cùng nhau xây dựng cuộc sống đủ đầy',
                'Thắp sáng niềm tin cho người nghèo',
                'Đồng hành cùng hộ nghèo vươn lên',
            ],
            'An sinh' => [
                'Chung tay nâng đỡ những mảnh đời khó khăn',
                'Thắp sáng hy vọng cho hoàn cảnh khó khăn',
                'Hành trình sẻ chia cùng người kém may mắn',
                'Lan tỏa yêu thương đến những số phận khó khăn',
                'Kết nối yêu thương – nâng bước người yếu thế',
                'Chung sức vì cuộc sống tốt đẹp hơn cho người nghèo',
                'Trao cơ hội – tiếp sức những mảnh đời bất hạnh',
                'Sẻ chia hôm nay – đổi thay ngày mai',
                'Vì một cộng đồng không ai bị bỏ lại phía sau',
                'Chắp cánh hy vọng cho những hoàn cảnh khó khăn',
            ],
        ];

        $orgs = DB::table('to_chuc')
            ->join('tai_khoan_gay_quy', 'to_chuc.id', '=', 'tai_khoan_gay_quy.to_chuc_id')
            ->select('to_chuc.*', 'tai_khoan_gay_quy.id as tk_id')
            ->where('to_chuc.trang_thai', 'HOAT_DONG')
            ->where('tai_khoan_gay_quy.trang_thai', 'HOAT_DONG')
            ->get();

        $locations = [
            // ===== ĐÀ NẴNG =====
            ['address' => '12 Bạch Đằng, Hải Châu, Đà Nẵng', 'lat' => 16.0703, 'lng' => 108.2240],
            ['address' => '45 Nguyễn Văn Linh, Hải Châu, Đà Nẵng', 'lat' => 16.0605, 'lng' => 108.2140],
            ['address' => '78 Võ Nguyên Giáp, Sơn Trà, Đà Nẵng', 'lat' => 16.0825, 'lng' => 108.2473],
            ['address' => '22 Lê Duẩn, Hải Châu, Đà Nẵng', 'lat' => 16.0678, 'lng' => 108.2152],
            ['address' => '90 Trần Phú, Hải Châu, Đà Nẵng', 'lat' => 16.0709, 'lng' => 108.2215],
            ['address' => '5 Phạm Văn Đồng, Sơn Trà, Đà Nẵng', 'lat' => 16.0758, 'lng' => 108.2305],
            ['address' => '33 Hoàng Diệu, Hải Châu, Đà Nẵng', 'lat' => 16.0643, 'lng' => 108.2159],
            ['address' => '101 Nguyễn Hữu Thọ, Hải Châu, Đà Nẵng', 'lat' => 16.0512, 'lng' => 108.2025],
            ['address' => '64 Điện Biên Phủ, Thanh Khê, Đà Nẵng', 'lat' => 16.0700, 'lng' => 108.2020],
            ['address' => '200 Ngô Quyền, Sơn Trà, Đà Nẵng', 'lat' => 16.0810, 'lng' => 108.2380],

            // ===== HÀ NỘI =====
            ['address' => '10 Tràng Tiền, Hoàn Kiếm, Hà Nội', 'lat' => 21.0245, 'lng' => 105.8560],
            ['address' => '45 Trần Phú, Ba Đình, Hà Nội', 'lat' => 21.0313, 'lng' => 105.8381],
            ['address' => '120 Xuân Thủy, Cầu Giấy, Hà Nội', 'lat' => 21.0365, 'lng' => 105.7820],
            ['address' => '88 Nguyễn Trãi, Thanh Xuân, Hà Nội', 'lat' => 20.9980, 'lng' => 105.8090],
            ['address' => '15 Láng Hạ, Đống Đa, Hà Nội', 'lat' => 21.0170, 'lng' => 105.8120],
            ['address' => '60 Kim Mã, Ba Đình, Hà Nội', 'lat' => 21.0305, 'lng' => 105.8165],
            ['address' => '200 Cầu Giấy, Cầu Giấy, Hà Nội', 'lat' => 21.0330, 'lng' => 105.8000],
            ['address' => '25 Hai Bà Trưng, Hoàn Kiếm, Hà Nội', 'lat' => 21.0285, 'lng' => 105.8500],
            ['address' => '300 Nguyễn Văn Cừ, Long Biên, Hà Nội', 'lat' => 21.0450, 'lng' => 105.8800],
            ['address' => '18 Phạm Hùng, Nam Từ Liêm, Hà Nội', 'lat' => 21.0280, 'lng' => 105.7760],

            // ===== TP.HCM =====
            ['address' => '200 Lê Lợi, Quận 1, TP.HCM', 'lat' => 10.7769, 'lng' => 106.7009],
            ['address' => '50 Nguyễn Huệ, Quận 1, TP.HCM', 'lat' => 10.7745, 'lng' => 106.7040],
            ['address' => '100 Điện Biên Phủ, Bình Thạnh, TP.HCM', 'lat' => 10.8003, 'lng' => 106.7100],
            ['address' => '75 Cách Mạng Tháng 8, Quận 3, TP.HCM', 'lat' => 10.7790, 'lng' => 106.6870],
            ['address' => '300 Trường Chinh, Tân Bình, TP.HCM', 'lat' => 10.8060, 'lng' => 106.6350],
            ['address' => '150 Phan Xích Long, Phú Nhuận, TP.HCM', 'lat' => 10.8000, 'lng' => 106.6800],
            ['address' => '20 Nguyễn Thị Minh Khai, Quận 1, TP.HCM', 'lat' => 10.7870, 'lng' => 106.7010],
            ['address' => '90 Lý Thường Kiệt, Quận 10, TP.HCM', 'lat' => 10.7700, 'lng' => 106.6600],
            ['address' => '210 Võ Văn Ngân, Thủ Đức, TP.HCM', 'lat' => 10.8500, 'lng' => 106.7700],
            ['address' => '60 Nguyễn Văn Linh, Quận 7, TP.HCM', 'lat' => 10.7300, 'lng' => 106.7200],

            // ===== CẦN THƠ =====
            ['address' => '10 Ninh Kiều, Cần Thơ', 'lat' => 10.0340, 'lng' => 105.7870],
            ['address' => '50 Trần Văn Khéo, Ninh Kiều, Cần Thơ', 'lat' => 10.0330, 'lng' => 105.7800],
            ['address' => '120 30/4, Ninh Kiều, Cần Thơ', 'lat' => 10.0300, 'lng' => 105.7700],
            ['address' => '80 Nguyễn Văn Linh, Cần Thơ', 'lat' => 10.0280, 'lng' => 105.7600],
            ['address' => '25 Lý Tự Trọng, Cần Thơ', 'lat' => 10.0350, 'lng' => 105.7850],

            // ===== HUẾ =====
            ['address' => '20 Hùng Vương, Huế', 'lat' => 16.4637, 'lng' => 107.5909],
            ['address' => '50 Lê Lợi, Huế', 'lat' => 16.4660, 'lng' => 107.5930],
            ['address' => '100 Nguyễn Huệ, Huế', 'lat' => 16.4700, 'lng' => 107.5800],
            ['address' => '12 Phạm Ngũ Lão, Huế', 'lat' => 16.4665, 'lng' => 107.5950],
            ['address' => '70 Trần Hưng Đạo, Huế', 'lat' => 16.4650, 'lng' => 107.6000],

            // ===== HẢI PHÒNG =====
            ['address' => '10 Lạch Tray, Hải Phòng', 'lat' => 20.8440, 'lng' => 106.6880],
            ['address' => '50 Cầu Đất, Hải Phòng', 'lat' => 20.8600, 'lng' => 106.6800],
            ['address' => '100 Trần Nguyên Hãn, Hải Phòng', 'lat' => 20.8500, 'lng' => 106.6700],
            ['address' => '25 Điện Biên Phủ, Hải Phòng', 'lat' => 20.8550, 'lng' => 106.6900],
            ['address' => '60 Nguyễn Văn Linh, Hải Phòng', 'lat' => 20.8400, 'lng' => 106.7000],

            // ===== NHA TRANG =====
            ['address' => '10 Trần Phú, Nha Trang', 'lat' => 12.2388, 'lng' => 109.1967],
            ['address' => '50 Nguyễn Thị Minh Khai, Nha Trang', 'lat' => 12.2400, 'lng' => 109.1900],
            ['address' => '100 Lê Hồng Phong, Nha Trang', 'lat' => 12.2500, 'lng' => 109.1800],
            ['address' => '25 Hùng Vương, Nha Trang', 'lat' => 12.2350, 'lng' => 109.1950],
            ['address' => '80 2/4, Nha Trang', 'lat' => 12.2600, 'lng' => 109.2000],

        ];
        $shuffledLocations = collect($locations)->shuffle();
        $index = 0;
        foreach ($orgs as $org) {
            // lấy danh mục theo tổ chức
            $categoryName = $orgCategoryMap[$org->ten_to_chuc] ?? null;
            if (!$categoryName) continue;

            // lấy danh mục trong DB
            $danhMuc = DB::table('danh_muc')
                ->where('ten_danh_muc', $categoryName)
                ->first();

            if (!$danhMuc) continue;

            // lấy folder ảnh
            $folder = $folderMap[$categoryName] ?? null;

            $files = $folder
                ? Storage::disk('public')->files("campaigns/$folder")
                : [];

            // fallback nếu folder rỗng
            if (empty($files)) {
                $files = [
                    'campaigns/default.jpg'
                ];
            }

            // tạo 10 campaign
            for ($i = 0; $i < 10; $i++) {
                $location = $shuffledLocations[$index % $shuffledLocations->count()];
                $index++;

                // Tổng số ảnh trong folder
                $totalFiles = count($files);

                // Lấy ảnh đầu tiên KHÔNG TRÙNG theo index
                $firstImage = $files[$i % $totalFiles];

                // Lấy thêm 1–4 ảnh random khác (không trùng ảnh đầu)
                $otherCount = rand(2, 3);
                $otherImages = collect($files)
                    ->reject(fn($f) => $f === $firstImage)
                    ->shuffle()
                    ->take(min($otherCount, $totalFiles - 1));

                $images = collect([$firstImage])
                    ->merge($otherImages)
                    ->values()
                    ->toArray();

                // mục tiêu tiền
                $rand = rand(1, 100);
                if ($rand <= 50) {
                    $mucTieu = rand(500, 1000) * 1000000;
                } elseif ($rand <= 80) {
                    $mucTieu = rand(1000, 2000) * 1000000;
                } else {
                    $mucTieu = rand(2000, 5000) * 1000000;
                }

                $statuses = array_merge(
                    array_fill(0, 55, 'HOAT_DONG'),
                    array_fill(0, 10, 'TAM_DUNG'),
                    array_fill(0, 10, 'DA_KET_THUC'),
                    array_fill(0, 15, 'HOAN_THANH'),
                    array_fill(0, 5, 'CHO_XU_LY'),
                    array_fill(0, 5, 'TU_CHOI'),
                );

                $trangThai = $statuses[array_rand($statuses)];

                switch ($trangThai) {
                    case 'HOAT_DONG':
                        // đang chạy → ngày kết thúc ở tương lai
                        $createdAt = now()->subDays(rand(1, 10));
                        $ngayKetThuc = now()->addDays(rand(1, 30));
                        break;

                    case 'TAM_DUNG':
                        // tạm dừng nhưng chưa hết hạn
                        $createdAt = now()->subDays(rand(5, 20));
                        $ngayKetThuc = now()->addDays(rand(5, 20));
                        break;

                    case 'CHO_XU_LY':
                        // mới tạo → còn hạn xa
                        $createdAt = now()->subDays(rand(0, 2));
                        $ngayKetThuc = now()->addDays(rand(10, 40));
                        break;

                    case 'DA_KET_THUC':
                        // đã kết thúc → ngày trong quá khứ
                        $ngayKetThuc = now()->subDays(rand(1, 10));
                        $createdAt = (clone $ngayKetThuc)->subDays(rand(10, 30));
                        break;

                    case 'HOAN_THANH':
                        // hoàn thành sớm → vẫn là quá khứ
                        $ngayKetThuc = now()->subDays(rand(1, 5));
                        $createdAt = (clone $ngayKetThuc)->subDays(rand(5, 20));
                        break;

                    case 'TU_CHOI':
                        // bị từ chối → có thể chưa tới hạn hoặc vừa tạo
                        $createdAt = now()->subDays(rand(0, 5));
                        $ngayKetThuc = now()->addDays(rand(5, 15));
                        break;

                    default:
                        $createdAt = now()->subDays(3);
                        $ngayKetThuc = now()->addDays(10);
                        break;
                }

                $tenChienDich = $nameTemplates[$categoryName][$i] ?? "Chung tay vì cộng đồng";

                $descriptions = [
                    "Chiến dịch nhằm hỗ trợ {$categoryName} tại khu vực {$location['address']}. Chúng tôi kêu gọi sự chung tay từ cộng đồng để mang lại những giá trị thiết thực như hỗ trợ tài chính, cung cấp nhu yếu phẩm và cải thiện điều kiện sống cho những hoàn cảnh khó khăn. Mỗi đóng góp, dù nhỏ, đều góp phần tạo nên sự thay đổi tích cực và lan tỏa yêu thương trong xã hội.",

                    "Với mong muốn nâng cao chất lượng cuộc sống cho cộng đồng {$categoryName}, chiến dịch được triển khai tại {$location['address']} nhằm gây quỹ hỗ trợ kịp thời. Số tiền quyên góp sẽ được sử dụng minh bạch cho các hoạt động thiết thực như cứu trợ, giáo dục và phát triển bền vững cho người dân địa phương.",

                    "Chiến dịch hướng tới việc hỗ trợ {$categoryName} tại {$location['address']}, nơi vẫn còn nhiều hoàn cảnh khó khăn cần được giúp đỡ. Chúng tôi hy vọng nhận được sự đồng hành từ các mạnh thường quân để cùng nhau xây dựng một cộng đồng tốt đẹp và nhân văn hơn.",

                    "Chiến dịch được triển khai tại {$location['address']} với mục tiêu hỗ trợ {$categoryName} một cách bền vững và lâu dài. Chúng tôi mong muốn tạo ra sự thay đổi tích cực thông qua việc kết nối cộng đồng, kêu gọi sự đóng góp từ các nhà hảo tâm để giúp đỡ những hoàn cảnh khó khăn vượt qua thử thách trong cuộc sống.",

                    "Tại {$location['address']}, chiến dịch hướng đến việc hỗ trợ {$categoryName} thông qua các hoạt động thiết thực như cung cấp nhu yếu phẩm, hỗ trợ tài chính và cải thiện điều kiện sống. Sự chung tay của cộng đồng sẽ là động lực to lớn giúp những người kém may mắn có thêm niềm tin vào cuộc sống.",

                    "Chiến dịch gây quỹ lần này tập trung vào {$categoryName} tại khu vực {$location['address']}, nơi vẫn còn nhiều khó khăn cần được chia sẻ. Với tinh thần tương thân tương ái, chúng tôi hy vọng có thể mang lại những giá trị tốt đẹp và góp phần xây dựng một xã hội công bằng, nhân ái hơn.",

                    "Chúng tôi phát động chiến dịch tại {$location['address']} nhằm hỗ trợ {$categoryName} thông qua việc gây quỹ và triển khai các hoạt động hỗ trợ trực tiếp. Mỗi sự đóng góp đều mang ý nghĩa lớn, góp phần giúp những hoàn cảnh khó khăn có cơ hội vươn lên.",

                    "Chiến dịch tại {$location['address']} được xây dựng với mong muốn đồng hành cùng {$categoryName} trong hành trình vượt qua khó khăn. Nguồn quỹ sẽ được sử dụng minh bạch, đúng mục đích nhằm mang lại hiệu quả thiết thực và lâu dài cho cộng đồng.",

                    "Hướng đến việc cải thiện đời sống cho {$categoryName}, chiến dịch được tổ chức tại {$location['address']} với sự tham gia của nhiều cá nhân và tổ chức thiện nguyện. Chúng tôi tin rằng sự sẻ chia sẽ giúp lan tỏa yêu thương và tạo nên những thay đổi tích cực trong xã hội.",

                    "Chiến dịch được triển khai tại {$location['address']} nhằm hỗ trợ {$categoryName} vượt qua những khó khăn trước mắt và hướng tới tương lai tốt đẹp hơn. Sự đóng góp của cộng đồng sẽ giúp mang lại hy vọng, niềm tin và cơ hội phát triển cho những người cần giúp đỡ.",
                ];
                DB::table('chien_dich_gay_quy')->insert([
                    'to_chuc_id' => $org->id,
                    'danh_muc_id' => $danhMuc->id,
                    'tai_khoan_gay_quy_id' => $org->tk_id,

                    'ten_chien_dich' => $tenChienDich,
                    'mo_ta' => collect($descriptions)->random(),

                    'hinh_anh' => json_encode($images),

                    'muc_tieu_tien' => $mucTieu,
                    'so_tien_da_nhan' => 0,

                    'ngay_ket_thuc' => $ngayKetThuc,

                    'vi_tri' => $location['address'],
                    'lat' => $location['lat'],
                    'lng' => $location['lng'],

                    'ma_noi_dung_ck' => 'CD' . strtoupper(Str::random(6)),

                    'trang_thai' => $trangThai,

                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }
        }
    }
}