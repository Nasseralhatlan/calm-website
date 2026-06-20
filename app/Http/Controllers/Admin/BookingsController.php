<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CancelBookingRequest;
use App\Models\Booking;
use App\Services\Booking\BookingService;
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

        return view('admin.bookings.index', [
            'bookings' => $this->service->paginateForAdmin($search, $status),
            'counts' => $this->service->adminStatusCounts(),
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function show(Booking $booking): View
    {
        $booking->load(['place.coverPhoto', 'place.cityArea.city', 'place.type', 'place.publishedReviews.guest', 'guest', 'host']);

        return view('admin.bookings.show', ['booking' => $booking]);
    }

    public function cancel(CancelBookingRequest $request, Booking $booking): RedirectResponse
    {
        $this->service->cancelByAdmin($booking, $request->canceledStatus());

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('status', __('Booking :ref cancelled — the guest and host have been notified.', ['ref' => $booking->reference]));
    }
}
