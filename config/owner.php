<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Owner alert recipients
    |--------------------------------------------------------------------------
    |
    | Comma-separated emails that receive internal alerts on important business
    | events (new paid booking, payment failure, new place submitted, …). Leave
    | OWNER_EMAILS empty to disable owner alerts entirely.
    |
    */

    'emails' => array_filter(array_map('trim', explode(',', (string) env('OWNER_EMAILS', '')))),

];
