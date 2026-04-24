<?php

namespace App\Jobs;

use App\Models\BaiDang;
use App\Models\DanhMucBaiDang;
use App\Models\GhepNoiAi;
use App\Services\AiMatchingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FindPostMatches implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(
        private int $postId
    ) {}

    public function handle(AiMatchingService $aiMatchingService): void
    {

        try {
            $source = BaiDang::with('nguoiDung')->find($this->postId);
            if (!$source) {
                Log::warning('FindPostMatches: Post not found', ['post_id' => $this->postId]);
                return;
            }
            Log::info('FindPostMatches: Starting', ['post_id' => $this->postId]);
            $targetLoaiBai = $source->loai_bai === 'CHO' ? 'NHAN' : 'CHO';
            $targetLoaiBai = $source->loai_bai === 'CHO' ? 'NHAN' : 'CHO';
            $maxRadiusKm = 20.0;
            $candidatePrelimit = 120;
            $aiInputLimit = 40;

            $candidatesQuery = BaiDang::query()
                ->with('nguoiDung')
                ->select('bai_dang.*')
                ->where('loai_bai', $targetLoaiBai)
                ->whereNotIn('trang_thai', ['DA_NHAN', 'DA_TANG'])
                ->where('nguoi_dung_id', '!=', $source->nguoi_dung_id);
            if (!empty($source->region)) {
                $nearRegions = $this->neighborRegions($source->region);
                $regions = array_values(array_unique(array_filter(array_merge([$source->region], $nearRegions))));
                $candidatesQuery->whereIn('region', $regions);
            }
            if ($source->lat !== null && $source->lng !== null) {
                $distanceExpr = "(6371 * acos( cos(radians(?)) * cos(radians(bai_dang.lat)) * cos(radians(bai_dang.lng) - radians(?)) + sin(radians(?)) * sin(radians(bai_dang.lat)) ))";
                $candidatesQuery->whereNotNull('bai_dang.lat')
                    ->whereNotNull('bai_dang.lng')
                    ->whereRaw("$distanceExpr <= ?", [
                        $source->lat,
                        $source->lng,
                        $source->lat,
                        $maxRadiusKm,
                    ]);
                $distanceExprSelect = "(6371 * acos( cos(radians(" . (float) $source->lat . ")) * cos(radians(bai_dang.lat)) * cos(radians(bai_dang.lng) - radians(" . (float) $source->lng . ")) + sin(radians(" . (float) $source->lat . ")) * sin(radians(bai_dang.lat)) ))";
                $candidatesQuery->addSelect(DB::raw($distanceExprSelect . " as distance_km"))
                    ->orderBy('distance_km', 'asc');
            } else {
                $candidatesQuery->orderByDesc('created_at');
            }
            $candidates = $candidatesQuery->limit($candidatePrelimit)->get();
            if ($source->lat !== null && $source->lng !== null) {
                $candidates = $candidates->sortBy('distance_km')->take($aiInputLimit)->values();
            } else {
                $candidates = $candidates->take($aiInputLimit)->values();
            }
            if ($candidates->isEmpty()) {
                Log::info('FindPostMatches: No candidates found', ['post_id' => $this->postId]);
                return;
            }
            $allPosts = collect([$source])->concat($candidates);
            $danhMucMap = $this->loadDanhMucMap($allPosts->pluck('id')->all());
            $postsPayload = [];

            $postsPayload[] = $this->toAiPost($source, $danhMucMap);
            foreach ($candidates as $cand) {
                $postsPayload[] = $this->toAiPost($cand, $danhMucMap);
            }
            if (count($postsPayload) < 2) {
                Log::info('FindPostMatches: Not enough payload', ['post_id' => $this->postId]);
                return;
            }
            $payload = [
                'post_id' => $source->id,
                'posts' => $postsPayload,
                'user_has_address' => !empty($source->nguoiDung?->dia_chi),
                'user_interests' => [],
            ];
            $matches = $aiMatchingService->match($payload);
            Log::info('FindPostMatches: Match result', [
                'post_id' => $this->postId,
                'count' => count($matches),
            ]);
            foreach ($matches as $item) {
                GhepNoiAi::updateOrCreate(
                    [
                        'bai_dang_nguon_id' => $source->id,
                        'bai_dang_phu_hop_id' => (int) $item['post_id'],
                    ],
                    [
                        'diem_phu_hop' => (float) $item['score'],
                        'trang_thai' => 'GHEP_NOI',
                    ]
                );
            }

            Log::info('FindPostMatches: Completed', ['post_id' => $this->postId]);
        } catch (\Throwable $e) {
            Log::error('FindPostMatches failed', [
                'post_id' => $this->postId,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
    private function toAiPost(BaiDang $post, array $danhMucMap = []): array
    {
        $m = $danhMucMap[(int) $post->id] ?? ['primary' => null, 'all' => []];
        return [
            'id' => (int) $post->id,
            'loai_bai' => $post->loai_bai,
            'tieu_de' => $post->tieu_de,
            'mo_ta' => $post->mo_ta,
            'lat' => $post->lat,
            'lng' => $post->lng,
            'region' => $post->region,
            'created_at' => $post->created_at?->toIso8601String(),
            'danh_muc' => $m['primary'],
            'danh_mucs' => $m['all'],
        ];
    }
    private function loadDanhMucMap(array $postIds): array
    {
        if (!$postIds) {
            return [];
        }
        $rows = DanhMucBaiDang::query()
            ->whereIn('bai_dang_id', $postIds)
            ->orderByDesc('is_primary')
            ->orderByDesc('confidence')
            ->get(['bai_dang_id', 'danh_muc_code', 'is_primary', 'confidence']);
        $map = [];
        foreach ($postIds as $id) {
            $map[(int) $id] = ['primary' => null, 'all' => []];
        }
        foreach ($rows as $row) {
            $id = (int) $row->bai_dang_id;
            $code = (string) $row->danh_muc_code;
            if (!isset($map[$id])) {
                $map[$id] = ['primary' => null, 'all' => []];
            }
            if (!in_array($code, $map[$id]['all'], true)) {
                $map[$id]['all'][] = $code;
            }
            if ($row->is_primary && $map[$id]['primary'] === null) {
                $map[$id]['primary'] = $code;
            }
        }
        return $map;
    }
    private function neighborRegions(string $region): array
    {
        $parts = explode('_', $region);
        if (count($parts) !== 2 || !is_numeric($parts[0]) || !is_numeric($parts[1])) {
            return [];
        }
        $lat = round((float) $parts[0], 2);
        $lng = round((float) $parts[1], 2);
        $step = 0.01;
        $rows = [];
        foreach ([-2 * $step, -$step, 0.0, $step, 2 * $step] as $dLat) {
            foreach ([-2 * $step, -$step, 0.0, $step, 2 * $step] as $dLng) {
                if ($dLat == 0.0 && $dLng == 0.0) {
                    continue;
                }
                $rows[] = number_format($lat + $dLat, 2, '.', '') . '_' . number_format($lng + $dLng, 2, '.', '');
            }
        }
        return $rows;
    }
}
