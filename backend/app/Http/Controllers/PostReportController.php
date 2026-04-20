<?php

namespace App\Http\Controllers;

use App\Http\Requests\Post\StorePostReportRequest;
use App\Http\Requests\Post\UpdatePostReportRequest;
use App\Models\BaiDang;
use App\Models\BaoCaoBaiDang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PostReportController extends Controller
{
    private const DEDUPE_HOURS = 24;

    /**
     * POST /api/posts/{id}/report — báo cáo vi phạm / gian lận bài đăng.
     */
    public function store(StorePostReportRequest $request, int $id)
    {
        $post = BaiDang::query()->findOrFail($id);
        $userId = (int) Auth::id();

        if ((int) $post->nguoi_dung_id === $userId) {
            return response()->json(['message' => 'Không thể báo cáo bài đăng của chính mình.'], 422);
        }

        $dup = BaoCaoBaiDang::query()
            ->where('bai_dang_id', $id)
            ->where('nguoi_to_cao_id', $userId)
            ->where('trang_thai', 'CHO_XU_LY')
            ->where('created_at', '>=', now()->subHours(self::DEDUPE_HOURS))
            ->exists();

        if ($dup) {
            return response()->json([
                'message' => 'Bạn đã gửi báo cáo cho bài này và đang chờ xử lý.',
            ], 422);
        }

        $data = $request->validated();
        $baoCao = BaoCaoBaiDang::query()->create([
            'bai_dang_id' => $id,
            'nguoi_to_cao_id' => $userId,
            'ly_do' => $data['ly_do'],
            'mo_ta' => $data['mo_ta'] ?? null,
            'trang_thai' => 'CHO_XU_LY',
        ]);

        return response()->json([
            'data' => $this->formatReport($baoCao),
        ], 201);
    }

    /**
     * GET /api/admin/post-reports
     */
    public function adminIndex(Request $request)
    {
        $trangThai = $request->query('trang_thai');
        if (is_string($trangThai)) {
            $trangThai = strtoupper(trim($trangThai));
        }

        $baiDangId = $request->query('bai_dang_id');
        $baiDangId = is_numeric($baiDangId) ? (int) $baiDangId : null;

        $limit = (int) $request->query('limit', 50);
        $limit = max(1, min($limit, 100));

        $q = BaoCaoBaiDang::query()
            ->with([
                'baiDang:id,nguoi_dung_id,tieu_de,loai_bai',
                'nguoiToCao:id,ho_ten,email',
            ])
            ->orderByDesc('created_at');

        if (in_array($trangThai, ['CHO_XU_LY', 'DA_XU_LY', 'TU_CHOI'], true)) {
            $q->where('trang_thai', $trangThai);
        }

        if ($baiDangId !== null && $baiDangId > 0) {
            $q->where('bai_dang_id', $baiDangId);
        }

        $rows = $q->limit($limit)->get()->map(fn (BaoCaoBaiDang $r) => $this->formatReport($r, true));

        return response()->json([
            'data' => $rows,
        ]);
    }

    /**
     * PATCH /api/admin/post-reports/{id}
     */
    public function adminUpdate(UpdatePostReportRequest $request, int $id)
    {
        $baoCao = BaoCaoBaiDang::query()->findOrFail($id);
        $baoCao->update([
            'trang_thai' => $request->validated()['trang_thai'],
        ]);

        $baoCao->load(['baiDang:id,nguoi_dung_id,tieu_de,loai_bai', 'nguoiToCao:id,ho_ten,email']);

        return response()->json([
            'data' => $this->formatReport($baoCao, true),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatReport(BaoCaoBaiDang $r, bool $detail = false): array
    {
        $out = [
            'id' => (int) $r->id,
            'bai_dang_id' => (int) $r->bai_dang_id,
            'ly_do' => $r->ly_do,
            'mo_ta' => $r->mo_ta,
            'trang_thai' => $r->trang_thai,
            'created_at' => $r->created_at?->toIso8601String(),
            'updated_at' => $r->updated_at?->toIso8601String(),
        ];

        if ($detail) {
            $out['bai_dang'] = $r->baiDang ? [
                'id' => (int) $r->baiDang->id,
                'tieu_de' => $r->baiDang->tieu_de,
                'loai_bai' => $r->baiDang->loai_bai,
                'nguoi_dung_id' => (int) $r->baiDang->nguoi_dung_id,
            ] : null;
            $out['nguoi_to_cao'] = $r->nguoiToCao ? [
                'id' => (int) $r->nguoiToCao->id,
                'ho_ten' => $r->nguoiToCao->ho_ten,
                'email' => $r->nguoiToCao->email,
            ] : null;
        }

        return $out;
    }
}
