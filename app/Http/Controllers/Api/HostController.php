<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\BookingResource;
use App\Http\Resources\Api\HostListingResource;
use App\Http\Resources\Api\PlaceReviewResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\Booking\BookingService;
use App\Services\Place\PlaceService;
use App\Services\Place\ReviewService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Host-app endpoints for the authenticated user acting as a host: bookings on
 * their places, their own listings, earnings, and published reviews.
 */
class HostController extends Controller
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly PlaceService $places,
        private readonly ReviewService $reviews,
    ) {}

    /** Published reviews on the host's places, newest first, paginated. */
    public function reviews(Request $request): JsonResponse
    {
        /** @var User $host */
        $host = $request->user();

        $paginator = $this->reviews->publishedForHost($host);

        return ApiResponse::success(
            data: [
                'items' => PlaceReviewResource::collection($paginator->items())->resolve($request),
                'pagination' => self::pagination($paginator),
            ],
            message: 'Host reviews fetched.',
        );
    }

    /** Bookings guests placed on the host's places, newest first, paginated. */
    public function bookings(Request $request): JsonResponse
    {
        /** @var User $host */
        $host = $request->user();

        $paginator = $this->bookings->forHostPaginated($host);

        return ApiResponse::success(
            data: [
                'items' => BookingResource::collection($paginator->items())->resolve($request),
                'pagination' => self::pagination($paginator),
            ],
            message: 'Host bookings fetched.',
        );
    }

    /** The host's own places (every status), newest first, paginated. */
    public function listings(Request $request): JsonResponse
    {
        /** @var User $host */
        $host = $request->user();

        $paginator = $this->places->listingsForHost($host);

        return ApiResponse::success(
            data: [
                'items' => HostListingResource::collection($paginator->items())->resolve($request),
                'pagination' => self::pagination($paginator),
            ],
            message: 'Host listings fetched.',
        );
    }

    /** The host's earnings: total, paid, and not-yet-paid-out. */
    public function earnings(Request $request): JsonResponse
    {
        /** @var User $host */
        $host = $request->user();

        return ApiResponse::success(
            data: $this->bookings->earningsForHost($host),
            message: 'Host earnings fetched.',
        );
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @return array<string, mixed>
     */
    private static function pagination($paginator): array
    {
        return [
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'has_more' => $paginator->hasMorePages(),
        ];
    }
}
