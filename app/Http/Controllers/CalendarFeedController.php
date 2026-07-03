<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Place;
use App\Services\Calendar\CalendarExportService;
use Illuminate\Http\Response;

/**
 * The public per-place iCal export — the URL a host pastes into Airbnb /
 * Gathern / Google so Calm's booked + blocked dates block there too. No auth:
 * the other platforms poll it as anonymous GETs, so the secret token in the
 * URL is the whole credential (capability URL, same model those platforms use).
 */
class CalendarFeedController extends Controller
{
    public function __construct(private readonly CalendarExportService $export) {}

    public function __invoke(Place $place, string $token): Response
    {
        // Constant-time compare; 404 (not 403) on mismatch so a token prober
        // can't even confirm the place exists.
        abort_unless(
            $place->calendar_token !== null && hash_equals($place->calendar_token, $token),
            404,
        );

        return response($this->export->feed($place), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="calm-'.$place->id.'.ics"',
            // Pollers should always fetch fresh availability.
            'Cache-Control' => 'no-cache',
        ]);
    }
}
