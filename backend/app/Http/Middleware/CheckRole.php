<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = Auth::user();

        // chưa đăng nhập
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn chưa đăng nhập.'
            ], 401);
        }

        // kiểm tra role
        if (!empty($roles)) {

            $hasRole = $user->roles()
                ->whereIn('ten_vai_tro', $roles)
                ->exists();

            if (!$hasRole) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền truy cập.'
                ], 403);
            }
        }

        return $next($request);
    }
}