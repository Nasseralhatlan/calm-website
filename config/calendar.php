<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| iCal calendar sync (Airbnb / Gathern / Google style)
|--------------------------------------------------------------------------
| Export: each place gets a secret /ical/places/{id}/{token}.ics URL other
| platforms poll. Import: hosts paste external .ics URLs per place; a
| scheduled job fetches them and mirrors their events into place_blockings.
*/

return [
    // Outbound fetch timeout (seconds) for one external feed.
    'fetch_timeout' => (int) env('CALENDAR_FETCH_TIMEOUT', 15),

    // Max .ics body size we accept from an external feed, in bytes. A busy
    // yearly calendar is tens of KB; 2 MB is far beyond any legitimate feed.
    'max_body_bytes' => (int) env('CALENDAR_MAX_BODY_BYTES', 2 * 1024 * 1024),

    // How many external feeds one place may import.
    'max_feeds_per_place' => (int) env('CALENDAR_MAX_FEEDS_PER_PLACE', 5),
];
