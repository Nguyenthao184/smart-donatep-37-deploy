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

        // Mỗi chiến dịch một tỉnh/thành khác nhau (17 địa điểm / 17 chiến dịch)
        $diaDiemTheoTinh = [
            ['tinh' => 'Đà Nẵng', 'address' => '12 Bạch Đằng, Hải Châu, Đà Nẵng', 'lat' => 16.080083723726315, 'lng' => 108.22348595962076],
            ['tinh' => 'Hà Nội', 'address' => '10 Tràng Tiền, Hoàn Kiếm, Hà Nội', 'lat' => 21.02493252622818, 'lng' => 105.85632417493993],
            ['tinh' => 'TP.HCM', 'address' => '36 Bis/1 Lê Lợi, Sài Gòn, Hồ Chí Minh', 'lat' => 10.774839611501271, 'lng' => 106.70037501521871],
            ['tinh' => 'Cần Thơ', 'address' => '3 Đ. Hai Bà Trưng, Ninh Kiều, Cần Thơ', 'lat' => 10.02908890925625, 'lng' => 105.78735255279162],
            ['tinh' => 'Huế', 'address' => '20 Hùng Vương, Thuận Hóa, Huế', 'lat' => 16.464980313129036, 'lng' => 107.59326823034212],
            ['tinh' => 'Hải Phòng', 'address' => '80 Lạch Tray, Lê Chân, Hải Phòng', 'lat' => 20.84812565823585, 'lng' => 106.69039821535124],
            ['tinh' => 'Khánh Hòa', 'address' => '18 Trần Phú, Nha Trang, Khánh Hòa', 'lat' => 12.241647983355051, 'lng' => 109.19622384681318],
            ['tinh' => 'Lâm Đồng', 'address' => '1 Trần Hưng Đạo, Đức Trọng, Lâm Đồng', 'lat' => 11.728826640127052, 'lng' => 108.37595437192351],
            ['tinh' => 'Bà Rịa – Vũng Tàu', 'address' => '9 Thùy Vân, Vũng Tàu, Hồ Chí Minh', 'lat' => 10.346926286402653, 'lng' => 107.09490488741146],
            ['tinh' => 'Đồng Nai', 'address' => '45 Đ. Đồng Khởi, Trảng Dài, Đồng Nai', 'lat' => 10.968184167389634, 'lng' => 106.85385455961793],
            ['tinh' => 'Bình Định', 'address' => '88 Nguyễn Tất Thành, Quy Nhơn, Bình Định', 'lat' => 13.778639356424122, 'lng' => 109.22200717047896],
            ['tinh' => 'Nghệ An', 'address' => '72 V.I Lê Nin, Vinh Phú, Nghệ An', 'lat' => 18.68739742875626, 'lng' => 105.69193703392024],
            ['tinh' => 'Thái Nguyên', 'address' => '45 Hoàng Văn Thụ, Đức Xuân, Thái Nguyên', 'lat' => 22.167287997559278, 'lng' => 105.84749607330919],
            ['tinh' => 'Ninh Bình', 'address' => '12 Điện Biên, Nam Định, Ninh Bình', 'lat' => 20.429051513906348, 'lng' => 106.16834996157536],
            ['tinh' => 'Đắk Lắk', 'address' => '55 Nguyễn Tất Thành, Buôn Ma Thuột, Đắk Lắk', 'lat' => 12.685824013653367, 'lng' => 108.05291117245118],
            ['tinh' => 'Gia Lai', 'address' => '18 Hùng Vương, Phú Túc, Gia Lai', 'lat' => 13.196386529837053, 'lng' => 108.68700429100097],
            ['tinh' => 'An Giang', 'address' => '22 Trần Hưng Đạo, Long Xuyên, An Giang', 'lat' => 10.35930399746741, 'lng' => 105.4550496854255],
        ];

        // 12 chiến hoạt động + mỗi trạng thái khác đúng 1 chiến
        $keHoachTrangThai = array_merge(
            array_fill(0, 12, 'HOAT_DONG'),
            ['TAM_DUNG', 'DA_KET_THUC', 'HOAN_THANH', 'CHO_XU_LY', 'TU_CHOI'],
        );

        $orgList = $orgs->values();
        $orgCount = $orgList->count();
        if ($orgCount === 0) {
            return;
        }

        for ($slot = 0; $slot < count($diaDiemTheoTinh); $slot++) {
            $org = $orgList[$slot % $orgCount];

            // lấy danh mục theo tổ chức
            $categoryName = $orgCategoryMap[$org->ten_to_chuc] ?? null;
            if (!$categoryName) {
                continue;
            }

            // lấy danh mục trong DB
            $danhMuc = DB::table('danh_muc')
                ->where('ten_danh_muc', $categoryName)
                ->first();

            if (!$danhMuc) {
                continue;
            }

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

            $location = $diaDiemTheoTinh[$slot];

            // Tổng số ảnh trong folder
            $totalFiles = count($files);

            // Lấy ảnh đầu tiên KHÔNG TRÙNG theo index
            $firstImage = $files[$slot % $totalFiles];

            // tối đa 4 ảnh / chiến dịch
            $maxImages = min(4, $totalFiles);

            // luôn có ảnh đầu tiên
            $remainingSlots = $maxImages - 1;

            // lấy thêm ảnh khác không trùng
            $otherImages = collect($files)
                ->reject(fn ($f) => $f === $firstImage)
                ->shuffle()
                ->take($remainingSlots);

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

            $trangThai = $keHoachTrangThai[$slot];
                switch ($trangThai) {
                    case 'HOAT_DONG':
                        // đang chạy → ngày kết thúc ở tương lai
                        $createdAt = now()->subDays(rand(30, 120));
                        $ngayKetThuc = now()->addDays(rand(15, 90));
                        break;

                    case 'TAM_DUNG':
                        // tạm dừng nhưng chưa hết hạn
                        $createdAt = now()->subDays(rand(60, 180));
                        $ngayKetThuc = now()->addDays(rand(15, 60));
                        break;

                    case 'CHO_XU_LY':
                        // mới tạo → còn hạn xa
                        $createdAt = now()->subDays(rand(1, 7));
                        $ngayKetThuc = now()->addDays(rand(60, 180));
                        break;

                    case 'DA_KET_THUC':
                        // đã kết thúc → ngày trong quá khứ
                        $ngayKetThuc = now()->subDays(rand(5, 30));
                        $createdAt = (clone $ngayKetThuc)
                            ->subDays(rand(60, 180));
                        break;

                    case 'HOAN_THANH':
                        // hoàn thành sớm trước hạn
                        $createdAt = now()->subDays(rand(60, 150));
                        // deadline vẫn còn
                        $ngayKetThuc = now()->addDays(rand(15, 90));
                        break;

                    case 'TU_CHOI':
                        $createdAt = now()->subDays(rand(1, 15));
                        $ngayKetThuc = now()->addDays(rand(30, 90));
                        break;

                    default:
                        $createdAt = now()->subDays(rand(30, 60));
                        $ngayKetThuc = now()->addDays(rand(30, 60));
                        break;
                }

                $tenChienDich = collect($nameTemplates[$categoryName])->random();

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