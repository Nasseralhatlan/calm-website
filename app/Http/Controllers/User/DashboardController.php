<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Booking\BookingService;
use App\Services\Place\SettingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The "regular user" dashboard tabs. Bookings and financials are live;
 * favorites/support are still placeholders awaiting their backing features.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly BookingService $bookings) {}

    /** Host view: bookings guests placed on this user's places. */
    public function bookings(Request $request): View
    {
        return view('user.bookings', [
            'bookings' => $this->bookings->forHostPaginated($request->user()),
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
    public function showBooking(Request $request, Booking $booking, SettingService $settings): View
    {
        $viewer = $request->user();
        $booking = $this->bookings->detailForViewer($booking, $viewer);

        abort_if($booking === null, 404);

        return view('user.booking-detail', [
            'booking' => $booking,
            'isHost' => $booking->host_user_id === $viewer->id,
            'support' => $settings->byKeys(['support_phone', 'support_email']),
        ]);
    }

    /**
     * Host finances: earnings summary (total / paid out / pending payout),
     * the payout bank account, and the per-booking transaction list.
     */
    public function financials(Request $request): View
    {
        $finance = $this->bookings->financeForHost($request->user());

        return view('user.financials', [
            'user' => $request->user(),
            'earnings' => $finance['earnings'],
            'bookings' => $finance['bookings'],
        ]);
    }

    public function favorites(Request $request): View
    {
        return view('user.favorites', ['user' => $request->user()]);
    }
}
