<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default SMS Driver
    |--------------------------------------------------------------------------
    |
    | Which SmsDeliveryContract implementation to bind into the container.
    |
    | Supported drivers:
    |   - "mock"      → logs the message to laravel.log (default for local/test)
    |   - "sms_saudi" → real https://sms-saudi.com API (https://api-server14.com)
    |
    */

    'driver' => env('SMS_DRIVER', 'mock'),

    /*
    |--------------------------------------------------------------------------
    | SMS Saudi (api-server14) driver settings
    |--------------------------------------------------------------------------
    */

    'sms_saudi' => [
        'endpoint' => env('SMS_SAUDI_ENDPOINT', 'https://api-server14.com/api/send.aspx'),
        'api_key' => env('SMS_SAUDI_API_KEY'),
        'sender' => env('SMS_SAUDI_SENDER'),
        // The gateway expects the full international number with the country
        // code prefix and no leading +. We store phones as "5xxxxxxxx" so we
        // prepend this on the wire.
        'country_code' => env('SMS_SAUDI_COUNTRY_CODE', '966'),
        // 1 = English, 2 = Arabic, 3 = Unicode
        'language' => env('SMS_SAUDI_LANGUAGE', '1'),
        'timeout' => (int) env('SMS_SAUDI_TIMEOUT', 10),
    ],

];
