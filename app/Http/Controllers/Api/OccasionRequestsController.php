<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreOccasionRequest;
use App\Http\Resources\Api\OccasionRequestResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\Occasion\OccasionRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OccasionRequestsController extends Controller
{
    public function __construct(private readonly OccasionRequestService $service) {}

    /** The guest's own occasion requests, newest first — read-only tracking. */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $paginator = $this->service->forUserPaginated($user);

        return ApiResponse::success(
            data: [
                'items' => OccasionRequestResource::collection($paginator->items())->resolve($request),
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'has_more' => $paginator->hasMorePages(),
                ],
            ],
            message: 'Occasion requests fetched.',
        );
    }

    /**
     * Record an occasion planning lead and alert the events team. The guest is
     * told on screen that we'll call within minutes, so this must stay fast —
     * the team alert is queued, never awaited.
     */
    public function store(StoreOccasionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $occasionRequest = $this->service->create(
            $user,
            $request->validated('occasion_type'),
            $request->details(),
        );

        return ApiResponse::success(
            data: OccasionRequestResource::make($occasionRequest)->resolve($request),
            message: 'Occasion request received — our events team will be in touch with ideas.',
            status: 201,
        );
    }
}
