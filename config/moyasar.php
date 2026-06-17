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
];
