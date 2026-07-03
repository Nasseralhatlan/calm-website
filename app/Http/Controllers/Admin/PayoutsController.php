<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePayoutStatusRequest;
use App\Models\Booking;
use App\Services\Booking\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Manual host payouts. Payouts are bank transfers the operator makes by hand
 * (no payment-provider integration yet): the queue lists completed stays not
 * yet paid out — with the host's IBAN inline — and each row is settled one by
 * one with "Mark paid". The paid tab keeps the audit trail and allows undoing
 * a mistaken settlement.
 */
class PayoutsController extends Controller
{
    public function __construct(private readonly BookingService $service) {}

    public function index(Request $request): View
    {
        $search = $request->string('q')->toString();
        $tab = $request->string('tab')->toString() === 'paid' ? 'paid' : 'pending';

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
}
