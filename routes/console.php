<?php

use App\Jobs\CompleteEndedBookings;
use App\Jobs\ExpireStaleBookings;
use App\Jobs\FinalizeBookingFinances;
use App\Jobs\PurgeDeletedAccounts;
use App\Jobs\SyncExternalCalendars;
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

// Flip confirmed bookings to completed once the guest's checkout has passed.
// Not time-critical, so hourly is plenty — stateless, all state is in the DB.
Schedule::job(new CompleteEndedBookings)
    ->hourly()
    ->withoutOverlapping();

// Permanently scrub PII on accounts soft-deleted past the retention window.
// No-ops unless ACCOUNT_RETAIN_DAYS is set, so deleted accounts are kept (and
// support-restorable) forever by default.
Schedule::job(new PurgeDeletedAccounts)
    ->daily()
    ->withoutOverlapping();

// Mirror hosts' external iCal feeds (Airbnb / Gathern / Google) into
// place_blockings so cross-platform bookings block dates here. Hourly matches
// how the platforms poll each other; hosts can also "Sync now" on demand.
Schedule::job(new SyncExternalCalendars)
    ->hourly()
    ->withoutOverlapping();

// Issue each paid stay's financial documents (guest invoice, host commission
// invoice, payout statement) once its checkout is more than N hours behind us
// (finance.invoice.issue_after_checkout_hours). Idempotent per booking.
Schedule::job(new FinalizeBookingFinances)
    ->everyFifteenMinutes()
    ->withoutOverlapping();
