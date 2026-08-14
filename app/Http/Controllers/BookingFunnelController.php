<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Http\Requests\StoreFunnelBookingRequest;
use App\Models\Booking;
use App\Models\Country;
use App\Models\Place;
use App\Services\Booking\BookingFunnelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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

        // Active countries drive the dial-code picker (same source as the
        // login page). Saudi first — it's the default market.
        $countries = Country::query()
            ->active()
            ->whereNotNull('dial_code')
            ->orderByRaw("country_code = 'SA' desc")
            ->orderBy('name_en')
            ->get(['id', 'country_code', 'dial_code', 'avatar', 'name_ar', 'name_en']);

        return view('booking.funnel', ['place' => $place, 'countries' => $countries]);
    }

    /**
     * Checkout summary page — reached from the place page's dates modal (or
     * directly from search with the stay in the query string). Invalid or
     * past dates bounce back to the place page instead of erroring.
     */
    public function checkout(Request $request, Place $place): View|RedirectResponse
    {
        abort_unless(
            $place->status === PlaceStatus::Active
            && $place->review_status === PlaceReviewStatus::Approved,
            404,
        );

        $checkIn = (string) $request->query('check_in', '');
        $checkOut = (string) $request->query('check_out', '');
        $isDate = fn (string $d): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;

        if (! $isDate($checkIn) || ! $isDate($checkOut) || $checkOut < $checkIn || $checkIn < now()->toDateString()) {
            return redirect()->route('places.show', $place);
        }

        $place->load(['type', 'cityArea.city', 'coverPhoto']);
        // Rating line on the summary card (★ 5.00 (2) · مميز).
        $place->loadCount('publishedReviews')->loadAvg('publishedReviews', 'rate');

        return view('booking.checkout', [
            'place' => $place,
            'checkIn' => $checkIn,
            'checkOut' => $checkOut,
            'guests' => min(max(1, (int) $request->query('guests', 1)), (int) ($place->max_guests ?: 1)),
        ]);
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

        // Moyasar's in-page back button lands with ?back=1: settle truthfully
        // (race-paid → confirmed; still pending → hold released) instead of
        // leaving the customer on an endless "confirming…" spinner.
        if ($request->boolean('back')) {
            $booking = $this->funnel->settleOnBack($booking);
        }

        $booking->load(['place.coverPhoto', 'place.type', 'place.cityArea.city']);
        // Rating for the app-style summary card (★ 4.80 (12)).
        $booking->place?->loadCount('publishedReviews');
        $booking->place?->loadAvg('publishedReviews', 'rate');

        return view('booking.status', ['booking' => $booking]);
    }
}
