<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Booking\BookingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sweeps pending_payment bookings whose 10-minute hold has lapsed. Each is
 * re-checked against Moyasar (so a payment that completed without notifying us
 * is still confirmed) and otherwise expired so the dates free up. Scheduled
 * every minute — see routes/console.php. Stateless: all state lives in the DB.
 */
class ExpireStaleBookings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(BookingService $bookings): void
    {
        $bookings->expireStale();
    }
}
