<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\CityResource;
use App\Http\Responses\ApiResponse;
use App\Services\Geo\CityService;
use Illuminate\Http\JsonResponse;

class CitiesController extends Controller
{
    public function __construct(private readonly CityService $service) {}

    public function index(): JsonResponse
    {
        $cities = $this->service->activeForApi();

        return ApiResponse::success(
            data: CityResource::collection($cities),
            message: 'Cities fetched.',
        );
    }
}
