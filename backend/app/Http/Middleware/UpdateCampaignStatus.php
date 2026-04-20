<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateCampaignStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        \App\Models\ChienDichGayQuy::where('trang_thai', 'HOAT_DONG')
        ->whereDate('ngay_ket_thuc', '<=', now())
        ->update(['trang_thai' => 'DA_KET_THUC']);

        return $next($request);
    }
}
