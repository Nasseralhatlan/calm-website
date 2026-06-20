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
 * Flips confirmed bookings whose checkout has passed to completed, so a stay
 * doesn't stay "confirmed" forever after the guest leaves. Scheduled hourly —
 * see routes/console.php. Stateless: all state lives in the DB.
 */
class CompleteEndedBookings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(BookingService $bookings): void
    {
        $bookings->completeEndedStays();
    }
}
