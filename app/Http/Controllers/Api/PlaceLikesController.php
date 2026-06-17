<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Place;
use App\Models\User;
use App\Services\Place\PlaceLikeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlaceLikesController extends Controller
{
    public function __construct(private readonly PlaceLikeService $service) {}

    public function store(Request $request, Place $place): JsonResponse
    {
        // Can't like a draft/inactive/rejected listing. (Unlike stays unguarded
        // so a user can always remove a place that later went private.)
        abort_unless($place->isVisible(), 404);

        /** @var User $user */
        $user = $request->user();

        $this->service->like($user, $place);

        return ApiResponse::success(
            data: ['is_liked' => true],
            message: 'Place liked.',
        );
    }

    public function destroy(Request $request, Place $place): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->service->unlike($user, $place);

        return ApiResponse::success(
            data: ['is_liked' => false],
            message: 'Place unliked.',
        );
    }
}
