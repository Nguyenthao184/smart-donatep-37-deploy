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
            ['dia_diem' => 'Hà Nội - Cầu Giấy', 'lat' => 21.0360, 'lng' => 105.8000],

            ['dia_diem' => 'Hồ Chí Minh', 'lat' => 10.8231, 'lng' => 106.6297],
            ['dia_diem' => 'Hồ Chí Minh - Thủ Đức', 'lat' => 10.8500, 'lng' => 106.6200],
        ];

        // 🎯 Hàm random lệch vị trí (~500m - 1km)
        function randomizeLatLng($lat, $lng)
        {
            return [
                'lat' => $lat + (mt_rand(-100, 100) / 10000),
                'lng' => $lng + (mt_rand(-100, 100) / 10000),
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
                'Mì tôm' => ['posts/mi_tom_1_cho.jpg'],
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
                'Mì tôm' => [
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
                'Mì tôm',
                'Gạo + Mì',
                'Rau củ',
                'Sữa',
                'Nhu yếu phẩm',
                'Thực phẩm',
                'Sách bút',
                'Cặp học sinh',
                'Đồ gia dụng',
                'Nồi cơm',
                'Xe máy',
                'Xe đạp',
            ];
            $tenChuDe = Arr::random($chuDes);
            $diaDiem = $location['dia_diem'];

            $tieuDeSamples = [
                "Cho {$tenChuDe} còn dùng tốt",
                "Mình tặng {$tenChuDe} cho ai cần",
                "Có {$tenChuDe} không dùng tới",
                "Ai cần {$tenChuDe} thì liên hệ mình",
                "Còn dư {$tenChuDe}, muốn cho lại",
            ];

            $tieuDeNhanSamples = [
                "Mình cần {$tenChuDe}",
                "Ai có {$tenChuDe} cho mình xin",
                "Đang cần {$tenChuDe} gấp",
                "Xin {$tenChuDe} để dùng",
                "Bạn nào có {$tenChuDe} không dùng không ạ?",
            ];

            $tieuDe = $loaiBai === 'CHO'
                ? Arr::random($tieuDeSamples)
                : Arr::random($tieuDeNhanSamples);
            if ($loaiBai === 'NHAN') {
                $sentences = [
                    "Mình đang cần {$tenChuDe}, ai có dư cho mình xin với ạ.",
                    "Hiện tại mình thiếu {$tenChuDe}, mong được hỗ trợ.",
                    "Bạn nào có {$tenChuDe} không dùng tới thì cho mình xin nhé.",
                    "Mình cần {$tenChuDe}, có thể tự qua lấy.",
                    "Đang hơi gấp nên cần {$tenChuDe}, cảm ơn mọi người nhiều.",
                ];
                shuffle($sentences);
                $take = rand(3, 6);
                $moTa = implode(' ', array_slice($sentences, 0, $take));
            } else {
                $sentences = [
                    "Mình có {$tenChuDe} không dùng nữa nên muốn cho lại.",
                    "Còn {$tenChuDe} khá ổn, bạn nào cần thì mình tặng.",
                    "Dọn nhà nên dư {$tenChuDe}, ai cần lấy giúp mình.",
                    "Mình muốn cho lại {$tenChuDe}, ưu tiên người cần.",
                    "Có {$tenChuDe}, còn dùng tốt, tặng lại cho bạn nào cần.",
                ];
                $extra = [
                    "Mình ở {$diaDiem}.",
                    "Có thể hẹn giờ linh hoạt.",
                    "Ai cần thật sự thì liên hệ mình nhé.",
                    "Ưu tiên bạn nào gần khu vực.",
                    "Cảm ơn mọi người.",
                ];
                shuffle($sentences);
                shuffle($extra);

                $moTa = implode(' ', array_merge(
                    array_slice($sentences, 0, rand(1, 3)),
                    array_slice($extra, 0, rand(1, 2))
                ));;
            }

            $hinhAnhArr = null;
            if (rand(1, 100) <= 70) {

                $imgs = $imageMap[$tenChuDe] ?? null;

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
