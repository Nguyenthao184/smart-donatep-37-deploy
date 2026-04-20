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

        BaiDang::query()->select(['id', 'tieu_de', 'mo_ta'])->chunkById(200, function ($posts) use ($svc) {
            foreach ($posts as $post) {
                $suggestions = $svc->suggest((string) $post->tieu_de, (string) $post->mo_ta);

                DanhMucBaiDang::where('bai_dang_id', $post->id)->delete();
                foreach ($suggestions as $s) {
                    DanhMucBaiDang::create([
                        'bai_dang_id' => $post->id,
                        'danh_muc_code' => $s['danh_muc_code'],
                        'is_primary' => (bool) $s['is_primary'],
                        'confidence' => (float) $s['confidence'],
                    ]);
                }
            }
        });
    }
}

