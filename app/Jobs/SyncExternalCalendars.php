<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Calendar\CalendarImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Re-fetches every external iCal feed hosts have imported (Airbnb / Gathern /
 * Google) and mirrors their events into place_blockings. Per-feed failures are
 * logged and recorded on the feed row — one dead URL never stops the sweep.
 * Scheduled hourly — see routes/console.php. Stateless: all state is in the DB.
 */
class SyncExternalCalendars implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(CalendarImportService $calendars): void
    {
        $calendars->syncAll();
    }
}
