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
 * Starts Moyasar transfers for every payable host payout (completed stay +
 * documents issued + hold window passed). A no-op while MOYASAR_PAYOUTS_MODE
 * is 'manual'. Scheduled every fifteen minutes — see routes/console.php.
 * Stateless: all state is in the DB.
 */
class ProcessDuePayouts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(HostPayoutService $payouts): void
    {
        $payouts->executeDuePayouts();
    }
}
