<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Http\Requests\StoreFunnelBookingRequest;
use App\Models\Booking;
use App\Models\Place;
use App\Services\Booking\BookingFunnelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Web booking funnel — the shareable /book/{place} link. One custom page:
 * place info on top, a big date-range calendar below, inline OTP login for
 * new customers, then straight to Moyasar and back to the status page.
 * Same booking rails as the app; only the journey surface is web.
 */
class BookingFunnelController extends Controller
{
    public function __construct(private readonly BookingFunnelService $funnel) {}

    public function show(Place $place): View
    {
        // Only live listings are bookable — drafts/pending/rejected/paused 404.
        abort_unless(
            $place->status === PlaceStatus::Active
            && $place->review_status === PlaceReviewStatus::Approved,
            404,
        );

        $place->load(['type', 'cityArea.city', 'coverPhoto']);

        return view('booking.funnel', ['place' => $place]);
    }

    public function store(StoreFunnelBookingRequest $request, Place $place): JsonResponse
    {
        abort_unless(
            $place->status === PlaceStatus::Active
            && $place->review_status === PlaceReviewStatus::Approved,
            404,
        );

        $booking = $this->funnel->book($request->user(), $place, $request->validated());

        // The page JS sends the browser to Moyasar's hosted payment page.
        return response()->json([
            'payment_url' => $booking->payment_url,
            'status_url' => route('book.status', $booking),
        ]);
    }

    /** Moyasar returns the payer here; the page polls until the webhook confirms. */
    public function status(Request $request, Booking $booking): View
    {
        abort_unless($booking->guest_user_id === $request->user()->id, 404);

        $booking->load(['place.coverPhoto', 'place.type', 'place.cityArea.city']);

        return view('booking.status', ['booking' => $booking]);
    }
}
