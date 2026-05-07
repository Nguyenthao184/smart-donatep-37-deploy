<?php

namespace Database\Seeders;

use App\Models\BaiDang;
use App\Models\DanhMucBaiDang;
use App\Services\DanhMucSuggestionService;
use Illuminate\Database\Seeder;

class DanhMucBaiDangSeeder extends Seeder
{
    public function run(): void
    {
        /** @var DanhMucSuggestionService $svc */
        $svc = app(DanhMucSuggestionService::class);

        BaiDang::query()
            ->select(['id', 'tieu_de', 'mo_ta'])
            ->chunkById(200, function ($posts) use ($svc) {

                $insertData = [];
                $postIds = [];

                foreach ($posts as $post) {

                    $postIds[] = $post->id;

                    $suggestions = $svc->suggest(
                        (string) $post->tieu_de,
                        (string) $post->mo_ta
                    );

                    foreach ($suggestions as $s) {
                        $insertData[] = [
                            'bai_dang_id' => $post->id,
                            'danh_muc_code' => $s['danh_muc_code'],
                            'is_primary' => (bool) $s['is_primary'],
                            'confidence' => (float) $s['confidence'],
                        ];
                    }
                }

                DanhMucBaiDang::whereIn('bai_dang_id', $postIds)->delete();

                if (!empty($insertData)) {
                    DanhMucBaiDang::insert($insertData);
                }
            });
    }
}
