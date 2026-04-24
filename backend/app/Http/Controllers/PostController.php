<?php

namespace App\Http\Controllers;

use App\Http\Requests\Post\StorePostRequest;
use App\Http\Requests\Post\UpdatePostRequest;
use App\Models\BaiDang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\GhepNoiAi;
use App\Services\AiMatchingService;
use App\Services\GeocodingService;
use App\Services\DanhMucSuggestionService;
use App\Models\DanhMucBaiDang;
use App\Models\ThichBaiDang;
use App\Jobs\FindPostMatches;
use App\Models\User;
use App\Notifications\BaiDangDuocThichNotification;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PostController extends Controller
{

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 50));

        $keyword = trim((string) $request->query('keyword', ''));
        $loaiBai = strtoupper((string) $request->query('loai_bai', ''));
        $query = BaiDang::query()
            ->with('nguoiDung')
            ->whereNotIn('trang_thai', ['DA_TANG', 'DA_NHAN']);
        $this->applyPostLikeAggregates($query);
        if (in_array($loaiBai, ['CHO', 'NHAN'])) {
            $query->where('loai_bai', $loaiBai);
        }
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('tieu_de', 'like', "%$keyword%")
                    ->orWhere('mo_ta', 'like', "%$keyword%");
            });
        }
        $query->orderByRaw('
    CASE 
        WHEN created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1000
        WHEN created_at > DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 100
        ELSE 0
    END DESC
')
            ->orderByRaw('RAND()');
        $posts = $query->paginate($perPage);
        $currentUserId = Auth::id();

        $posts->getCollection()->transform(function (BaiDang $post) use ($currentUserId) {

            $post->avatar_url = $post->nguoiDung && $post->nguoiDung->anh_dai_dien
                ? asset('storage/' . $post->nguoiDung->anh_dai_dien)
                : null;

            $paths = is_array($post->hinh_anh) ? $post->hinh_anh : [];
            $post->hinh_anh_urls = array_values(array_map(fn($p) => $this->resolveMediaUrl($p), $paths));
            $post->hinh_anh_url = $post->hinh_anh_urls[0] ?? null;

            $post->nguoi_dung_ten = $post->nguoiDung?->ho_ten;

            $post->is_mine = $currentUserId && $post->nguoi_dung_id == $currentUserId;
            $post->can_edit = $currentUserId === $post->nguoi_dung_id;
            $this->decoratePostLikeFields($post);

            return $post;
        });

        return response()->json([
            'data' => $posts
        ]);
    }

    public function related(int $id, AiMatchingService $aiMatchingService)
    {
        $user = Auth::user();
        if (!$user || !$user->lat || !$user->lng) {
            return response()->json([
                'data' => [],
                'status' => 'empty'
            ]);
        }

        $source = BaiDang::with(['nguoiDung'])->findOrFail($id);
        $locationSource = 'unknown';
        if ($source->lat && $source->lng) {
            $lat = (float) $source->lat;
            $lng = (float) $source->lng;
            $locationSource = 'post';
        } else {
            $latestViewerPost = BaiDang::query()
                ->where('nguoi_dung_id', $user->id)
                ->whereNotNull('lat')
                ->whereNotNull('lng')
                ->orderByDesc('created_at')
                ->first();

            if ($latestViewerPost) {
                $lat = (float) $latestViewerPost->lat;
                $lng = (float) $latestViewerPost->lng;
                $locationSource = 'viewer_post';
            } else {
                $lat = (float) $user->lat;
                $lng = (float) $user->lng;
                $locationSource = 'user';
            }
        }

        $distanceExpr = "(6371 * acos(
            cos(radians({$lat})) * cos(radians(bai_dang.lat)) *
            cos(radians(bai_dang.lng) - radians({$lng})) +
            sin(radians({$lat})) * sin(radians(bai_dang.lat))
        ))";

        $candidates = BaiDang::query()
            ->select('bai_dang.*')
            ->where('loai_bai', $source->loai_bai)
            ->where('id', '!=', $source->id)
            ->where('nguoi_dung_id', '!=', $source->nguoi_dung_id)
            ->whereNotIn('trang_thai', ['DA_NHAN', 'DA_TANG'])
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->whereRaw("$distanceExpr <= 15")
            ->addSelect(DB::raw("$distanceExpr as distance_km"))
            ->orderBy('distance_km', 'asc')
            ->limit(40)
            ->get();

        if ($candidates->isEmpty()) {
            $candidates = BaiDang::query()
                ->select('bai_dang.*')
                ->where('loai_bai', $source->loai_bai)
                ->where('id', '!=', $source->id)
                ->where('nguoi_dung_id', '!=', $source->nguoi_dung_id)
                ->whereNotIn('trang_thai', ['DA_NHAN', 'DA_TANG'])
                ->whereNotNull('lat')
                ->whereNotNull('lng')
                ->whereRaw("$distanceExpr <= 100")
                ->addSelect(DB::raw("$distanceExpr as distance_km"))
                ->orderBy('distance_km', 'asc')
                ->limit(40)
                ->get();
        }

        if ($candidates->isEmpty()) {
            return response()->json([
                'data' => [],
                'status' => 'empty'
            ]);
        }

        $sourceText = $this->normalizeVietnameseText(($source->tieu_de ?? '') . ' ' . ($source->mo_ta ?? ''));
        $sourceFoodTokens = $this->extractFoodTokens($sourceText);
        if (!empty($sourceFoodTokens)) {
            $candidates = $candidates->filter(function (BaiDang $cand) use ($sourceFoodTokens) {
                $candText = $this->normalizeVietnameseText(($cand->tieu_de ?? '') . ' ' . ($cand->mo_ta ?? ''));
                $candFoodTokens = $this->extractFoodTokens($candText);
                if (empty($candFoodTokens)) {
                    return false;
                }
                return !empty(array_intersect($sourceFoodTokens, $candFoodTokens));
            })->values();
        }

        if ($candidates->isEmpty()) {
            return response()->json([
                'data' => [],
                'status' => 'empty'
            ]);
        }

        $allPosts = collect([$source])->concat($candidates);
        $danhMucMap = $this->loadDanhMucMap($allPosts->pluck('id')->all());

        $postsPayload = [$this->toAiPost($source, $danhMucMap)];
        foreach ($candidates as $cand) {
            $postsPayload[] = $this->toAiPost($cand, $danhMucMap);
        }

        if (count($postsPayload) < 2) {
            return response()->json([
                'data' => [],
                'status' => 'empty'
            ]);
        }

        $userHasAddress = !empty($user->dia_chi);
        $userInterests = $userHasAddress ? [] : $this->calculateUserInterests((int) $user->id);

        $payload = [
            'post_id' => $source->id,
            'posts' => $postsPayload,
            'user_has_address' => $userHasAddress,
            'user_interests' => $userInterests,
            'location_source' => $locationSource,
        ];

        $matches = $aiMatchingService->match($payload);
        $matchedIds = collect($matches)->pluck('post_id')->all();
        $matchedPosts = BaiDang::with(['nguoiDung'])->whereIn('id', $matchedIds)->get()->keyBy('id');

        $responseData = [];
        foreach ($matches as $item) {
            $post = $matchedPosts->get((int) $item['post_id']);
            if (!$post) {
                continue;
            }

            $post->avatar_url = $post->nguoiDung && $post->nguoiDung->anh_dai_dien
                ? asset('storage/' . $post->nguoiDung->anh_dai_dien)
                : null;
            $paths = is_array($post->hinh_anh) ? $post->hinh_anh : [];
            $post->hinh_anh_urls = array_values(array_map(fn($p) => $this->resolveMediaUrl($p), $paths));
            $post->hinh_anh_url = $post->hinh_anh_urls[0] ?? null;
            $post->nguoi_dung_ten = $post->nguoiDung?->ho_ten;
            unset($post->nguoiDung);

            $reasonCodes = is_array($item['reasons'] ?? null) ? $item['reasons'] : [];
            $reasonMapVi = [
                'category_gate' => 'Cùng danh mục nên được ưu tiên',
                'intent_gate' => 'Cùng nhóm nhu cầu (ý định) nên được ưu tiên',
                'geo_ok' => 'Có thể tính khoảng cách theo vị trí',
                'geo_unknown' => 'Không đủ dữ liệu vị trí để tính khoảng cách',
                'interest_match' => 'Phù hợp với mối quan tâm của bạn',
            ];
            $reasonsVi = [];
            foreach ($reasonCodes as $code) {
                $reasonsVi[] = $reasonMapVi[$code] ?? (string) $code;
            }

            $responseData[] = [
                'post' => $post,
                'score' => (float) $item['score'],
                'distance_km' => array_key_exists('distance', $item) && $item['distance'] !== null
                    ? (float) $item['distance']
                    : null,
                'match_percent' => (float) $item['match_percent'],
                'reasons' => $reasonsVi,
                'reason_codes' => $reasonCodes,
                'breakdown' => $item['breakdown'] ?? null,
            ];
        }

        $filtered = array_values(array_filter($responseData, static function (array $row): bool {
            return (float) ($row['match_percent'] ?? 0) >= 60.0;
        }));
        if (count($filtered) < 3) {
            $filtered = array_values(array_filter($responseData, static function (array $row): bool {
                return (float) ($row['match_percent'] ?? 0) >= 50.0;
            }));
        }
        if ($filtered === []) {
            $filtered = $responseData;
        }

        return response()->json([
            'data' => array_slice($filtered, 0, 10),
        ]);
    }

    public function show(int $id)
    {
        $query = BaiDang::query()->with(['nguoiDung']);
        $this->applyPostLikeAggregates($query);
        $post = $query->findOrFail($id);

        $post->avatar_url = $post->nguoiDung && $post->nguoiDung->anh_dai_dien
            ? asset('storage/' . $post->nguoiDung->anh_dai_dien)
            : null;
        $paths = is_array($post->hinh_anh) ? $post->hinh_anh : [];
        $post->hinh_anh_urls = array_values(array_map(fn($p) => $this->resolveMediaUrl($p), $paths));
        $post->hinh_anh_url = $post->hinh_anh_urls[0] ?? null; // backward compatible

        $this->decoratePostLikeFields($post);

        return response()->json([
            'data' => $post
        ]);
    }

    public function me(Request $request)
    {
        $userId = (int) Auth::id();

        $loaiBai = $request->query('loai_bai');
        if (is_string($loaiBai)) {
            $loaiBai = strtoupper(trim($loaiBai));
        }

        $trangThai = $request->query('trang_thai');
        if (is_string($trangThai)) {
            $trangThai = strtoupper(trim($trangThai));
        }

        $perPage = (int) $request->query('per_page', 12);
        $perPage = max(1, min($perPage, 50));

        $query = BaiDang::query()
            ->with(['nguoiDung'])
            ->where('nguoi_dung_id', $userId)
            ->orderByDesc('created_at');

        $this->applyPostLikeAggregates($query);

        if (in_array($loaiBai, ['CHO', 'NHAN'], true)) {
            $query->where('loai_bai', $loaiBai);
        }

        if (in_array($trangThai, ['CON_NHAN', 'CON_TANG', 'DA_NHAN', 'DA_TANG'], true)) {
            $query->where('trang_thai', $trangThai);
        }

        $posts = $query->paginate($perPage);

        $posts->getCollection()->transform(function (BaiDang $post) {
            $post->avatar_url = $post->nguoiDung && $post->nguoiDung->anh_dai_dien
                ? asset('storage/' . $post->nguoiDung->anh_dai_dien)
                : null;

            $paths = is_array($post->hinh_anh) ? $post->hinh_anh : [];
            $post->hinh_anh_urls = array_values(array_map(fn($p) => $this->resolveMediaUrl($p), $paths));
            $post->hinh_anh_url = $post->hinh_anh_urls[0] ?? null;

            $post->nguoi_dung_ten = $post->nguoiDung?->ho_ten;
            unset($post->nguoiDung);

            $this->decoratePostLikeFields($post);

            return $post;
        });

        return response()->json([
            'data' => $posts,
        ]);
    }

    public function toggleLike(int $id)
    {
        $userId = (int) Auth::id();
        $post = BaiDang::query()->findOrFail($id);

        $existing = ThichBaiDang::query()
            ->where('bai_dang_id', $id)
            ->where('nguoi_dung_id', $userId)
            ->first();

        if ($existing) {
            $existing->delete();
            $liked = false;
        } else {
            ThichBaiDang::query()->create([
                'bai_dang_id' => $id,
                'nguoi_dung_id' => $userId,
            ]);
            $liked = true;

            $chuBaiId = (int) $post->nguoi_dung_id;
            if ($chuBaiId !== $userId) {
                $chuBai = User::query()->find($chuBaiId);
                $nguoiThich = Auth::user();
                if ($chuBai && $nguoiThich) {
                    $chuBai->notify(new BaiDangDuocThichNotification(
                        bai_dang_id: $id,
                        nguoi_thich_id: $userId,
                        nguoi_thich_ten: (string) ($nguoiThich->ho_ten ?? 'Người dùng'),
                        tieu_de_bai: $post->tieu_de,
                    ));
                }
            }
        }

        $soLuotThich = ThichBaiDang::query()->where('bai_dang_id', $id)->count();

        return response()->json([
            'data' => [
                'liked' => $liked,
                'so_luot_thich' => $soLuotThich,
            ],
        ]);
    }

    public function store(StorePostRequest $request)
    {
        $userId = Auth::id();

        $data = $request->validated();

        if (
            (isset($data['lat'], $data['lng']) && $data['lat'] !== null && $data['lng'] !== null)
            && (empty($data['dia_diem']) || !is_string($data['dia_diem']))
        ) {
            /** @var GeocodingService $geo */
            $geo = app(GeocodingService::class);
            $rev = $geo->reverseGeocode((float)$data['lat'], (float)$data['lng']);
            if ($rev && !empty($rev['display_name'])) {
                $data['dia_diem'] = $rev['display_name'];
            }
        }

        if (
            (!isset($data['lat']) || $data['lat'] === null || $data['lng'] === null)
            && !empty($data['dia_diem'])
        ) {
            /** @var GeocodingService $geo */
            $geo = app(GeocodingService::class);
            $coords = $geo->geocode($data['dia_diem']);
            if ($coords) {
                $data['lat'] = $coords['lat'];
                $data['lng'] = $coords['lng'];
            }
        }
        if (isset($data['lat'], $data['lng']) && $data['lat'] !== null && $data['lng'] !== null) {
            /** @var GeocodingService $geo */
            $geo = app(GeocodingService::class);
            $data['region'] = $geo->makeRegion((float)$data['lat'], (float)$data['lng']);
        }

        $trangThaiDefault = $data['loai_bai'] === 'CHO' ? 'CON_TANG' : 'CON_NHAN';
        $data['trang_thai'] = $data['trang_thai'] ?? $trangThaiDefault;

        $data['nguoi_dung_id'] = $userId;

        $hinhAnhPaths = [];
        $files = $request->file('hinh_anh');
        if ($files) {
            $files = is_array($files) ? $files : [$files];
            foreach ($files as $f) {
                if ($f && $f->isValid()) {
                    $uploaded = Cloudinary::uploadApi()->upload($f->getRealPath(), [
                        'folder' => 'posts'
                    ]);
                    if ($uploaded) {
                        $hinhAnhPaths[] = $uploaded['secure_url'];
                    }
                }
            }
        }
        $data['hinh_anh'] = $hinhAnhPaths === [] ? null : $hinhAnhPaths;

        $post = BaiDang::create($data);

        $gService = app(DanhMucSuggestionService::class);
        $suggestions = $gService->suggest($post->tieu_de, $post->mo_ta);
        DanhMucBaiDang::where('bai_dang_id', $post->id)->delete();
        foreach ($suggestions as $s) {
            DanhMucBaiDang::create([
                'bai_dang_id' => $post->id,
                'danh_muc_code' => $s['danh_muc_code'],
                'is_primary' => (bool)$s['is_primary'],
                'confidence' => (float)$s['confidence'],
            ]);
        }
        $post->load('nguoiDung');
        $post->avatar_url = $post->nguoiDung && $post->nguoiDung->anh_dai_dien
            ? asset('storage/' . $post->nguoiDung->anh_dai_dien)
            : null;
        $paths = is_array($post->hinh_anh) ? $post->hinh_anh : [];
        $post->hinh_anh_urls = array_values(array_map(fn($p) => $this->resolveMediaUrl($p), $paths));
        $post->hinh_anh_url = $post->hinh_anh_urls[0] ?? null; // backward compatible
        unset($post->nguoiDung);

        $aiService = app(AiMatchingService::class);
        $realtimeMatches = $this->buildRealtimeMatchesPayload($post, (int) $userId, $aiService);

        FindPostMatches::dispatch((int) $post->id)->delay(now()->addSeconds(2));

        $danhMucGoiY = DanhMucBaiDang::where('bai_dang_id', $post->id)
            ->orderByDesc('is_primary')
            ->orderByDesc('confidence')
            ->get(['danh_muc_code', 'is_primary', 'confidence']);

        return response()->json([
            'message' => 'Tạo bài đăng thành công',
            'data' => $post,
            'danh_muc_goi_y' => $danhMucGoiY,
            'matches' => $realtimeMatches,
            'matches_source' => $realtimeMatches === [] ? 'none' : 'ai',
        ], 201);
    }


    public function update(UpdatePostRequest $request, int $id)
    {
        $userId = Auth::id();

        $post = BaiDang::findOrFail($id);

        if ((int)$post->nguoi_dung_id !== (int)$userId) {
            return response()->json([
                'message' => 'Bạn không có quyền cập nhật bài này.'
            ], 403);
        }

        $data = $request->validated();

        if (
            (array_key_exists('lat', $data) || array_key_exists('lng', $data))
            && (isset($data['lat'], $data['lng']) && $data['lat'] !== null && $data['lng'] !== null)
            && (!array_key_exists('dia_diem', $data) || empty($data['dia_diem']))
        ) {
            /** @var GeocodingService $geo */
            $geo = app(GeocodingService::class);
            $rev = $geo->reverseGeocode((float)$data['lat'], (float)$data['lng']);
            if ($rev && !empty($rev['display_name'])) {
                $data['dia_diem'] = $rev['display_name'];
            }
        }

        $shouldGeocode = array_key_exists('dia_diem', $data)
            && !empty($data['dia_diem'])
            && (!isset($data['lat']) || $data['lat'] === null || !isset($data['lng']) || $data['lng'] === null);

        if ($shouldGeocode) {
            /** @var GeocodingService $geo */
            $geo = app(GeocodingService::class);
            $coords = $geo->geocode($data['dia_diem']);
            if ($coords) {
                $data['lat'] = $coords['lat'];
                $data['lng'] = $coords['lng'];
            }
        }
        if (isset($data['lat'], $data['lng']) && $data['lat'] !== null && $data['lng'] !== null) {
            /** @var GeocodingService $geo */
            $geo = app(GeocodingService::class);
            $data['region'] = $geo->makeRegion((float)$data['lat'], (float)$data['lng']);
        }

        if (!empty($data['loai_bai'])) {
            $default = $data['loai_bai'] === 'CHO' ? 'CON_TANG' : 'CON_NHAN';
            if (empty($data['trang_thai'])) {
                $data['trang_thai'] = $default;
            }
        }

        $oldPaths = array_values(array_filter(
            is_array($post->hinh_anh) ? $post->hinh_anh : [],
            static fn($p) => is_string($p) && $p !== ''
        ));
        $oldSet = array_flip($oldPaths);

        $existingImageUrls = $request->input('existing_images', []);
        if (!is_array($existingImageUrls)) {
            $existingImageUrls = [];
        }

        $keepPaths = [];
        foreach ($existingImageUrls as $img) {
            $path = $this->normalizeStoragePath(is_string($img) ? $img : null);
            if ($path !== null && isset($oldSet[$path])) {
                $keepPaths[] = $path;
            }
        }
        $keepPaths = array_values(array_unique($keepPaths));

        foreach ($oldPaths as $op) {
            if (
                !in_array($op, $keepPaths, true)
                && !$this->isAbsoluteUrl($op)
                && Storage::disk('public')->exists($op)
            ) {
                Storage::disk('public')->delete($op);
            }
        }

        $newPaths = [];
        $files = $request->file('hinh_anh');
        if ($files) {
            $files = is_array($files) ? $files : [$files];
            foreach ($files as $f) {
                if ($f && $f->isValid()) {
                    $uploaded = Cloudinary::uploadApi()->upload($f->getRealPath(), [
                        'folder' => 'posts'
                    ]);
                    if ($uploaded) {
                        $newPaths[] = $uploaded['secure_url'];
                    }
                }
            }
        }

        if ($request->has('existing_images') || $files) {
            $mergedPaths = array_values(array_unique(array_merge($keepPaths, $newPaths)));
            $data['hinh_anh'] = $mergedPaths === [] ? null : $mergedPaths;
        }

        $post->update($data);
        $post->refresh();

        $gService = app(DanhMucSuggestionService::class);
        $suggestions = $gService->suggest($post->tieu_de, $post->mo_ta);
        DanhMucBaiDang::where('bai_dang_id', $post->id)->delete();
        foreach ($suggestions as $s) {
            DanhMucBaiDang::create([
                'bai_dang_id' => $post->id,
                'danh_muc_code' => $s['danh_muc_code'],
                'is_primary' => (bool)$s['is_primary'],
                'confidence' => (float)$s['confidence'],
            ]);
        }

        $post->load('nguoiDung');
        $post->avatar_url = $post->nguoiDung && $post->nguoiDung->anh_dai_dien
            ? asset('storage/' . $post->nguoiDung->anh_dai_dien)
            : null;
        $paths = is_array($post->hinh_anh) ? $post->hinh_anh : [];
        $post->hinh_anh_urls = array_values(array_map(fn($p) => $this->resolveMediaUrl($p), $paths));
        $post->hinh_anh_url = $post->hinh_anh_urls[0] ?? null; // backward compatible
        unset($post->nguoiDung);

        $danhMucGoiY = DanhMucBaiDang::where('bai_dang_id', $post->id)
            ->orderByDesc('is_primary')
            ->orderByDesc('confidence')
            ->get(['danh_muc_code', 'is_primary', 'confidence']);

        FindPostMatches::dispatch((int) $post->id)->delay(now()->addSeconds(2));
        return response()->json([
            'message' => 'Cập nhật bài đăng thành công',
            'data' => $post,
            'danh_muc_goi_y' => $danhMucGoiY,
        ]);
    }


    public function destroy(int $id)
    {
        $userId = Auth::id();

        $post = BaiDang::findOrFail($id);

        if ((int)$post->nguoi_dung_id !== (int)$userId) {
            return response()->json([
                'message' => 'Bạn không có quyền xóa bài này.'
            ], 403);
        }

        $paths = is_array($post->hinh_anh) ? $post->hinh_anh : [];
        foreach ($paths as $p) {
            if (is_string($p) && $p !== '' && !$this->isAbsoluteUrl($p) && Storage::disk('public')->exists($p)) {
                Storage::disk('public')->delete($p);
            }
        }

        $post->delete();

        return response()->json([
            'message' => 'Xóa bài đăng thành công'
        ]);
    }


    public function matches(int $id, AiMatchingService $aiMatchingService)
{
    $source = BaiDang::with(['nguoiDung'])->findOrFail($id);

    $userId = (int) Auth::id();
    if ((int) $source->nguoi_dung_id !== $userId) {
        return response()->json([
            'message' => 'Chỉ chủ bài đăng mới được phép xem matches.'
        ], 403);
    }

    $user = User::findOrFail($userId);
    $userHasAddress = !empty($user->dia_chi);

    $userInterests = [];
    if (!$userHasAddress) {
        $userInterests = $this->calculateUserInterests($userId);
    }

    $candidates = $this->buildCandidates($source);

    if ($candidates->isEmpty()) {
        return response()->json([
            'data' => [],
            'status' => 'no_candidate'
        ]);
    }

    $matches = $this->runAiMatching($source, $candidates, $userHasAddress, $userInterests, $aiMatchingService);

    if (!empty($matches)) {

        $responseData = $this->mapAiMatchesToResponse($source, $matches, true);

        return response()->json([
            'data' => $responseData,
            'source' => 'ai'
        ]);
    }

    $existingMatches = GhepNoiAi::where('bai_dang_nguon_id', $id)
        ->with(['baiDangPhuHop.nguoiDung'])
        ->orderByDesc('diem_phu_hop')
        ->limit(10)
        ->get();

    if ($existingMatches->isNotEmpty()) {

        $responseData = $existingMatches->map(function ($m) {

            $post = $m->baiDangPhuHop;

            $post->avatar_url = $post->nguoiDung && $post->nguoiDung->anh_dai_dien
                ? asset('storage/' . $post->nguoiDung->anh_dai_dien)
                : null;

            $paths = is_array($post->hinh_anh) ? $post->hinh_anh : [];
            $post->hinh_anh_urls = array_values(array_map(fn($p) => $this->resolveMediaUrl($p), $paths));
            $post->hinh_anh_url = $post->hinh_anh_urls[0] ?? null;

            return [
                'post' => $post,
                'score' => (float)$m->diem_phu_hop,
                'match_percent' => round(min(100, max(0, ($m->diem_phu_hop / 10) * 100)), 2),
            ];
        });

        return response()->json([
            'data' => $responseData,
            'source' => 'db_fallback'
        ]);
    }

    return response()->json([
        'data' => [],
        'status' => 'no_match'
    ]);
}

    private function buildCandidates(BaiDang $source)
    {
        $targetLoaiBai = $source->loai_bai === 'CHO' ? 'NHAN' : 'CHO';
        $query = BaiDang::query()
            ->with(['nguoiDung'])
            ->select('bai_dang.*')
            ->where('loai_bai', $targetLoaiBai)
            ->whereNotIn('trang_thai', ['DA_NHAN', 'DA_TANG'])
            ->where('nguoi_dung_id', '!=', $source->nguoi_dung_id);

        if (!empty($source->region)) {
            $nearRegions = $this->neighborRegions($source->region);
            $regions = array_values(array_unique(array_filter(array_merge([$source->region], $nearRegions))));
            $query->whereIn('region', $regions);
        }

        if ($source->lat !== null && $source->lng !== null) {
            $distanceExpr = "(6371 * acos(
                cos(radians(" . (float) $source->lat . ")) * cos(radians(bai_dang.lat)) *
                cos(radians(bai_dang.lng) - radians(" . (float) $source->lng . ")) +
                sin(radians(" . (float) $source->lat . ")) * sin(radians(bai_dang.lat))
            ))";
            $query->whereNotNull('bai_dang.lat')
                ->whereNotNull('bai_dang.lng')
                ->whereRaw("$distanceExpr <= 20")
                ->addSelect(DB::raw("$distanceExpr as distance_km"))
                ->orderBy('distance_km', 'asc');
        } else {
            $query->latest();
        }

        return $query->limit(40)->get();
    }

    private function runAiMatching(
        BaiDang $source,
        $candidates,
        bool $userHasAddress,
        array $userInterests,
        AiMatchingService $aiMatchingService
    ): array {
        $allPosts = collect([$source])->concat($candidates);
        $danhMucMap = $this->loadDanhMucMap($allPosts->pluck('id')->all());
        $postsPayload = [$this->toAiPost($source, $danhMucMap)];
        foreach ($candidates as $cand) {
            $postsPayload[] = $this->toAiPost($cand, $danhMucMap);
        }
        if (count($postsPayload) < 2) {
            return [];
        }

        $payload = [
            'post_id' => $source->id,
            'posts' => $postsPayload,
            'user_has_address' => $userHasAddress,
            'user_interests' => $userInterests,
        ];

        return $aiMatchingService->match($payload);
    }

    private function mapAiMatchesToResponse(BaiDang $source, array $matches, bool $persist = false): array
    {
        $matchedIds = collect($matches)->pluck('post_id')->all();
        $matchedPosts = BaiDang::with(['nguoiDung'])
            ->whereIn('id', $matchedIds)
            ->get()
            ->keyBy('id');

        $responseData = [];
        foreach ($matches as $item) {
            $post = $matchedPosts->get((int) $item['post_id']);
            if (!$post) {
                continue;
            }

            $post->avatar_url = $post->nguoiDung && $post->nguoiDung->anh_dai_dien
                ? asset('storage/' . $post->nguoiDung->anh_dai_dien)
                : null;
            $paths = is_array($post->hinh_anh) ? $post->hinh_anh : [];
            $post->hinh_anh_urls = array_values(array_map(fn($p) => $this->resolveMediaUrl($p), $paths));
            $post->hinh_anh_url = $post->hinh_anh_urls[0] ?? null;
            $post->nguoi_dung_ten = $post->nguoiDung?->ho_ten;
            unset($post->nguoiDung);

            $responseData[] = [
                'post' => $post,
                'score' => (float) ($item['score'] ?? 0),
                'distance_km' => array_key_exists('distance', $item) && $item['distance'] !== null
                    ? (float) $item['distance']
                    : null,
                'match_percent' => (float) ($item['match_percent'] ?? 0),
                'reasons' => $item['reasons'] ?? [],
                'breakdown' => $item['breakdown'] ?? null,
            ];

            if ($persist) {
                GhepNoiAi::updateOrCreate(
                    [
                        'bai_dang_nguon_id' => $source->id,
                        'bai_dang_phu_hop_id' => $post->id,
                    ],
                    [
                        'diem_phu_hop' => (float) ($item['score'] ?? 0),
                        'trang_thai' => 'GHEP_NOI',
                    ]
                );
            }
        }

        return $responseData;
    }

    private function buildRealtimeMatchesPayload(BaiDang $post, int $userId, AiMatchingService $aiMatchingService): array
    {
        $source = BaiDang::with(['nguoiDung'])->find($post->id);
        if (!$source) {
            return [];
        }

        $user = User::find($userId);
        $userHasAddress = !empty($user?->dia_chi);
        $userInterests = $userHasAddress ? [] : $this->calculateUserInterests($userId);

        $candidates = $this->buildCandidates($source);
        if ($candidates->isEmpty()) {
            return [];
        }

        $matches = $this->runAiMatching($source, $candidates, $userHasAddress, $userInterests, $aiMatchingService);
        if (empty($matches)) {
            return [];
        }

        return $this->mapAiMatchesToResponse($source, $matches, false);
    }

    private function toAiPost(BaiDang $post, array $danhMucMap = []): array
    {
        $m = $danhMucMap[(int)$post->id] ?? ['primary' => null, 'all' => []];
        return [
            'id' => (int)$post->id,
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

        $rows = \App\Models\DanhMucBaiDang::query()
            ->whereIn('bai_dang_id', $postIds)
            ->orderByDesc('is_primary')
            ->orderByDesc('confidence')
            ->get(['bai_dang_id', 'danh_muc_code', 'is_primary', 'confidence']);

        $map = [];
        foreach ($postIds as $id) {
            $map[(int)$id] = ['primary' => null, 'all' => []];
        }

        foreach ($rows as $row) {
            $id = (int)$row->bai_dang_id;
            $code = (string)$row->danh_muc_code;
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

    private function calculateUserInterests(int $userId): array
    {

        $interests = DanhMucBaiDang::query()
            ->whereIn('bai_dang_id', function ($q) use ($userId) {
                $q->select('bai_dang_id')
                    ->from('thich_bai_dang')
                    ->where('nguoi_dung_id', $userId);
            })
            ->selectRaw(
                'danh_muc_code, 
                 COUNT(*) as like_count,
                 AVG(confidence) as avg_confidence,
                 SUM(CASE WHEN is_primary THEN 1 ELSE 0 END) as primary_count'
            )
            ->groupBy('danh_muc_code')
            ->orderByDesc('like_count')
            ->orderByDesc('primary_count')
            ->limit(10)
            ->get();

        $result = [];
        foreach ($interests as $interest) {
            $result[] = [
                'code' => $interest->danh_muc_code,
                'weight' => (float)$interest->like_count,
            ];
        }

        return $result;
    }

    private function normalizeStoragePath(?string $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $raw = trim($value);
        if ($this->isAbsoluteUrl($raw)) {
            return $raw;
        }

        $path = parse_url($raw, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $raw;
        }

        $path = ltrim($path, '/');
        $storagePrefix = 'storage/';

        if (str_starts_with($path, $storagePrefix)) {
            return substr($path, strlen($storagePrefix));
        }

        return $path;
    }

    private function resolveMediaUrl(?string $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $raw = trim($value);
        return $this->isAbsoluteUrl($raw) ? $raw : asset('storage/' . ltrim($raw, '/'));
    }

    private function isAbsoluteUrl(?string $value): bool
    {
        return is_string($value) && preg_match('/^https?:\/\//i', trim($value)) === 1;
    }

    private function normalizeVietnameseText(string $value): string
    {
        $text = mb_strtolower(trim($value), 'UTF-8');
        $map = [
            'à' => 'a',
            'á' => 'a',
            'ạ' => 'a',
            'ả' => 'a',
            'ã' => 'a',
            'â' => 'a',
            'ầ' => 'a',
            'ấ' => 'a',
            'ậ' => 'a',
            'ẩ' => 'a',
            'ẫ' => 'a',
            'ă' => 'a',
            'ằ' => 'a',
            'ắ' => 'a',
            'ặ' => 'a',
            'ẳ' => 'a',
            'ẵ' => 'a',
            'è' => 'e',
            'é' => 'e',
            'ẹ' => 'e',
            'ẻ' => 'e',
            'ẽ' => 'e',
            'ê' => 'e',
            'ề' => 'e',
            'ế' => 'e',
            'ệ' => 'e',
            'ể' => 'e',
            'ễ' => 'e',
            'ì' => 'i',
            'í' => 'i',
            'ị' => 'i',
            'ỉ' => 'i',
            'ĩ' => 'i',
            'ò' => 'o',
            'ó' => 'o',
            'ọ' => 'o',
            'ỏ' => 'o',
            'õ' => 'o',
            'ô' => 'o',
            'ồ' => 'o',
            'ố' => 'o',
            'ộ' => 'o',
            'ổ' => 'o',
            'ỗ' => 'o',
            'ơ' => 'o',
            'ờ' => 'o',
            'ớ' => 'o',
            'ợ' => 'o',
            'ở' => 'o',
            'ỡ' => 'o',
            'ù' => 'u',
            'ú' => 'u',
            'ụ' => 'u',
            'ủ' => 'u',
            'ũ' => 'u',
            'ư' => 'u',
            'ừ' => 'u',
            'ứ' => 'u',
            'ự' => 'u',
            'ử' => 'u',
            'ữ' => 'u',
            'ỳ' => 'y',
            'ý' => 'y',
            'ỵ' => 'y',
            'ỷ' => 'y',
            'ỹ' => 'y',
            'đ' => 'd',
        ];
        $text = strtr($text, $map);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? $text;
        return preg_replace('/\s+/u', ' ', $text) ?? $text;
    }

    private function extractFoodTokens(string $text): array
    {
        $tokenMap = [
            'sua' => [' sua ', ' sua_', ' _sua', 'sua bot', 'sua tuoi'],
            'gao' => [' gao ', 'gao '],
            'mi' => [' mi ', 'mi tom', 'my tom'],
        ];

        $haystack = ' ' . $text . ' ';
        $hits = [];
        foreach ($tokenMap as $token => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($haystack, $kw)) {
                    $hits[] = $token;
                    break;
                }
            }
        }

        return array_values(array_unique($hits));
    }

    private function applyPostLikeAggregates($query): void
    {
        $query->withCount([
            'thichs as so_luot_thich',
            'binhLuans as so_binh_luan',
        ]);
        if (Auth::check()) {
            $uid = (int) Auth::id();
            $query->withExists(['thichs as da_thich' => function ($q) use ($uid) {
                $q->where('nguoi_dung_id', $uid);
            }]);
        }
    }

    private function decoratePostLikeFields(BaiDang $post): void
    {
        $post->setAttribute('so_luot_thich', (int) ($post->getAttribute('so_luot_thich') ?? 0));
        $post->setAttribute('so_binh_luan', (int) ($post->getAttribute('so_binh_luan') ?? 0));
        $post->setAttribute(
            'da_thich',
            Auth::check() ? (bool) $post->getAttribute('da_thich') : false
        );
    }

    private function neighborRegions(string $region): array
    {
        $parts = explode('_', $region);
        if (count($parts) !== 2 || !is_numeric($parts[0]) || !is_numeric($parts[1])) {
            return [];
        }

        $lat = round((float)$parts[0], 2);
        $lng = round((float)$parts[1], 2);
        $step = 0.01;
        $rows = [];

        // Expand từ 3x3 thành 5x5 grid (±0.02) để không miss bài gần nhưng vượt khỏi boundary
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
    public function uploadImage(Request $request)
    {
        $request->validate([
            'hinh_anh.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $urls = [];

        $files = $request->file('hinh_anh');

        if (!empty($files)) {

            $files = is_array($files) ? $files : [$files];

            foreach ($files as $file) {

                if ($file && $file->isValid()) {

                    $uploaded = Cloudinary::uploadApi()->upload($file->getRealPath(), [
                        'folder' => 'posts'
                    ]);

                    $urls[] = $uploaded['secure_url'] ?? null;
                }
            }
        }
        return response()->json([
            'success' => true,
            'urls' => array_values(array_filter($urls)),
        ]);
    }
    public function search(Request $request)
    {
        $keyword = trim((string) $request->query('q', ''));
        $loaiBai = strtoupper((string) $request->query('loai_bai', ''));
        $perPage = (int) $request->query('per_page', 10);

        $perPage = max(1, min($perPage, 50));

        $query = BaiDang::query()
            ->with(['nguoiDung'])
            ->orderByDesc('created_at');

        if ($keyword !== '') {
            $words = array_values(array_filter(preg_split('/\s+/', $keyword), function ($word) {
                return $word !== null && trim($word) !== '';
            }));

            $query->where(function ($q) use ($words, $keyword) {
                $q->whereRaw("tieu_de COLLATE utf8mb4_unicode_ci LIKE ?", ["%{$keyword}%"])
                    ->orWhereRaw("mo_ta COLLATE utf8mb4_unicode_ci LIKE ?", ["%{$keyword}%"]);

                foreach ($words as $word) {
                    $q->orWhereRaw("tieu_de COLLATE utf8mb4_unicode_ci LIKE ?", ["%{$word}%"])
                        ->orWhereRaw("mo_ta COLLATE utf8mb4_unicode_ci LIKE ?", ["%{$word}%"]);
                }
            });

            $query->orderByRaw("
                    (tieu_de LIKE ?) DESC,
                    (mo_ta LIKE ?) DESC
                ", ["%{$keyword}%", "%{$keyword}%"]);
        }

        $query->orderByDesc('created_at');

        if (in_array($loaiBai, ['CHO', 'NHAN'])) {
            $query->where('loai_bai', $loaiBai);
        }

        $query->whereNotIn('trang_thai', ['DA_TANG', 'DA_NHAN']);

        $posts = $query->paginate($perPage);

        $posts->getCollection()->transform(function (BaiDang $post) {

            $post->avatar_url = $post->nguoiDung && $post->nguoiDung->anh_dai_dien
                ? asset('storage/' . $post->nguoiDung->anh_dai_dien)
                : null;

            $paths = is_array($post->hinh_anh) ? $post->hinh_anh : [];
            $post->hinh_anh_urls = array_map(fn($p) => $this->resolveMediaUrl($p), $paths);
            $post->hinh_anh_url = $post->hinh_anh_urls[0] ?? null;

            $post->nguoi_dung_ten = $post->nguoiDung?->ho_ten;

            unset($post->nguoiDung);

            return $post;
        });

        return response()->json([
            'data' => $posts
        ]);
    }
}
