<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PlaceFiltersRequest;
use App\Http\Requests\Api\SearchPlacesRequest;
use App\Http\Resources\Api\PlaceDetailResource;
use App\Http\Resources\Api\PlaceResource;
use App\Http\Responses\ApiResponse;
use App\Models\Place;
use App\Models\User;
use App\Services\Place\PlaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlacesController extends Controller
{
    public function __construct(private readonly PlaceService $service) {}

    /**
     * Place search. `city_id` is required; every other filter is optional and
     * narrows the results. Public + auth-aware (is_liked reflects the viewer).
     * Paginated.
     */
    public function search(SearchPlacesRequest $request): JsonResponse
    {
        /** @var User|null $viewer */
        $viewer = $request->user();

        $paginator = $this->service->search($request->filters(), $viewer);

        return ApiResponse::success(
            data: [
                'items' => PlaceResource::collection($paginator->items())->resolve($request),
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'has_more' => $paginator->hasMorePages(),
                ],
            ],
            message: 'Search results fetched.',
        );
    }

    /**
     * Available filter options for a city's filters page — the amenities/types/
     * areas actually in use plus the real price + guest ranges. Public.
     */
    public function filters(PlaceFiltersRequest $request): JsonResponse
    {
        return ApiResponse::success(
            data: $this->service->filterFacets($request->validated('city_id')),
            message: 'Filters fetched.',
        );
    }

    /** Most-liked Active+Approved places, capped at 20. */
    public function mostLiked(Request $request): JsonResponse
    {
        /** @var User|null $viewer */
        $viewer = $request->user();

        $places = $this->service->mostLiked($viewer, 20);

        return ApiResponse::success(
            data: PlaceResource::collection($places),
            message: 'Most-liked places fetched.',
        );
    }

    /**
     * The authed user's own liked places ("My favorites"), newest-liked first,
     * paginated. Same place-card shape as the home feed; every card is_liked.
     */
    public function favorites(Request $request): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();

        $paginator = $this->service->likedByUser($viewer);

        return ApiResponse::success(
            data: [
                'items' => PlaceResource::collection($paginator->items())->resolve($request),
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'has_more' => $paginator->hasMorePages(),
                ],
            ],
            message: 'Liked places fetched.',
        );
    }

    /**
     * Single-place detail. Public + auth-aware (is_liked flips when a Bearer
     * token is supplied). 404s on any place that isn't Active + Approved so
     * drafts and rejected listings never leak via direct URL.
     */
    public function show(Request $request, Place $place): JsonResponse
    {
        /** @var User|null $viewer */
        $viewer = $request->user();

        $place = $this->service->findForApi($place, $viewer);

        if ($place === null) {
            return ApiResponse::error('Place not found.', 404);
        }

        return ApiResponse::success(
            data: PlaceDetailResource::make($place)->resolve($request),
            message: 'Place fetched.',
        );
    }
}
