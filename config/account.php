<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Deletion retention window
    |--------------------------------------------------------------------------
    |
    | When a user "deletes" their account it is soft-deleted (hidden + login
    | disabled) and all data is retained so support can restore it. Leave
    | ACCOUNT_RETAIN_DAYS unset/null to keep deleted accounts forever. Set it to
    | a number of days to enable the optional PurgeDeletedAccounts job, which
    | permanently scrubs PII on accounts deleted longer ago than that.
    |
    */

    'retain_days' => env('ACCOUNT_RETAIN_DAYS') !== null ? (int) env('ACCOUNT_RETAIN_DAYS') : null,

];
