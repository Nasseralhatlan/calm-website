<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | PostHog (server-side capture)
    |--------------------------------------------------------------------------
    |
    | Only events the client must not be trusted for are sent from here —
    | today that is `payment_completed`, fired from the Moyasar webhook.
    | Everything behavioural comes from the browser/app SDKs.
    |
    | Same project key as the client (it is write-only and public by design).
    | Leave the key empty to disable server-side capture entirely.
    |
    */

    'posthog' => [
        'key' => env('POSTHOG_KEY', ''),
        'host' => env('POSTHOG_HOST', 'https://eu.i.posthog.com'),
        'timeout' => (int) env('POSTHOG_TIMEOUT', 2),
    ],

];
