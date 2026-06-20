<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreReviewRequest;
use App\Http\Resources\Api\PlaceReviewResource;
use App\Http\Responses\ApiResponse;
use App\Models\Booking;
use App\Models\PlaceReview;
use App\Services\Place\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewsController extends Controller
{
    public function __construct(private readonly ReviewService $service) {}

    /**
     * Post a review for a completed booking's place. One review per (place,
     * guest); 422 if the stay isn't completed or the place was already reviewed.
     */
    public function store(StoreReviewRequest $request, Booking $booking): JsonResponse
    {
        abort_unless($booking->guest_user_id === $request->user()?->id, 403);

        $review = $this->service->createForBooking(
            $booking,
            (int) $request->validated('rate'),
            $request->validated('comment'),
        );

        return ApiResponse::success(
            data: PlaceReviewResource::make($review)->resolve($request),
            message: 'Review submitted — pending review.',
            status: 201,
        );
    }

    /** Guest removes their own review (soft delete). Blocked reviews can't go. */
    public function destroy(Request $request, PlaceReview $review): JsonResponse
    {
        abort_unless($review->guest_user_id === $request->user()?->id, 403);

        $this->service->deleteOwn($review);

        return ApiResponse::success(message: 'Review deleted.');
    }
}
