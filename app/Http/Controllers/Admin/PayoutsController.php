<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePayoutStatusRequest;
use App\Models\Booking;
use App\Services\Booking\BookingService;
use App\Services\Finance\HostPayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Host payouts. In manual mode (default) the operator makes bank transfers by
 * hand: the queue lists completed stays not yet paid out — with the host's
 * IBAN inline — and each row is settled with "Mark paid". With
 * MOYASAR_PAYOUTS_MODE=auto the scheduler executes transfers via Moyasar and
 * this page becomes a monitor: a processing tab tracks in-flight transfers,
 * failed rows carry the reason + a Retry button, and manual mark-paid remains
 * the fallback. The paid tab keeps the audit trail and allows undoing a
 * mistaken manual settlement.
 */
class PayoutsController extends Controller
{
    public function __construct(private readonly BookingService $service) {}

    public function index(Request $request): View
    {
        $search = $request->string('q')->toString();
        $requested = $request->string('tab')->toString();
        $tab = in_array($requested, ['paid', 'processing'], true) ? $requested : 'pending';

        $data = $this->service->payoutsIndexData($search, $tab);

        return view('admin.payouts.index', [
            'bookings' => $data['bookings'],
            'totals' => $data['totals'],
            'search' => $search,
            'tab' => $tab,
        ]);
    }

    /** Settle (or revert) one booking's host payout. */
    public function update(UpdatePayoutStatusRequest $request, Booking $booking): RedirectResponse
    {
        $booking = $this->service->setPayoutStatus(
            $booking,
            (string) $request->validated('payout_status'),
            $request->validated('payout_reference'),
        );

        return redirect()
            ->back()
            ->with('status', $booking->payout_status === 'paid'
                ? __('Booking :ref marked as paid out.', ['ref' => $booking->reference])
                : __('Booking :ref returned to the payout queue.', ['ref' => $booking->reference]));
    }

    /** Re-attempt a failed automatic Moyasar transfer for one booking. */
    public function retry(Booking $booking, HostPayoutService $payouts): RedirectResponse
    {
        $started = $payouts->retry($booking);

        return redirect()
            ->back()
            ->with('status', $started
                ? __('Transfer for booking :ref started via Moyasar.', ['ref' => $booking->reference])
                : __('Transfer for booking :ref failed again — the reason is shown in the queue.', ['ref' => $booking->reference]));
    }
}
