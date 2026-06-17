<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PlaceTypeResource;
use App\Http\Responses\ApiResponse;
use App\Services\Place\PlaceTypeService;
use Illuminate\Http\JsonResponse;

class PlaceTypesController extends Controller
{
    public function __construct(private readonly PlaceTypeService $service) {}

    public function index(): JsonResponse
    {
        $types = $this->service->activeForApi();

        return ApiResponse::success(
            data: PlaceTypeResource::collection($types),
            message: 'Place types fetched.',
        );
    }
}
