<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PlaceListResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\Place\PlaceListService;
use App\Services\Place\PlaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlaceListsController extends Controller
{
    public function __construct(
        private readonly PlaceListService $service,
        private readonly PlaceService $places,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User|null $viewer */
        $viewer = $request->user();

        $lists = $this->service->activeForApi($this->places, $viewer);

        return ApiResponse::success(
            data: PlaceListResource::collection($lists),
            message: 'Curated lists fetched.',
        );
    }
}
