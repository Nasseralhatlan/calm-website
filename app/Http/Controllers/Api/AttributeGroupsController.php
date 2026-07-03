<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\AttributeGroupResource;
use App\Http\Responses\ApiResponse;
use App\Services\Place\AttributeGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public amenity catalog for the place wizard: groups + their attributes in
 * admin-controlled order. Read-only reference data, same set the web wizard
 * embeds in its page.
 */
class AttributeGroupsController extends Controller
{
    public function __construct(private readonly AttributeGroupService $service) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(
            data: AttributeGroupResource::collection($this->service->catalog())->resolve($request),
            message: 'Attribute groups fetched.',
        );
    }
}
