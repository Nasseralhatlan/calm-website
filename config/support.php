<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Support contact details
    |--------------------------------------------------------------------------
    |
    | Shown to hosts and guests on the booking detail page (in place of the
    | admin-only cancel actions) so they can reach support for help, changes
    | or cancellation requests. Set the real values in the environment.
    |
    */

    'phone' => env('SUPPORT_PHONE', '+966500000000'),

    'whatsapp' => env('SUPPORT_WHATSAPP', env('SUPPORT_PHONE', '+966500000000')),

    'email' => env('SUPPORT_EMAIL', 'support@calm.sa'),

    'hours' => env('SUPPORT_HOURS', '9:00 AM – 11:00 PM'),

];
