<?php

declare(strict_types=1);

return [
    // Moyasar secret API key (used as HTTP basic-auth username, blank password).
    'secret_key' => env('MOYASAR_SECRET_KEY'),

    'base_url' => env('MOYASAR_BASE_URL', 'https://api.moyasar.com/v1/'),

    // Shared secret echoed back by Moyasar in every webhook payload as
    // `secret_token`. When set, the webhook endpoint rejects calls that don't
    // carry it — keeps strangers from spoofing payment confirmations.
    'webhook_secret' => env('MOYASAR_WEBHOOK_SECRET'),

    // Where Moyasar sends the guest's WebView after the hosted payment page.
    // The mobile app watches the WebView URL for these paths to know payment is
    // done (success) or was abandoned (back) — see PaymentReturnController.
    'success_url' => env('MOYASAR_SUCCESS_URL', env('APP_URL').'/calm-after-payment'),
    'back_url' => env('MOYASAR_BACK_URL', env('APP_URL').'/calm-back-payment'),

    // How long a hosted invoice (and the date hold) stays open before expiring.
    'hold_minutes' => (int) env('MOYASAR_HOLD_MINUTES', 10),

    // The hosted invoice expires this many minutes BEFORE the date hold, so
    // Moyasar stops accepting payment before our expiry sweep can release the
    // dates — closes the "paid after we expired" race.
    'invoice_buffer_minutes' => (int) env('MOYASAR_INVOICE_BUFFER_MINUTES', 1),

    // ── Moyasar Payouts (automatic host transfers) ───────────────────────────
    // 'manual' keeps the admin mark-paid flow; 'auto' lets the scheduler
    // execute real bank transfers via POST /v1/payouts. Flip to auto only
    // after Moyasar activates Payouts and the payout account (Al Rajhi / SNB
    // corporate account with API credentials) is registered — its id below.
    'payouts_mode' => env('MOYASAR_PAYOUTS_MODE', 'manual'),
    'payout_account_id' => env('MOYASAR_PAYOUT_ACCOUNT_ID'),
    'payout_purpose' => env('MOYASAR_PAYOUT_PURPOSE', 'expenses_services'),
    // Moyasar bank destinations require a city; hosts don't store one yet.
    'payout_default_city' => env('MOYASAR_PAYOUT_DEFAULT_CITY', 'Riyadh'),
];
