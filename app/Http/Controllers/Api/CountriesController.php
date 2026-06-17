<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\CountryResource;
use App\Http\Responses\ApiResponse;
use App\Services\Geo\CountryService;
use Illuminate\Http\JsonResponse;

class CountriesController extends Controller
{
    public function __construct(private readonly CountryService $service) {}

    public function index(): JsonResponse
    {
        $countries = $this->service->activeForApi();

        return ApiResponse::success(
            data: CountryResource::collection($countries),
            message: 'Countries fetched.',
        );
    }
}
