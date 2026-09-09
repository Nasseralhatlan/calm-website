<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CancelBookingRequest;
use App\Http\Requests\Admin\ForcePayoutRequest;
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

    public function show(Booking $booking, HostPayoutService $payouts): View
    {
        $booking->load([
            'place.coverPhoto', 'place.cityArea.city', 'place.type', 'place.publishedReviews.guest', 'guest', 'host', 'unit',
            // The booking-centric finance panel: documents + money trail.
            'financialDocuments', 'financialMovements', 'payoutSettledBy',
        ]);

        return view('admin.bookings.show', [
            'booking' => $booking,
            // Drives the "Pay now via Moyasar" button — pointless while
            // payouts are in manual mode (execute() would only record a
            // failure against an empty source account).
            'payoutsAutoMode' => $payouts->autoModeEnabled(),
        ]);
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
     * Release the payout through Moyasar NOW, ahead of the stay completing or
     * the hold window closing. Documents are issued first, so the books never
     * lag the money.
     */
    public function payoutNow(ForcePayoutRequest $request, Booking $booking, HostPayoutService $payouts): RedirectResponse
    {
        $started = $payouts->payoutNow($booking, $request->note(), $request->user());

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('status', $started
                ? __('Early transfer for booking :ref started via Moyasar — invoices were issued first.', ['ref' => $booking->reference])
                : __('Early transfer for booking :ref could not be started — the reason is shown on this page.', ['ref' => $booking->reference]));
    }

    /**
     * The admin settled the payout by hand — a company bank transfer or cash —
     * and records it here. Full finance trail fires (invoices if they were not
     * issued yet, movement, سند صرف, host SMS).
     */
    public function markPayoutPaid(MarkPayoutPaidRequest $request, Booking $booking, HostPayoutService $payouts): RedirectResponse
    {
        $payouts->markPaidManually(
            $booking,
            $request->bankReference(),
            $request->method(),
            $request->note(),
            $request->user(),
        );

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('status', $request->method() === 'cash'
                ? __('Payout for booking :ref recorded as paid in cash.', ['ref' => $booking->reference])
                : __('Payout for booking :ref recorded as paid manually.', ['ref' => $booking->reference]));
    }

    public function cancel(CancelBookingRequest $request, Booking $booking): RedirectResponse
    {
        $this->service->cancelByAdmin($booking, $request->canceledStatus());

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('status', __('Booking :ref cancelled — the guest and host have been notified.', ['ref' => $booking->reference]));
    }
}
