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

    // =========================
    // ĐÀ NẴNG
    // =========================
    [
        'dia_diem' => 'Phường Hải Châu 1, Quận Hải Châu, Đà Nẵng',
        'lat' => 16.0678,
        'lng' => 108.2208
    ],
    [
        'dia_diem' => 'Phường Hòa Cường Bắc, Quận Hải Châu, Đà Nẵng',
        'lat' => 16.0415,
        'lng' => 108.2215
    ],
    [
        'dia_diem' => 'Đường Nguyễn Văn Linh, Quận Hải Châu, Đà Nẵng',
        'lat' => 16.0545,
        'lng' => 108.2022
    ],

    [
        'dia_diem' => 'Phường Mỹ An, Quận Ngũ Hành Sơn, Đà Nẵng',
        'lat' => 16.0389,
        'lng' => 108.2473
    ],
    [
        'dia_diem' => 'Phường Khuê Mỹ, Quận Ngũ Hành Sơn, Đà Nẵng',
        'lat' => 16.0175,
        'lng' => 108.2520
    ],
    [
        'dia_diem' => 'Đường Lê Văn Hiến, Quận Ngũ Hành Sơn, Đà Nẵng',
        'lat' => 15.9900,
        'lng' => 108.2600
    ],

    [
        'dia_diem' => 'Phường Hòa An, Quận Cẩm Lệ, Đà Nẵng',
        'lat' => 16.0312,
        'lng' => 108.1885
    ],
    [
        'dia_diem' => 'Phường Thanh Khê Đông, Quận Thanh Khê, Đà Nẵng',
        'lat' => 16.0704,
        'lng' => 108.1917
    ],

    [
        'dia_diem' => 'Phường An Hải Bắc, Quận Sơn Trà, Đà Nẵng',
        'lat' => 16.0672,
        'lng' => 108.2365
    ],

    // =========================
    // HÀ NỘI
    // =========================
    [
        'dia_diem' => 'Phường Dịch Vọng, Quận Cầu Giấy, Hà Nội',
        'lat' => 21.0368,
        'lng' => 105.7902
    ],
    [
        'dia_diem' => 'Phường Láng Hạ, Quận Đống Đa, Hà Nội',
        'lat' => 21.0147,
        'lng' => 105.8142
    ],
    [
        'dia_diem' => 'Phường Minh Khai, Quận Hai Bà Trưng, Hà Nội',
        'lat' => 20.9987,
        'lng' => 105.8635
    ],

    // =========================
    // HẢI PHÒNG
    // =========================
    [
        'dia_diem' => 'Phường Lạch Tray, Quận Ngô Quyền, Hải Phòng',
        'lat' => 20.8449,
        'lng' => 106.6881
    ],

    // =========================
    // QUẢNG NINH
    // =========================
    [
        'dia_diem' => 'Phường Bãi Cháy, TP Hạ Long, Quảng Ninh',
        'lat' => 20.9511,
        'lng' => 107.0438
    ],

    // =========================
    // BẮC NINH
    // =========================
    [
        'dia_diem' => 'Phường Võ Cường, TP Bắc Ninh, Bắc Ninh',
        'lat' => 21.1861,
        'lng' => 106.0763
    ],

    // =========================
    // NGHỆ AN
    // =========================
    [
        'dia_diem' => 'Phường Hưng Bình, TP Vinh, Nghệ An',
        'lat' => 18.6796,
        'lng' => 105.6813
    ],

    // =========================
    // HUẾ
    // =========================
    [
        'dia_diem' => 'Phường Phú Hội, TP Huế, Thừa Thiên Huế',
        'lat' => 16.4637,
        'lng' => 107.5909
    ],

    // =========================
    // QUẢNG NAM
    // =========================
    [
        'dia_diem' => 'Phường Minh An, TP Hội An, Quảng Nam',
        'lat' => 15.8801,
        'lng' => 108.3380
    ],

    // =========================
    // BÌNH ĐỊNH
    // =========================
    [
        'dia_diem' => 'Phường Ghềnh Ráng, TP Quy Nhơn, Bình Định',
        'lat' => 13.7563,
        'lng' => 109.2167
    ],

    // =========================
    // ĐẮK LẮK
    // =========================
    [
        'dia_diem' => 'Phường Tân Lợi, TP Buôn Ma Thuột, Đắk Lắk',
        'lat' => 12.6797,
        'lng' => 108.0382
    ],

    // =========================
    // GIA LAI
    // =========================
    [
        'dia_diem' => 'Phường Diên Hồng, TP Pleiku, Gia Lai',
        'lat' => 13.9833,
        'lng' => 108.0000
    ],

    // =========================
    // HỒ CHÍ MINH
    // =========================
    [
        'dia_diem' => 'Phường 13, Quận Tân Bình, Hồ Chí Minh',
        'lat' => 10.8012,
        'lng' => 106.6401
    ],
    [
        'dia_diem' => 'Phường Linh Trung, TP Thủ Đức, Hồ Chí Minh',
        'lat' => 10.8701,
        'lng' => 106.8032
    ],
    [
        'dia_diem' => 'Phường Hiệp Bình Chánh, TP Thủ Đức, Hồ Chí Minh',
        'lat' => 10.8423,
        'lng' => 106.7319
    ],
    [
        'dia_diem' => 'Phường 5, Quận Gò Vấp, Hồ Chí Minh',
        'lat' => 10.8391,
        'lng' => 106.6698
    ],

    // =========================
    // BÌNH DƯƠNG
    // =========================
    [
        'dia_diem' => 'Phường Phú Hòa, TP Thủ Dầu Một, Bình Dương',
        'lat' => 11.0042,
        'lng' => 106.6504
    ],

    // =========================
    // ĐỒNG NAI
    // =========================
    [
        'dia_diem' => 'Phường Tân Hiệp, TP Biên Hòa, Đồng Nai',
        'lat' => 10.9574,
        'lng' => 106.8426
    ],

    // =========================
    // CẦN THƠ
    // =========================
    [
        'dia_diem' => 'Phường Xuân Khánh, Quận Ninh Kiều, Cần Thơ',
        'lat' => 10.0301,
        'lng' => 105.7694
    ],

    // =========================
    // AN GIANG
    // =========================
    [
        'dia_diem' => 'Phường Mỹ Bình, TP Long Xuyên, An Giang',
        'lat' => 10.3864,
        'lng' => 105.4352
    ],

    // =========================
    // KIÊN GIANG
    // =========================
    [
        'dia_diem' => 'Phường Vĩnh Thanh, TP Rạch Giá, Kiên Giang',
        'lat' => 10.0125,
        'lng' => 105.0809
    ],
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

        $count = 500;

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
                    'posts/quan_ao_mua_dong_2.jpg',
                    'posts/quan_ao_mua_dong_3_cho.jpg',
                    'posts/quan_ao_mua_dong_2.png',

                ],
                'Quần áo mùa hè' => [
                    'posts/quan_ao_mua_he_1_cho.jpg',
                    'posts/quan_ao_mua_he_2_cho.png',

                    'posts/ao_mua_he_1_cho.jpg',
                    'posts/ao_mua_he_1_cho.png',
                    'posts/ao_mua_he_2_cho.png',
                ],


                'Gạo' => ['posts/gao_cho.jpg', 'posts/gao_1_cho.jpg', 'posts/gao_2_cho.jpg', 'posts/gao_3_cho.jpg'],
                'Thùng Mì tôm' => ['posts/mi_tom_1_cho.jpg', 'posts/mi_tom_cho.jpg', 'posts/mi_tom_2_cho.jpg'],
                'Gạo + Mì' => [ 'posts/gao_cho.jpg', 'posts/mi_tom_1_cho.jpg'],
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
                    'posts/thuc_pham_2_cho.jpg',
                ],
                'Bàn học' => ['posts/ban_hoc_cho.jpg', 'posts/ban_hoc_1_cho.jpg', 'posts/ban_hoc_2_cho.jpg'],
                'Ghế học sinh' => ['posts/ghe_hoc_sinh_cho.jpg', 'posts/ghe_hoc_sinh_1_cho.jpg', 'posts/ghe_hoc_sinh_2_cho.jpg'],
                'Quạt điện' => ['posts/quat_dien_cho.jpg', 'posts/quat_dien_1_cho.jpg'],
                'Bếp gas' => ['posts/bep_gas_cho.png', 'posts/bep_gas_1_cho.jpg'],
                'Nồi niêu' => ['posts/noi_nieu_cho.jpg', 'posts/noi_nieu_1_cho.jpg'],
                'Tủ lạnh' => ['posts/tu_lanh_cho.jpg', 'posts/tu_lanh_1_cho.jpg'],
                'Máy giặt' => ['posts/may_giat_cho.jpg', 'posts/may_giat_1_cho.jpg'],
                'Bàn ghế' => ['posts/ban_ghe_cho.jpg', 'posts/ban_ghe_1_cho.jpg'],
                'Giường' => ['posts/giuong_cho.jpg', 'posts/giuong_1_cho.jpg'],
                'Tủ quần áo' => ['posts/tu_quan_ao_cho.jpg', 'posts/tu_quan_ao_1_cho.jpg'],

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
                    'posts/mi_tom_2_nhan.jpg',
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
                'Xe máy' => ['posts/xe_may_nhan.jpg', 'posts/xe_may_1_nhan.jpg'],
                'Xe đạp' => ['posts/xe_dap_nhan.jpg', 'posts/xe_dap_1_nhan.jpg'],
                'Bàn học' => ['posts/ban_hoc_nhan.jpg', 'posts/ban_hoc_1_nhan.png', 'posts/ban_hoc_2_nhan.jpg'],
                'Ghế học sinh' => ['posts/ghe_hoc_sinh_nhan.jpg', 'posts/ghe_hoc_sinh_1_nhan.jpg', 'posts/ghe_hoc_sinh_2_nhan.jpg'],
                'Quạt điện' => ['posts/quat_dien_nhan.jpg', 'posts/quat_dien_1_nhan.jpg'],
                'Bếp gas' => ['posts/bep_gas_nhan.jpg', 'posts/bep_gas_1_nhan.jpg'],
                'Nồi niêu' => ['posts/noi_nieu_nhan.jpg', 'posts/noi_nieu_1_nhan.jpg'],
                'Tủ lạnh' => ['posts/tu_lanh_nhan.jpg', 'posts/tu_lanh_1_nhan.jpg'],
                'Máy giặt' => ['posts/may_giat_nhan.jpg'],
                'Bàn ghế' => ['posts/ban_ghe_nhan.jpg'],
                'Giường' => ['posts/giuong_nhan.jpg'],
                'Tủ quần áo' => ['posts/tu_quan_ao_nhan.jpg'],
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
                $selectedSentences = Arr::random($sentences, rand(1, min(3, count($sentences))));
                $selectedExtra = Arr::random($extra, rand(1, min(2, count($extra))));
                $take = rand(3, 6);
                $qty = rand(1, 10);
                $moTa = "Mình cần khoảng {$qty} {$tenChuDe}, {$randomNoise}. "
                    . implode(' ', array_merge(
                        (array) $selectedSentences,
                        (array) $selectedExtra
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
                $selectedSentences = Arr::random($sentences, rand(1, min(3, count($sentences))));

                $selectedExtra = Arr::random($extra, rand(1, min(2, count($extra))));
                $randomNoise = Arr::random($noise);
                $qty = rand(1, 10);
                $moTa = "Mình có khoảng {$qty} {$tenChuDe}, {$randomNoise}. "
                    . implode(' ', array_merge(
                        (array) $selectedSentences,
                        (array) $selectedExtra,
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
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('bai_dang')->insert($chunk);
        }

        $posts = DB::table('bai_dang')
            ->select('id', 'nguoi_dung_id')
            ->get();

        foreach ($posts as $post) {

            $postId = $post->id;
            $postOwner = $post->nguoi_dung_id;

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
                    'created_at' => $now->copy()->subMinutes(rand(1, 1000)),
                ];
            }
            if (count($likes) >= 500) {
                DB::table('thich_bai_dang')->insert($likes);
                $likes = [];
            }

            if (count($comments) >= 500) {
                DB::table('binh_luan_bai_dang')->insert($comments);
                $comments = [];
            }
        }
        if (!empty($likes)) {
            DB::table('thich_bai_dang')->insert($likes);
        }

        if (!empty($comments)) {
            DB::table('binh_luan_bai_dang')->insert($comments);
        }

    }
}
