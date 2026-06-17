<?php

use App\Jobs\ExpireStaleBookings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Free dates from pending bookings that never completed payment within their
// hold window (and rescue any that quietly succeeded). Runs every minute on a
// single server — stateless, all state is in the DB.
Schedule::job(new ExpireStaleBookings)
    ->everyMinute()
    ->withoutOverlapping();
