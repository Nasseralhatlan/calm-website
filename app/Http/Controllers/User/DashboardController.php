<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Booking\BookingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The "regular user" dashboard tabs. Bookings are live; the rest
 * (financials, favorites, support) are still placeholders awaiting their
 * backing features.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly BookingService $bookings) {}

    /** Host view: bookings guests placed on this user's places. */
    public function bookings(Request $request): View
    {
        return view('user.bookings', [
            'bookings' => $this->bookings->forHost($request->user()),
        ]);
    }

    /** Guest view: bookings this user made. */
    public function myBookings(Request $request): View
    {
        return view('user.my-bookings', [
            'bookings' => $this->bookings->forGuest($request->user()),
        ]);
    }

    /** Detail of one booking — viewable by its guest or its host only. */
    public function showBooking(Request $request, Booking $booking): View
    {
        $viewer = $request->user();
        $booking = $this->bookings->detailForViewer($booking, $viewer);

        abort_if($booking === null, 404);

        return view('user.booking-detail', [
            'booking' => $booking,
            'isHost' => $booking->host_user_id === $viewer->id,
        ]);
    }

    public function financials(Request $request): View
    {
        return view('user.financials', ['user' => $request->user()]);
    }

    public function favorites(Request $request): View
    {
        return view('user.favorites', ['user' => $request->user()]);
    }
}
