<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Host\StoreBlockingRequest;
use App\Http\Resources\Api\PlaceBlockingResource;
use App\Http\Responses\ApiResponse;
use App\Models\Place;
use App\Models\PlaceBlocking;
use App\Services\Place\PlaceAvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mobile host availability: block / unblock / list date ranges on one of the
 * host's own places. Blocks show as `manual_block` on the host calendar and are
 * treated as unavailable everywhere (search, quote, unavailable-dates) because
 * availability unions bookings + blockings. Owner-only — a host can only manage
 * their own place's dates (admin acting-on-behalf stays on the web).
 */
class HostBlockingController extends Controller
{
    public function __construct(private readonly PlaceAvailabilityService $service) {}

    /** Upcoming blocks for one of the host's places. */
    public function index(Request $request, Place $place): JsonResponse
    {
        $this->authorizeOwner($request, $place);

        $blockings = $this->service->upcomingBlockingsForHost($place);

        return ApiResponse::success(
            data: [
                'place_id' => $place->id,
                'blockings' => PlaceBlockingResource::collection($blockings)->resolve($request),
            ],
            message: 'Host blockings fetched.',
        );
    }

    /** Block a date range (additive — never touches existing bookings). */
    public function store(StoreBlockingRequest $request, Place $place): JsonResponse
    {
        $this->authorizeOwner($request, $place);

        $blocking = $this->service->block($place, $request->blockingData());

        return ApiResponse::success(
            data: (new PlaceBlockingResource($blocking))->resolve($request),
            message: 'Dates blocked.',
            status: Response::HTTP_CREATED,
        );
    }

    /** Lift a blocked range. */
    public function destroy(Request $request, Place $place, PlaceBlocking $blocking): JsonResponse
    {
        $this->authorizeOwner($request, $place);
        // Defence-in-depth alongside the route's scoped binding: the blocking
        // must belong to this place, never another's.
        abort_unless($blocking->place_id === $place->id, Response::HTTP_NOT_FOUND);

        $this->service->unblock($blocking);

        return ApiResponse::success(message: 'Block removed — those dates are available again.');
    }

    /** Only the place's own host may manage its availability. */
    private function authorizeOwner(Request $request, Place $place): void
    {
        abort_unless($place->host_user_id === $request->user()?->id, Response::HTTP_FORBIDDEN);
    }
}
