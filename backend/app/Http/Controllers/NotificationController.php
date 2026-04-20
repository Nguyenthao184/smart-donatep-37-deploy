<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        /** @var User $user */
        $user = Auth::user();

        $notifications = $user->notifications()
            ->latest()
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $notifications,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * POST /api/notifications/{id}/read
     */
    public function markAsRead(string $id)
    {
        /** @var User $user */
        $user = Auth::user();

        $notification = $user->notifications()
            ->where('id', $id)
            ->first();

        if (!$notification) {
            return response()->json(['message' => 'Không tìm thấy thông báo.'], 404);
        }

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return response()->json([
            'message' => 'Đã đánh dấu đã đọc.',
            'unread_count' => $user->fresh()->unreadNotifications()->count(),
        ]);
    }

    /**
     * POST /api/notifications/read-all
     */
    public function markAllAsRead()
    {
        /** @var User $user */
        $user = Auth::user();
        $user->unreadNotifications->markAsRead();

        return response()->json([
            'message' => 'Đã đánh dấu tất cả là đã đọc.',
            'unread_count' => 0,
        ]);
    }
}
