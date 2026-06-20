<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBookingRequest;
use App\Http\Resources\Api\BookingResource;
use App\Http\Responses\ApiResponse;
use App\Models\Booking;
use App\Models\Place;
use App\Models\User;
use App\Services\Booking\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingsController extends Controller
{
    public function __construct(private readonly BookingService $service) {}

    /**
     * The guest's own bookings ("My bookings"), newest first, paginated. Each
     * item carries the place summary, dates, status, and price.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $paginator = $this->service->forGuestPaginated($user);

        return ApiResponse::success(
            data: [
                'items' => BookingResource::collection($paginator->items())->resolve($request),
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'has_more' => $paginator->hasMorePages(),
                ],
            ],
            message: 'Bookings fetched.',
        );
    }

    /**
     * The guest's still-payable holds — pending_payment bookings whose hold
     * hasn't lapsed. Drives a "finish your payment" card on the home screen;
     * each item carries `payment.url` and `expires_at`.
     */
    public function pending(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $bookings = $this->service->pendingPaymentsForGuest($user);

        return ApiResponse::success(
            data: ['items' => BookingResource::collection($bookings)->resolve($request)],
            message: 'Pending payments fetched.',
        );
    }

    /**
     * "Click book": re-verifies availability + pricing server-side, holds the
     * dates with a pending_payment booking, and opens a Moyasar invoice. Returns
     * the booking with `payment.url` for the client to open. 404 if the place
     * isn't visible, 422 if the dates are taken or the party is too large.
     */
    public function store(StoreBookingRequest $request, Place $place): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $booking = $this->service->create(
            $user,
            $place,
            $request->validated('check_in'),
            $request->validated('check_out'),
            (int) $request->validated('guests'),
        );

        return ApiResponse::success(
            data: BookingResource::make($booking)->resolve($request),
            message: 'Booking created — proceed to payment.',
            status: 201,
        );
    }

    /**
     * Poll a booking's payment: re-verifies against Moyasar and confirms the
     * booking when paid. Scoped to the booking's owner.
     */
    public function paymentStatus(Request $request, Booking $booking): JsonResponse
    {
        abort_unless($booking->guest_user_id === $request->user()?->id, 403);

        $booking = $this->service->checkPaymentStatus($booking);

        return ApiResponse::success(
            data: BookingResource::make($booking)->resolve($request),
            message: 'Payment status checked.',
        );
    }

    /**
     * Release a still-unpaid booking when the guest backs out of the hosted
     * payment page. Owner-only. No-ops on a confirmed booking (re-verifies
     * against Moyasar first, so a race-paid booking is confirmed, not cancelled).
     */
    public function cancel(Request $request, Booking $booking): JsonResponse
    {
        abort_unless($booking->guest_user_id === $request->user()?->id, 403);
        $booking = $this->service->cancelIfPending($booking);

        return ApiResponse::success(
            data: BookingResource::make($booking)->resolve($request),
            message: 'Booking cancellation processed.',
        );
    }
}
