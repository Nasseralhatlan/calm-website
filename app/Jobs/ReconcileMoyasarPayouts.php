<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Finance\HostPayoutService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Polls Moyasar for every in-flight (processing) payout and settles or
 * requeues it from the answer. A no-op when nothing is processing. Scheduled
 * every ten minutes — see routes/console.php. Stateless: all state is in
 * the DB.
 */
class ReconcileMoyasarPayouts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(HostPayoutService $payouts): void
    {
        $payouts->reconcileProcessingPayouts();
    }
}
