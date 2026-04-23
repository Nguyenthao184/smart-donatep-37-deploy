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
use App\Models\User;
use App\Notifications\BaiDangDuocThichNotification;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PostController extends Controller
{

    public function index()
    {
        $posts = BaiDang::query()
            ->with(['nguoiDung'])
            ->orderByDesc('created_at')
            ->paginate(12);

        $currentUserId = Auth::id();

        $posts->getCollection()->transform(function (BaiDang $post) use ($currentUserId) {

            $post->avatar_url = $post->nguoiDung && $post->nguoiDung->anh_dai_dien
                ? asset('storage/' . $post->nguoiDung->anh_dai_dien)
                : null;

            $paths = is_array($post->hinh_anh) ? $post->hinh_anh : [];
            $post->hinh_anh_urls = array_values(array_map(fn($p) => $this->resolveMediaUrl($p), $paths));
            $post->hinh_anh_url = $post->hinh_anh_urls[0] ?? null;

            $post->nguoi_dung_ten = $post->nguoiDung?->ho_ten;

            // 🔥 CHỈ MATCHES CHO BÀI CỦA CHÍNH USER
            if ($currentUserId && $post->nguoi_dung_id == $currentUserId) {

                $post->matches = GhepNoiAi::where('bai_dang_nguon_id', $post->id)
                    ->with(['baiDangPhuHop.nguoiDung'])
                    ->orderByDesc('diem_phu_hop')
                    ->limit(5)
                    ->get()
                    ->map(function ($m) {
                        return [
                            'post' => $m->baiDangPhuHop,
                            'score' => $m->diem_phu_hop
                        ];
                    });
            } else {
                $post->matches = null;
            }

            return $post;
        });

        return response()->json([
            'data' => $posts
        ]);
    }

    public function related(int $id)
    {
        $user = Auth::user();

        if (!$user || !$user->lat || !$user->lng) {
            return response()->json(['data' => []]);
        }

        $source = BaiDang::findOrFail($id);

        $lat = (float) $user->lat;
        $lng = (float) $user->lng;

        $distanceExpr = "(6371 * acos(
            cos(radians({$lat})) * cos(radians(bai_dang.lat)) *
            cos(radians(bai_dang.lng) - radians({$lng})) +
            sin(radians({$lat})) * sin(radians(bai_dang.lat))
        ))";

        $near = BaiDang::query()
            ->select('bai_dang.*')
            ->where('loai_bai', $source->loai_bai)
            ->where('id', '!=', $source->id)
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->whereRaw("$distanceExpr <= 15")
            ->addSelect(DB::raw("$distanceExpr as distance_km"))
            ->orderBy('distance_km', 'asc')
            ->limit(8)
            ->get();

        if ($near->isEmpty()) {
            $near = BaiDang::query()
                ->select('bai_dang.*')
                ->where('loai_bai', $source->loai_bai)
                ->where('id', '!=', $source->id)
                ->whereNotNull('lat')
                ->whereNotNull('lng')
                ->whereRaw("$distanceExpr <= 100")
                ->addSelect(DB::raw("$distanceExpr as distance_km"))
                ->orderBy('distance_km', 'asc')
                ->limit(8)
                ->get();
        }

        if ($near->isEmpty()) {
            $near = BaiDang::query()
                ->select('bai_dang.*')
                ->where('loai_bai', $source->loai_bai)
                ->where('id', '!=', $source->id)
                ->inRandomOrder()
                ->limit(10)
                ->get();
        }

        $posts = $near->values();





        if ($posts->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $texts = $posts->map(function ($p) {
            return [
                'id' => $p->id,
                'noi_dung' => ($p->tieu_de ?? '') . ' ' . ($p->mo_ta ?? '')
            ];
        })->values();

        $sourceText = ($source->tieu_de ?? '') . ' ' . ($source->mo_ta ?? '');

        try {
            $response = Http::timeout(3)->post(env('AI_SERVICE_URL') . '/semantic-matches', [
                'noi_dung' => $sourceText,
                'candidates' => $texts,
                'top_k' => 10,
                'min_score' => 0.3
            ]);

            if ($response->ok()) {
                $results = $response->json();

                $ids = collect($results)->pluck('id')->toArray();

                $posts = $posts
                    ->whereIn('id', $ids)
                    ->sortBy(function ($p) use ($ids) {
                        return array_search($p->id, $ids);
                    })
                    ->values();
            } else {
                $posts = $posts->shuffle()->take(10)->values();
            }
        } catch (\Exception $e) {
            $posts = $posts->shuffle()->take(10)->values();
        }

        return response()->json([
            'data' => $posts->take(10)
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
        $aiService = app(\App\Services\AiMatchingService::class);

        
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

        return response()->json([
            'message' => 'Tạo bài đăng thành công',
            'data' => $post,
            'danh_muc_goi_y' => $danhMucGoiY,
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

        // Get authenticated user - required by middleware
        $userId = (int) Auth::id();
        if ((int) $source->nguoi_dung_id !== $userId) {
            return response()->json([
                'message' => 'Chỉ chủ bài đăng mới được phép xem matches.'
            ], 403);
        }
        $user = User::findOrFail($userId);

        // Check if user has address for location-based matching
        $userHasAddress = !empty($user->dia_chi);

        // Get user interests from liked posts (for interest-based matching when no address)
        $userInterests = [];
        if (!$userHasAddress) {
            $userInterests = $this->calculateUserInterests($userId);
        }

        $targetLoaiBai = $source->loai_bai === 'CHO' ? 'NHAN' : 'CHO';
        $maxRadiusKm = 20.0;
        $candidatePrelimit = 120;
        $aiInputLimit = 40;

        // Lọc ứng viên: loại đối ứng + trạng thái active phù hợp + khác người đăng
        $candidatesQuery = BaiDang::query()
            ->with(['nguoiDung'])
            ->select('bai_dang.*')
            ->where('loai_bai', $targetLoaiBai)
            // Du lieu thuc te co the lech status (vd: CHO + CON_NHAN).
            // Chi loai bai da ket thuc de tranh bo sot ung vien phu hop.
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
                    $maxRadiusKm
                ]);
            $distanceExprSelect = "(6371 * acos( cos(radians(" . (float)$source->lat . ")) * cos(radians(bai_dang.lat)) * cos(radians(bai_dang.lng) - radians(" . (float)$source->lng . ")) + sin(radians(" . (float)$source->lat . ")) * sin(radians(bai_dang.lat)) ))";
            $candidatesQuery->addSelect(DB::raw($distanceExprSelect . " as distance_km"))
                ->orderBy('distance_km', 'asc');
        } else {
            $candidatesQuery->orderByDesc('created_at');
        }

        $candidates = $candidatesQuery
            ->limit($candidatePrelimit)
            ->get();

        if ($source->lat !== null && $source->lng !== null) {
            $candidates = $candidates->sortBy('distance_km')->take($aiInputLimit)->values();
        } else {
            $candidates = $candidates->take($aiInputLimit)->values();
        }

        $postsPayload = [];
        $allPosts = collect([$source])->concat($candidates);
        $danhMucMap = $this->loadDanhMucMap($allPosts->pluck('id')->all());

        $postsPayload[] = $this->toAiPost($source, $danhMucMap);
        foreach ($candidates as $cand) {
            $postsPayload[] = $this->toAiPost($cand, $danhMucMap);
        }

        $payload = [
            'post_id' => $source->id,
            'posts' => $postsPayload,
            'user_has_address' => $userHasAddress,
            'user_interests' => $userInterests,
        ];

        if (count($postsPayload) < 2) {
            return response()->json([
                'data' => [],
            ]);
        }

        $matches = $aiMatchingService->match($payload);

        $matchedIds = collect($matches)->pluck('post_id')->all();
        $matchedPosts = BaiDang::with(['nguoiDung'])->whereIn('id', $matchedIds)->get()->keyBy('id');

        $responseData = [];
        foreach ($matches as $item) {
            $post = $matchedPosts->get((int)$item['post_id']);
            if (!$post) {
                continue;
            }

            $post->avatar_url = $post->nguoiDung && $post->nguoiDung->anh_dai_dien
                ? asset('storage/' . $post->nguoiDung->anh_dai_dien)
                : null;
            $paths = is_array($post->hinh_anh) ? $post->hinh_anh : [];
            $post->hinh_anh_urls = array_values(array_map(fn($p) => $this->resolveMediaUrl($p), $paths));
            $post->hinh_anh_url = $post->hinh_anh_urls[0] ?? null; // backward compatible

            $post->nguoi_dung_ten = $post->nguoiDung?->ho_ten;
            unset($post->nguoiDung);

            $reasonCodes = is_array($item['reasons'] ?? null) ? $item['reasons'] : [];
            $reasonMapVi = [
                'category_gate' => 'Cùng danh mục nên được ưu tiên',
                'intent_gate' => 'Cùng nhóm nhu cầu (ý định) nên được ưu tiên',
                'geo_ok' => 'Có thể tính khoảng cách theo vị trí',
                'geo_unknown' => 'Không đủ dữ liệu vị trí để tính khoảng cách',
            ];
            $reasonsVi = [];
            foreach ($reasonCodes as $code) {
                $reasonsVi[] = $reasonMapVi[$code] ?? (string)$code;
            }

            $responseData[] = [
                'post' => $post,
                'score' => (float)$item['score'],
                // distance có thể null nếu bài thiếu lat/lng (AI service sẽ fallback theo nội dung)
                'distance_km' => array_key_exists('distance', $item) && $item['distance'] !== null
                    ? (float)$item['distance']
                    : null,
                'match_percent' => (float)$item['match_percent'],
                'reasons' => $reasonsVi,
                'reason_codes' => $reasonCodes,
                'breakdown' => $item['breakdown'] ?? null,
            ];

            GhepNoiAi::updateOrCreate(
                [
                    'bai_dang_nguon_id' => $source->id,
                    'bai_dang_phu_hop_id' => $post->id,
                ],
                [
                    'diem_phu_hop' => (float)$item['score'],
                    'trang_thai' => 'GHEP_NOI',
                ]
            );
        }

        return response()->json([
            'data' => $responseData,
        ]);
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
        foreach ([-2*$step, -$step, 0.0, $step, 2*$step] as $dLat) {
            foreach ([-2*$step, -$step, 0.0, $step, 2*$step] as $dLng) {
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

            $words = preg_split('/\s+/', $keyword);

            $query->where(function ($q) use ($words) {

                foreach ($words as $word) {
                    $q->where(function ($sub) use ($word) {
                        $sub->whereRaw("tieu_de COLLATE utf8mb4_bin LIKE ?", ["%{$word}%"])
                            ->orWhereRaw("mo_ta COLLATE utf8mb4_bin LIKE ?", ["%{$word}%"]);
                    });
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
