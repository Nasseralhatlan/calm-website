<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Push Channel Enabled
    |--------------------------------------------------------------------------
    |
    | Master switch for the push channel. OFF by default — the app ships with
    | SMS + in-app only (no Expo / device-token / EAS setup required). Flip
    | PUSH_ENABLED=true once the mobile app registers Expo tokens; everything
    | else (NotificationService, /api/devices, the job) is already in place.
    |
    */

    'enabled' => (bool) env('PUSH_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Default Push Driver
    |--------------------------------------------------------------------------
    |
    | Which PushDeliveryContract implementation to bind (used only when push is
    | enabled).
    |   - "mock" → logs to laravel.log (default for local/test)
    |   - "expo" → Expo push service (https://exp.host/--/api/v2/push/send)
    |
    */

    'driver' => env('PUSH_DRIVER', 'mock'),

    /*
    |--------------------------------------------------------------------------
    | Expo driver settings
    |--------------------------------------------------------------------------
    |
    | access_token is only needed if you've enabled "Enhanced Security for
    | Push Notifications" in your Expo project; otherwise leave it blank.
    |
    */

    'expo' => [
        'access_token' => env('EXPO_ACCESS_TOKEN'),
        'timeout' => (int) env('EXPO_PUSH_TIMEOUT', 10),
    ],
];
