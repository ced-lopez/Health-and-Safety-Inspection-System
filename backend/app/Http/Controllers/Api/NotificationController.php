<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $perPage = min($request->integer('per_page', 20), 50);
        $notifications = $user->notifications()->paginate($perPage);

        return $this->success([
            'notifications' => $notifications->through(fn ($notification) => [
                'id' => $notification->id,
                'type' => class_basename($notification->type),
                'data' => $notification->data,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ])->items(),
            'unread_count' => $user->unreadNotifications()->count(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ], 'Notifications retrieved successfully');
    }

    public function markAsRead(Request $request, DatabaseNotification $notification): JsonResponse
    {
        if ($notification->notifiable_id !== $request->user()->id) {
            return $this->error('Unauthorized', 403);
        }

        $notification->markAsRead();

        return $this->success([
            'id' => $notification->id,
            'read_at' => $notification->read_at?->toIso8601String(),
        ], 'Notification marked as read');
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $this->success(null, 'All notifications marked as read');
    }

    public function destroy(Request $request, DatabaseNotification $notification): JsonResponse
    {
        if ($notification->notifiable_id !== $request->user()->id) {
            return $this->error('Unauthorized', 403);
        }

        $notification->delete();

        return $this->success(null, 'Notification deleted');
    }
}
