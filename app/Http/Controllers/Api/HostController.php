<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\HostBookingsIndexRequest;
use App\Http\Requests\Api\HostCalendarDayRequest;
use App\Http\Requests\Api\HostCalendarWindowRequest;
use App\Http\Requests\Api\HostListingsRequest;
use App\Http\Resources\Api\BookingResource;
use App\Http\Resources\Api\HostListingResource;
use App\Http\Resources\Api\PlaceBlockingResource;
use App\Http\Resources\Api\PlaceReviewResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\Booking\BookingService;
use App\Services\Host\HostCalendarService;
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
        private readonly HostCalendarService $calendar,
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

    /**
     * Bookings guests placed on the host's places, newest first, paginated.
     * Optional `?q=` searches the booking reference or the guest's phone.
     */
    public function bookings(HostBookingsIndexRequest $request): JsonResponse
    {
        /** @var User $host */
        $host = $request->user();

        $paginator = $this->bookings->forHostPaginated($host, $request->input('q'));

        return ApiResponse::success(
            data: [
                'items' => BookingResource::collection($paginator->items())->resolve($request),
                'pagination' => self::pagination($paginator),
            ],
            message: 'Host bookings fetched.',
        );
    }

    /**
     * Home-screen highlights: confirmed bookings split into ongoing / today /
     * upcoming buckets, in one unpaginated response. The app decides how to
     * present them; counts are included for tab badges.
     */
    public function bookingHighlights(Request $request): JsonResponse
    {
        /** @var User $host */
        $host = $request->user();

        $buckets = $this->bookings->homeHighlightsForHost($host);

        return ApiResponse::success(
            data: [
                'ongoing' => BookingResource::collection($buckets['ongoing'])->resolve($request),
                'today' => BookingResource::collection($buckets['today'])->resolve($request),
                'upcoming' => BookingResource::collection($buckets['upcoming'])->resolve($request),
                'counts' => [
                    'ongoing' => $buckets['ongoing']->count(),
                    'today' => $buckets['today']->count(),
                    'upcoming' => $buckets['upcoming']->count(),
                ],
            ],
            message: 'Host booking highlights fetched.',
        );
    }

    /**
     * Calendar window: a per-day summary (app bookings + manual/external blocks)
     * across a date range, for the scrollable month/year calendar. Sparse — only
     * days that have something on them are returned. Optional place filter.
     */
    public function calendar(HostCalendarWindowRequest $request): JsonResponse
    {
        /** @var User $host */
        $host = $request->user();

        $window = $this->calendar->window(
            $host,
            $request->input('from'),
            $request->input('to'),
            $request->input('place_id'),
        );

        return ApiResponse::success(
            data: [
                'from' => $window['from'],
                'to' => $window['to'],
                'place_id' => $request->input('place_id'),
                'days' => $window['days'],
            ],
            message: 'Host calendar fetched.',
        );
    }

    /** Everything on one calendar day: bookings occupying it + blockings covering it. */
    public function calendarDay(HostCalendarDayRequest $request): JsonResponse
    {
        /** @var User $host */
        $host = $request->user();

        $day = $this->calendar->day($host, $request->input('date'), $request->input('place_id'));

        return ApiResponse::success(
            data: [
                'date' => $request->input('date'),
                'place_id' => $request->input('place_id'),
                'bookings' => BookingResource::collection($day['bookings'])->resolve($request),
                'blockings' => PlaceBlockingResource::collection($day['blockings'])->resolve($request),
            ],
            message: 'Host day fetched.',
        );
    }

    /**
     * ALL of the host's own places in one response (unpaginated — hosts own a
     * handful of listings), newest first. Every status by default; optional
     * `?status=` narrows to one lifecycle tab (see HostListingsRequest).
     */
    public function listings(HostListingsRequest $request): JsonResponse
    {
        /** @var User $host */
        $host = $request->user();

        $listings = $this->places->listingsForHost($host, $request->input('status'));

        return ApiResponse::success(
            data: [
                'items' => HostListingResource::collection($listings)->resolve($request),
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
