<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserNotificationResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The authenticated user's in-app notification feed.
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $paginator = $user->userNotifications()->paginate(config('pagination.per_page'));

        return ApiResponse::success(
            data: [
                'items' => UserNotificationResource::collection($paginator->items())->resolve($request),
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'has_more' => $paginator->hasMorePages(),
                ],
            ],
            message: 'Notifications fetched.',
        );
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = $request->user()->userNotifications()->unread()->count();

        return ApiResponse::success(data: ['count' => $count], message: 'Unread count fetched.');
    }

    public function read(Request $request, UserNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return ApiResponse::success(message: 'Notification marked read.');
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->userNotifications()->unread()->update(['read_at' => now()]);

        return ApiResponse::success(message: 'All notifications marked read.');
    }
}
