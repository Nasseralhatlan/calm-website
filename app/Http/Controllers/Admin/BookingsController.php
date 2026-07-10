<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CancelBookingRequest;
use App\Http\Requests\Admin\MarkPayoutPaidRequest;
use App\Models\Booking;
use App\Services\Booking\BookingService;
use App\Services\Finance\HostPayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingsController extends Controller
{
    public function __construct(private readonly BookingService $service) {}

    public function index(Request $request): View
    {
        $search = $request->string('q')->toString();
        $status = $request->string('status')->toString() ?: null;
        $payoutFailed = $request->boolean('payout_failed');

        return view('admin.bookings.index', [
            'bookings' => $this->service->paginateForAdmin($search, $status, payoutFailed: $payoutFailed),
            'counts' => $this->service->adminStatusCounts(),
            'search' => $search,
            'status' => $status,
            'payoutFailed' => $payoutFailed,
        ]);
    }

    public function show(Booking $booking): View
    {
        $booking->load([
            'place.coverPhoto', 'place.cityArea.city', 'place.type', 'place.publishedReviews.guest', 'guest', 'host',
            // The booking-centric finance panel: documents + money trail.
            'financialDocuments', 'financialMovements',
        ]);

        return view('admin.bookings.show', ['booking' => $booking]);
    }

    /**
     * Re-fire a failed automatic Moyasar transfer with a fresh sequence.
     */
    public function retryPayout(Booking $booking, HostPayoutService $payouts): RedirectResponse
    {
        $started = $payouts->retry($booking);

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('status', $started
                ? __('Transfer for booking :ref started via Moyasar.', ['ref' => $booking->reference])
                : __('Transfer for booking :ref failed again — the reason is shown on this page.', ['ref' => $booking->reference]));
    }

    /**
     * The admin transferred the payout from the company bank by hand and
     * records it here — full finance trail fires (movement, سند صرف, host SMS).
     */
    public function markPayoutPaid(MarkPayoutPaidRequest $request, Booking $booking, HostPayoutService $payouts): RedirectResponse
    {
        $payouts->markPaidManually($booking, $request->bankReference());

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('status', __('Payout for booking :ref recorded as paid manually.', ['ref' => $booking->reference]));
    }

    public function cancel(CancelBookingRequest $request, Booking $booking): RedirectResponse
    {
        $this->service->cancelByAdmin($booking, $request->canceledStatus());

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('status', __('Booking :ref cancelled — the guest and host have been notified.', ['ref' => $booking->reference]));
    }
}
