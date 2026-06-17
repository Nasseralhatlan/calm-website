<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Landing pages Moyasar redirects the guest's WebView to after the hosted
 * payment page. These are intentionally dumb — the mobile app detects the URL
 * (e.g. url.includes('calm-after-payment')) and drives confirmation itself via
 * the payment-status / cancel APIs. Moyasar appends `id` + `status` query params.
 */
class PaymentReturnController extends Controller
{
    /** Reached after a payment attempt completes. */
    public function afterPayment(): View
    {
        return view('payment.return', ['cancelled' => false]);
    }

    /** Reached when the guest backs out of the hosted payment page. */
    public function back(): View
    {
        return view('payment.return', ['cancelled' => true]);
    }
}
