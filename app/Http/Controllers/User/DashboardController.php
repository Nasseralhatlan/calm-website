<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Placeholder controller for the "regular user" dashboard tabs that aren't
 * fleshed out yet — bookings, financials, favorites, support, etc.
 *
 * Each method just renders a view; once the backing feature ships
 * (bookings, payments, favorites tables), each method gets its own service.
 */
class DashboardController extends Controller
{
    public function bookings(Request $request): View
    {
        return view('user.bookings', ['user' => $request->user()]);
    }

    public function myBookings(Request $request): View
    {
        return view('user.my-bookings', ['user' => $request->user()]);
    }

    public function financials(Request $request): View
    {
        return view('user.financials', ['user' => $request->user()]);
    }

    public function favorites(Request $request): View
    {
        return view('user.favorites', ['user' => $request->user()]);
    }

    public function support(Request $request): View
    {
        return view('user.support', ['user' => $request->user()]);
    }
}
