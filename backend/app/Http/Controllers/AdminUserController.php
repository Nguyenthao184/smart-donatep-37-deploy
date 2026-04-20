<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\CanhBaoGianLan;
use App\Models\XacMinhToChuc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminUserController extends Controller
{
    /**
     * GET /api/admin/users
     * Query: search, role, status, page, per_page
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $roleFilter = strtoupper((string) $request->query('role', ''));
        $statusFilter = strtoupper((string) $request->query('status', ''));

        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(5, min($perPage, 100));

        $subQueryRole = DB::table('nguoi_dung_vai_tro as ndvt')
            ->join('vai_tro as vt', 'vt.id', '=', 'ndvt.vai_tro_id')
            ->select('ndvt.nguoi_dung_id', DB::raw('MIN(vt.ten_vai_tro) as primary_role'))
            ->groupBy('ndvt.nguoi_dung_id');

        $subQueryOrg = DB::table('xac_minh_to_chuc as xmtc')
            ->select('xmtc.nguoi_dung_id', 'xmtc.trang_thai')
            ->orderByDesc('xmtc.id');

        $subQueryViolation = DB::table('canh_bao_gian_lan as cb')
            ->select('cb.nguoi_dung_id', DB::raw('COUNT(*) as violation_count'))
            ->groupBy('cb.nguoi_dung_id');

        $query = User::query()
            ->leftJoinSub($subQueryRole, 'r', 'r.nguoi_dung_id', '=', 'nguoi_dung.id')
            ->leftJoinSub($subQueryOrg, 'org', 'org.nguoi_dung_id', '=', 'nguoi_dung.id')
            ->leftJoinSub($subQueryViolation, 'v', 'v.nguoi_dung_id', '=', 'nguoi_dung.id')
            ->select(
                'nguoi_dung.id',
                'nguoi_dung.ho_ten',
                'nguoi_dung.email',
                'nguoi_dung.trang_thai',
                'nguoi_dung.created_at',
                DB::raw('COALESCE(r.primary_role, "NGUOI_DUNG") as primary_role'),
                DB::raw('org.trang_thai as org_status'),
                DB::raw('COALESCE(v.violation_count, 0) as violation_count')
            )
            ->orderByDesc('nguoi_dung.created_at');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nguoi_dung.ho_ten', 'like', '%' . $search . '%')
                    ->orWhere('nguoi_dung.email', 'like', '%' . $search . '%');
            });
        }

        if (in_array($statusFilter, ['HOAT_DONG', 'BI_CAM'], true)) {
            $query->where('nguoi_dung.trang_thai', $statusFilter);
        }

        if (in_array($roleFilter, ['ADMIN', 'TO_CHUC', 'NGUOI_DUNG'], true)) {
            if ($roleFilter === 'NGUOI_DUNG') {
                $query->where(function ($q) {
                    $q->whereNull('primary_role')
                        ->orWhere('primary_role', 'NGUOI_DUNG');
                });
            } else {
                $query->where('primary_role', $roleFilter);
            }
        }

        $paginator = $query->paginate($perPage);

        $items = collect($paginator->items())->map(function ($row) {
            $roleName = strtoupper((string) ($row->primary_role ?? 'NGUOI_DUNG'));

            $displayRole = match ($roleName) {
                'ADMIN' => 'Quản trị viên',
                'TO_CHUC' => 'Tổ chức',
                default => 'Người dùng',
            };

            $accountStatus = strtoupper((string) $row->trang_thai);
            $orgStatus = strtoupper((string) ($row->org_status ?? ''));

            if ($accountStatus === 'BI_CAM') {
                $displayStatus = 'Đã khóa';
            } elseif ($orgStatus === 'CHO_XU_LY') {
                $displayStatus = 'Chờ duyệt';
            } else {
                $displayStatus = 'Hoạt động';
            }

            return [
                'id' => (int) $row->id,
                'name' => $row->ho_ten,
                'email' => $row->email,
                'role' => $roleName,
                'role_label' => $displayRole,
                'status' => $accountStatus,
                'status_label' => $displayStatus,
                'joined_at' => $row->created_at?->toDateString(),
                'violation_count' => (int) $row->violation_count,
            ];
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/admin/users/{id}/lock
     * POST /api/admin/users/{id}/unlock
     */
    public function lock(int $id)
    {
        $user = User::findOrFail($id);
        $user->trang_thai = 'BI_CAM';
        $user->save();

        return response()->json([
            'data' => [
                'id' => (int) $user->id,
                'status' => $user->trang_thai,
            ],
        ]);
    }

    public function unlock(int $id)
    {
        $user = User::findOrFail($id);
        $user->trang_thai = 'HOAT_DONG';
        $user->save();

        return response()->json([
            'data' => [
                'id' => (int) $user->id,
                'status' => $user->trang_thai,
            ],
        ]);
    }
}

