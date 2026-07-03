<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\Finance\BookingFinanceFinalizer;
use App\Services\Finance\QoyodSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sweeps paid stays whose checkout passed more than N hours ago (config
 * finance.invoice.issue_after_checkout_hours) and issues their financial
 * documents (guest invoice, host commission invoice, payout statement).
 * Per-booking failures are logged and never stop the sweep. Scheduled every
 * fifteen minutes — see routes/console.php. Stateless: all state is in the DB.
 */
class FinalizeBookingFinances implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(BookingFinanceFinalizer $finalizer, QoyodSyncService $qoyod): void
    {
        // SQL narrows to plausible candidates; the exact checkout+N-hours
        // check happens in isDue() because checkout time derives from
        // end_date + check_out_time + checkout_next_day.
        Booking::query()
            ->where('payment_status', 'paid')
            ->whereIn('booking_status', [BookingStatus::Confirmed->value, BookingStatus::Completed->value])
            ->whereNull('financial_completed_at')
            ->where('end_date', '<=', now()->toDateString())
            ->with('host')
            ->chunkById(50, function ($bookings) use ($finalizer): void {
                foreach ($bookings as $booking) {
                    if (! $finalizer->isDue($booking)) {
                        continue;
                    }

                    try {
                        $finalizer->finalize($booking);
                    } catch (\Throwable $e) {
                        Log::error('finance: finalize failed', [
                            'booking' => $booking->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        // Push any pending/failed tax documents to Qoyod (no-op while the
        // integration is disabled). Retries happen naturally every sweep.
        $qoyod->syncPendingDocuments();
    }
}
