<?php

namespace Database\Seeders;

use App\Services\GeocodingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;

class BaiDangSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        DB::table('bao_cao_bai_dang')->truncate();
        DB::table('thich_bai_dang')->truncate();
        DB::table('binh_luan_bai_dang')->truncate();
        DB::table('bai_dang')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        $nguoiDungIds = DB::table('nguoi_dung')
            ->where('trang_thai', 'HOAT_DONG')
            ->where('ten_tai_khoan', '!=', 'admin')
            ->pluck('id')
            ->toArray();

        $activeUsers = array_slice($nguoiDungIds, 0, 5);
        $inactiveUsers = array_slice($nguoiDungIds, -5);

        $normalUsers = array_diff($nguoiDungIds, $inactiveUsers);



        $locations = [
            ['dia_diem' => 'Hải Châu', 'lat' => 16.0471, 'lng' => 108.2068],
            ['dia_diem' => 'Hải Châu - Nguyễn Văn Linh', 'lat' => 16.0545, 'lng' => 108.2022],

            ['dia_diem' => 'Sơn Trà', 'lat' => 16.0720, 'lng' => 108.2470],
            ['dia_diem' => 'Sơn Trà - Võ Nguyên Giáp', 'lat' => 16.0800, 'lng' => 108.2500],

            ['dia_diem' => 'Ngũ Hành Sơn', 'lat' => 16.0000, 'lng' => 108.2700],
            ['dia_diem' => 'Ngũ Hành Sơn - Le Van Hien', 'lat' => 15.9900, 'lng' => 108.2600],

            ['dia_diem' => 'Liên Chiểu', 'lat' => 16.1200, 'lng' => 108.1300],
            ['dia_diem' => 'Liên Chiểu - Nguyễn Tất Thành', 'lat' => 16.1100, 'lng' => 108.1500],

            ['dia_diem' => 'Cẩm Lệ', 'lat' => 16.0200, 'lng' => 108.2000],
            ['dia_diem' => 'Thanh Khê', 'lat' => 16.0600, 'lng' => 108.1900],

            ['dia_diem' => 'Hà Nội', 'lat' => 21.0278, 'lng' => 105.8342],
            ['dia_diem' => 'Hải Phòng', 'lat' => 20.8449, 'lng' => 106.6881],
            ['dia_diem' => 'Quảng Ninh', 'lat' => 21.0064, 'lng' => 107.2925],
            ['dia_diem' => 'Bắc Ninh', 'lat' => 21.1214, 'lng' => 106.1110],
            ['dia_diem' => 'Hải Dương', 'lat' => 20.9373, 'lng' => 106.3145],
            ['dia_diem' => 'Nam Định', 'lat' => 20.4388, 'lng' => 106.1621],
            ['dia_diem' => 'Thái Bình', 'lat' => 20.4463, 'lng' => 106.3366],
            ['dia_diem' => 'Ninh Bình', 'lat' => 20.2506, 'lng' => 105.9744],
            ['dia_diem' => 'Thanh Hóa', 'lat' => 19.8067, 'lng' => 105.7851],
            ['dia_diem' => 'Nghệ An', 'lat' => 18.6796, 'lng' => 105.6813],

            ['dia_diem' => 'Huế', 'lat' => 16.4637, 'lng' => 107.5909],
            ['dia_diem' => 'Quảng Nam', 'lat' => 15.5737, 'lng' => 108.4740],
            ['dia_diem' => 'Quảng Ngãi', 'lat' => 15.1214, 'lng' => 108.8044],
            ['dia_diem' => 'Bình Định', 'lat' => 13.7820, 'lng' => 109.2196],
            ['dia_diem' => 'Phú Yên', 'lat' => 13.0882, 'lng' => 109.0929],
            ['dia_diem' => 'Khánh Hòa', 'lat' => 12.2585, 'lng' => 109.0526],
            ['dia_diem' => 'Ninh Thuận', 'lat' => 11.5658, 'lng' => 108.9886],
            ['dia_diem' => 'Bình Thuận', 'lat' => 10.9804, 'lng' => 108.2615],

            ['dia_diem' => 'Đắk Lắk', 'lat' => 12.7100, 'lng' => 108.2378],
            ['dia_diem' => 'Gia Lai', 'lat' => 13.8079, 'lng' => 108.1094],
            ['dia_diem' => 'Kon Tum', 'lat' => 14.3497, 'lng' => 108.0005],
            ['dia_diem' => 'Lâm Đồng', 'lat' => 11.9404, 'lng' => 108.4583],

            ['dia_diem' => 'Hồ Chí Minh', 'lat' => 10.8231, 'lng' => 106.6297],
            ['dia_diem' => 'Bình Dương', 'lat' => 11.3254, 'lng' => 106.4770],
            ['dia_diem' => 'Đồng Nai', 'lat' => 11.0686, 'lng' => 107.1676],
            ['dia_diem' => 'Long An', 'lat' => 10.6956, 'lng' => 106.2431],
            ['dia_diem' => 'Tiền Giang', 'lat' => 10.4493, 'lng' => 106.3420],
            ['dia_diem' => 'Bến Tre', 'lat' => 10.2434, 'lng' => 106.3756],
            ['dia_diem' => 'Vĩnh Long', 'lat' => 10.2530, 'lng' => 105.9722],
            ['dia_diem' => 'Cần Thơ', 'lat' => 10.0452, 'lng' => 105.7469],
            ['dia_diem' => 'An Giang', 'lat' => 10.5216, 'lng' => 105.1259],
            ['dia_diem' => 'Kiên Giang', 'lat' => 10.0125, 'lng' => 105.0809],
        ];

        function randomizeLatLng($lat, $lng)
        {
            $radius = mt_rand(100, 800);

            $radiusInDegrees = $radius / 111320;

            $u = mt_rand() / mt_getrandmax();
            $v = mt_rand() / mt_getrandmax();

            $w = $radiusInDegrees * sqrt($u);
            $t = 2 * pi() * $v;

            return [
                'lat' => $lat + $w * cos($t),
                'lng' => $lng + $w * sin($t),
            ];
        }
        function fakeRegion($lat, $lng)
        {
            return round($lat, 2) . '_' . round($lng, 2);
        }
        $rows = [];
        $now = now();

        $count = 1000;

        $imageMap = [
            'CHO' => [
                'Quần áo' => [
                    'posts/quan_ao_cho.jpg',
                    'posts/quan_ao_1_cho.jpg',
                    'posts/quan_ao_2_cho.jpg',
                ],
                'Quần áo mùa đông' => [
                    'posts/quan_ao_mua_dong_cho.png',
                    'posts/quan_ao_mua_dong_5_cho.jpg',
                    'posts/quan_ao_mua_dong_1_cho.png',
                    'posts/quan_ao_mua_dong_cho.jpg',
                    'posts/quan_ao_mua_dong_2.jpg',
                    'posts/quan_ao_mua_dong_3_cho.jpg',
                    'posts/quan_ao_mua_dong_2.png',

                ],
                'Quần áo mùa hè' => [
                    'posts/quan_ao_mua_he_1_cho.jpg',
                    'posts/quan_ao_mua_he_2_cho.png',
                    'posts/quan_ao_mua_he_1_cho.png',
                    'posts/ao_mua_he_1_cho.jpg',
                    'posts/ao_mua_he_1_cho.png',
                    'posts/ao_mua_he_2_cho.png',
                ],


                'Gạo' => ['posts/gao_cho.jpg', 'posts/gao_1_cho.jpg', 'posts/gao_2_cho.jpg', 'posts/gao_3_cho.jpg'],
                'Thùng Mì tôm' => ['posts/mi_tom_1_cho.jpg'],
                'Gạo + Mì' => ['posts/gao_mi_cho.jpg', 'posts/gao_cho.jpg', 'posts/mi_tom_1_cho.jpg'],
                'Rau củ' => ['posts/rau_cho.jpg'],
                'Sữa' => ['posts/sua_cho.jpg'],
                'Nhu yếu phẩm' => ['posts/nhu_yeu_pham_cho.jpg'],
                'Thực phẩm' => [
                    'posts/thuc_pham_1_cho.jpg',
                    'posts/gao_cho.jpg',
                    'posts/mi_tom_1_cho.jpg',
                    'posts/rau_cho.jpg',
                    'posts/sua_cho.jpg',
                    'posts/gao_mi_cho.jpg',
                ],
                'Bàn học' => [],
                'Ghế học sinh' => [],
                'Quạt điện' => [],
                'Bếp gas' => [],
                'Nồi niêu' => [],
                'Tủ lạnh' => [],
                'Máy giặt' => [],
                'Bàn ghế' => [],
                'Giường' => [],
                'Tủ quần áo' => [],

                'Sách bút' => ['posts/sach_but_cho.png', 'posts/vo_but_chi_cho.jpg', 'posts/but_cho.jpg'],
                'Cặp học sinh' => ['posts/cap_hoc_sinh_cho.png'],

                'Đồ gia dụng' => [
                    'posts/do_gia_dung_1_cho.jpg',
                    'posts/do_gia_dung_2_cho.jpg',
                    'posts/do_gia_dung_3_cho.jpg',
                    'posts/do_gia_dung_4_cho.jpg',
                ],
                'Nồi cơm' => ['posts/noi_com_cho.jpg'],

                'Xe máy' => ['posts/xe_may_1_cho.jpg', 'posts/xe_may_2_cho.jpg'],
                'Xe đạp' => ['posts/xe_dap_1_cho.png', 'posts/xe_dap_2_cho.png', 'posts/xe_dap_3_cho.jpg'],
            ],
            'NHAN' => [
                'Quần áo' => [
                    'posts/quan_ao_nhan.jpg',
                    'posts/quan_ao_1_nhan.jpg',
                ],
                'Quần áo mùa đông' => [
                    'posts/quan_ao_mua_dong_nhan.jpg',
                    'posts/quan_ao_mua_dong_1_nhan.jpg',
                    'posts/ao_mua_dong_nhan.jpg',
                ],
                'Quần áo mùa hè' => [
                    'posts/quan_ao_mua_he_nhan.jpg',
                    'posts/quan_ao_mua_he_1_nhan.jpg',
                ],
                'Gạo' => [
                    'posts/gao_nhan.jpg',
                    'posts/gao_1_nhan.jpg',
                ],
                'Thùng Mì tôm' => [
                    'posts/mi_tom_nhan.jpg',
                    'posts/mi_tom_1_nhan.jpg',
                ],
                'Gạo + Mì' => [
                    'posts/mi_tom_gao_nhan.jpg',
                    'posts/mi_gao_nhan.jpg',
                ],
                'Rau củ' => ['posts/rau_nhan.jpg'],
                'Sữa' => [
                    'posts/sua_nhan.jpg',
                    'posts/sua_nhan.png',
                ],
                'Nhu yếu phẩm' => [
                    'posts/nhu_yeu_pham_nhan.jpg',
                ],
                'Thực phẩm' => [],
                'Sách bút' => [
                    'posts/sach_but_nhan.jpg',
                    'posts/sach_but_1_nhan.jpg',
                ],
                'Cặp học sinh' => [
                    'posts/cap_hoc_sinh_nhan.jpg',
                ],
                'Đồ gia dụng' => [
                    'posts/do_gia_dung_nhan.jpg',
                ],
                'Nồi cơm' => [
                    'posts/noi_com_nhan.jpg',
                ],
                'Xe máy' => [],
                'Xe đạp' => [],
                'Bàn học' => [],
                'Ghế học sinh' => [],
                'Quạt điện' => [],
                'Bếp gas' => [],
                'Nồi niêu' => [],
                'Tủ lạnh' => [],
                'Máy giặt' => [],
                'Bàn ghế' => [],
                'Giường' => [],
                'Tủ quần áo' => [],
            ],
        ];

        for ($i = 1; $i <= $count; $i++) {

            $rand = rand(1, 100);
            if ($rand <= 30) {
                $nguoiDungId = Arr::random($activeUsers);
            } elseif ($rand <= 90) {
                $nguoiDungId = Arr::random($normalUsers);
            } else {
                $nguoiDungId = Arr::random($inactiveUsers);
            }

            $loaiBai = ($i % 2 === 0) ? 'CHO' : 'NHAN';
            if ($loaiBai === 'CHO') {
                $trangThai = rand(1, 100) <= 30 ? 'DA_TANG' : 'CON_TANG';
            } else {
                $trangThai = rand(1, 100) <= 30 ? 'DA_NHAN' : 'CON_NHAN';
            }
            if ($trangThai === 'DA_TANG' || $trangThai === 'DA_NHAN') {
                $createdAt = $now->copy()->subDays(rand(5, 15));
            } else {
                $createdAt = $now->copy()->subDays(rand(0, 5));
            }
            $location = Arr::random($locations);

            $randomLatLng = randomizeLatLng($location['lat'], $location['lng']);

            $chuDes = [
                'Quần áo',
                'Quần áo mùa đông',
                'Quần áo mùa hè',

                'Gạo',
                'Thùng Mì tôm',
                'Gạo + Mì',
                'Rau củ',
                'Sữa',
                'Thực phẩm',
                'Nhu yếu phẩm',

                'Sách bút',
                'Cặp học sinh',
                'Bàn học',
                'Ghế học sinh',

                'Đồ gia dụng',
                'Nồi cơm',
                'Quạt điện',
                'Bếp gas',
                'Nồi niêu',
                'Tủ lạnh',
                'Máy giặt',

                'Bàn ghế',
                'Giường',
                'Tủ quần áo',

                'Xe máy',
                'Xe đạp',
            ];
            $tenChuDe = Arr::random($chuDes);
            $diaDiem = $location['dia_diem'];

            $tieuDeSamples = [
                "Cho {$tenChuDe} còn dùng tốt",
                "Tặng {$tenChuDe} còn mới ~80%",
                "Có {$tenChuDe} dư cần cho lại",
                "Thanh lý {$tenChuDe} miễn phí",
                "Cho {$tenChuDe}, ai cần liên hệ",
            ];

            $tieuDeNhanSamples = [
                "Mình cần {$tenChuDe} gấp",
                "Ai có {$tenChuDe} cho mình xin với",
                "Đang thiếu {$tenChuDe}, mong được hỗ trợ",
                "Cần {$tenChuDe} để sử dụng",
                "Bạn nào có {$tenChuDe} không dùng nữa không ạ?",
            ];

            $tieuDe = $loaiBai === 'CHO'
                ? Arr::random($tieuDeSamples)
                : Arr::random($tieuDeNhanSamples);

            if ($loaiBai === 'NHAN') {

                $sentences = [
                    "Hiện tại mình đang cần {$tenChuDe}.",
                    "Do hoàn cảnh nên mình thiếu {$tenChuDe}.",
                    "Nếu ai có dư mình xin lại ạ.",
                    "Mình có thể qua lấy tận nơi.",
                ];

                $extra = [
                    "Mình ở {$diaDiem}.",
                    "Cảm ơn mọi người rất nhiều.",
                    "Thật sự rất cần lúc này.",
                ];
                $noise = [
                    "đang cần gấp",
                    "rất cần lúc này",
                    "mong được giúp đỡ",
                    "cần sử dụng sớm",
                ];

                $randomNoise = Arr::random($noise);
                shuffle($sentences);
                shuffle($extra);
                $take = rand(3, 6);
                $qty = rand(1, 10);
                $moTa = "Mình cần khoảng {$qty} {$tenChuDe}, {$randomNoise}. "
                    . implode(' ', array_merge(
                        array_slice($sentences, 0, rand(1, 3)),
                        array_slice($extra, 0, rand(1, 2))
                    ));
            } else {
                $sentences = [
                    "Mình có {$tenChuDe} không dùng nữa nên muốn cho lại.",
                    "Tình trạng còn khá tốt, dùng bình thường.",
                    "Dọn nhà nên dư {$tenChuDe}.",
                    "Ai cần thì mình tặng lại.",
                ];

                $extra = [
                    "Mình ở {$diaDiem}.",
                    "Có thể qua lấy trực tiếp.",
                    "Ưu tiên người thật sự cần.",
                    "Liên hệ mình sớm nhé.",
                ];
                $noise = [
                    "còn dùng tốt",
                    "gần như mới",
                    "dùng bình thường",
                    "không còn nhu cầu",
                    "còn khá ổn",
                ];
                shuffle($sentences);
                shuffle($extra);
                $randomNoise = Arr::random($noise);
                $qty = rand(1, 10);
                $moTa = "Mình có khoảng {$qty} {$tenChuDe}, {$randomNoise}. "
                    . implode(' ', array_merge(
                        array_slice($sentences, 0, rand(1, 3)),
                        array_slice($extra, 0, rand(1, 2))
                    ));
            }

            $hinhAnhArr = null;
            if (rand(1, 100) <= 70) {

                $imgs = $imageMap[$loaiBai][$tenChuDe]
                    ?? $imageMap[$loaiBai]['Đồ gia dụng']
                    ?? [];

                if (is_array($imgs) && $imgs !== []) {
                    shuffle($imgs);

                    if ($loaiBai === 'CHO') {
                        $takeImg = min(count($imgs), rand(1, 3));
                    } else {
                        $takeImg = min(count($imgs), rand(1, 2));
                    }

                    $hinhAnhArr = array_slice($imgs, 0, $takeImg);
                }
            }

            $rows[] = [
                'nguoi_dung_id' => $nguoiDungId,
                'loai_bai' => $loaiBai,
                'tieu_de' => $tieuDe,
                'mo_ta' => $moTa,
                'hinh_anh' => is_array($hinhAnhArr) ? json_encode($hinhAnhArr, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                'dia_diem' => $diaDiem,
                'region' => fakeRegion($randomLatLng['lat'], $randomLatLng['lng']),
                'so_luong' => 5 + ($i % 10),
                'trang_thai' => $trangThai,
                'lat' => $randomLatLng['lat'],
                'lng' => $randomLatLng['lng'],
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
        }
        $commentSamples = [
            "Mình rất cần cái này, bạn còn không ạ?",
            "Mình xin được không ạ?",
            "Bạn ở khu vực nào vậy?",
            "Mình quan tâm bài này",
            "Có thể lấy hôm nay không?",
        ];

        $likes = [];
        $comments = [];
        collect($rows)->chunk(200)->each(function ($chunk) {
            DB::table('bai_dang')->insert($chunk->toArray());
        });

        $postIds = DB::table('bai_dang')->pluck('id');

        foreach ($postIds as $postId) {
            $postOwner = DB::table('bai_dang')
                ->where('id', $postId)
                ->value('nguoi_dung_id');

            $commentUsers = collect($nguoiDungIds)
                ->reject(fn($id) => $id == $postOwner)
                ->shuffle()
                ->take(rand(2, 5));
            $baseTime = now()->subHours(rand(1, 24));
            foreach ($commentUsers as $uid) {

                $time = $baseTime->copy()->addMinutes(rand(2, 15));

                $comments[] = [
                    'bai_dang_id' => $postId,
                    'nguoi_dung_id' => $uid,
                    'noi_dung' => Arr::random($commentSamples),
                    'created_at' => $time,
                    'updated_at' => $time,
                ];
            }
            $likeUsers = collect($nguoiDungIds)->shuffle()->take(rand(2, 5));

            foreach ($likeUsers as $uid) {
                $likes[] = [
                    'bai_dang_id' => $postId,
                    'nguoi_dung_id' => $uid,
                    'created_at' => now()->subMinutes(rand(1, 1000)),
                ];
            }
        }

        collect($likes)->chunk(500)->each(function ($chunk) {
            DB::table('thich_bai_dang')->insert($chunk->toArray());
        });

        collect($comments)->chunk(500)->each(function ($chunk) {
            DB::table('binh_luan_bai_dang')->insert($chunk->toArray());
        });
    }
}
