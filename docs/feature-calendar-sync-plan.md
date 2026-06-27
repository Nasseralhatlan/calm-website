# Feature plan (deferred): Two-way iCal calendar sync for host availability

> Status: **planned, not yet built.** Saved for later — we paused to work on another feature first.
> Scope decisions made: host availability only, web dashboard only (no mobile API, no guest "add to
> calendar").

## Context
Hosts who also list on Airbnb / Booking.com / Google Calendar need their Calm calendar to stay in sync so
they don't get double-booked. Standard solution = **iCal (.ics)**:
- **Export:** Calm publishes a per-place `.ics` feed (booked + blocked dates). The host pastes that URL
  into the other platforms so those dates get blocked there too.
- **Import:** the host adds the other platforms' `.ics` URLs to a Calm place; a scheduled job fetches them
  and blocks those dates in Calm.

Managed from the web Availability page (`resources/views/host/places/availability.blade.php`).

Key fit: availability everywhere already comes from `PlaceAvailabilityService::expandBlockedDays`
(`app/Services/Place/PlaceAvailabilityService.php:228`) = `place->blockings()` + active bookings. So
**imported dates are just `PlaceBlocking` rows** → they automatically affect search, quote,
unavailable-dates, and the host calendar with zero extra wiring.

Important date semantics: a blocking/booking `end_date` is the **last night (inclusive)**; iCal `DTEND`
is **exclusive** (the checkout day). So convert: export `DTEND = end_date + 1 day`; import
`end_date = DTEND - 1 day`.

## Library
`composer require sabre/vobject` — battle-tested iCal parse + generate (handles line folding, DATE vs
DATE-TIME, timezones). Used for both reading external feeds and building ours.

## Data model (migrations)
1. `add_calendar_token_to_places` — `places.calendar_token` (string, nullable, unique). A random 40-char
   secret for the export URL, generated lazily on first feed view.
2. `create_place_calendar_feeds_table` — imported external feeds:
   `id (uuid), place_id (FK cascade), name, url, last_synced_at (nullable), last_status (nullable),
   last_error (nullable), timestamps`.
3. `add_source_to_place_blockings` — distinguish imported from manual:
   `source` (string, default `'manual'`), `calendar_feed_id` (nullable FK→place_calendar_feeds, cascade),
   `external_uid` (string, nullable). Unique index `(calendar_feed_id, external_uid)` for idempotent upsert.
   Existing rows default to `manual` — unaffected.
- Models: new `PlaceCalendarFeed`; `Place` gets `calendarFeeds()` + `freshCalendarToken()`; `PlaceBlocking`
  adds the new fillable fields + a `feed()` relation.

## Export (Calm → other apps)
- **Public route** (no auth, token-gated), in `routes/web.php` outside the auth group:
  `GET /ical/places/{place}/{token}.ics` → `CalendarFeedController@export`.
  Validate `hash_equals($place->calendar_token, $token)` → else 404 (not 403, so the token isn't probed).
- `CalendarExportService::feed(Place $place): string` builds a `VCALENDAR` with one **all-day VEVENT**
  (`VALUE=DATE`) per **Calm-native** unavailable range — active bookings (`scopeActiveHold`) + manual
  blockings (`source = manual`). **Excludes imported (`source = ical`) blockings** so we never echo a
  platform's own dates back to it. Each event: `DTSTART = start_date`, `DTEND = end_date + 1`,
  `SUMMARY` = "Reserved"/"Blocked" (no guest PII), stable `UID` (`booking-{id}@calm` / `block-{id}@calm`).
- Response `Content-Type: text/calendar; charset=utf-8`.

## Import (other apps → Calm)
- `CalendarImportService::syncFeed(PlaceCalendarFeed $feed): void`:
  - Fetch `feed->url` via `Http::timeout(15)->get()` (http/https only; cap body size). On failure → set
    `last_status='error' + last_error`, **leave existing blocks intact**, return.
  - Parse with sabre/vobject; for each `VEVENT`: `start = DTSTART (date)`, `end = DTEND ? DTEND-1day : start`.
    Skip events fully in the past (`end < today`).
  - **Reconcile** this feed's blockings: upsert `PlaceBlocking` by `(calendar_feed_id, external_uid)` with
    `source='ical'`, start/end, `reason` = SUMMARY; **delete** this feed's imported blockings whose UID is
    no longer in the feed (so external cancellations free the dates). Manual blocks are never touched.
  - Set `last_synced_at = now()`, `last_status='ok'`.
- `syncPlace(Place)` / `syncAll()` helpers.
- **Scheduled job** `app/Jobs/SyncExternalCalendars.php` (mirrors `app/Jobs/ExpireStaleBookings.php`) →
  `Schedule::job(new SyncExternalCalendars)->hourly()->withoutOverlapping()` in `routes/console.php`
  (rides the existing scheduler worker).

## Host controller + routes (in the existing auth group)
`app/Http/Controllers/Host/CalendarSyncController.php` (reuses the `authorizeOwner` pattern from
`app/Http/Controllers/Host/PlaceAvailabilityController.php:69`):
- `POST /my-places/{place}/calendar-feeds` — add feed (`FeedRequest`: `name` required, `url` required|url,
  cap ~5 feeds/place). Then dispatch a sync so it populates immediately.
- `DELETE /my-places/{place}/calendar-feeds/{feed}` — remove feed (cascade deletes its imported blockings).
- `POST /my-places/{place}/calendar-feeds/sync` — "Sync now" (dispatch `SyncExternalCalendars` for this place).
- `POST /my-places/{place}/calendar-token/rotate` — regenerate the export token (invalidates the old URL).

## UI — add a "Calendar sync" card to the Availability page
On `availability.blade.php`, below the calendar:
- **Export:** read-only export URL + Copy button + one-line instructions ("Paste this link into Airbnb /
  Booking / Google to block these dates there"). Small "rotate link" action.
- **Import:** list current feeds (name · last synced · ok/error), an add-feed form (name + URL), remove
  buttons, and a "Sync now" button.
- In the existing **Blocked dates** list, tag imported ranges "via {feed name}" and hide their individual
  Unblock (they're managed by removing the feed); manual blocks keep Unblock.
The controller passes `$place->calendarFeeds` + the export URL to the view.

## Security
- Token: `hash_equals`, 404 on mismatch; rotation supported.
- Import fetch = **SSRF surface**: restrict to `http(s)`, `Http::timeout`, max response size, and (hardening)
  reject private/loopback IPs. Feed count capped per place. Log + swallow per-feed failures.

## Tests
- `tests/Feature/Calendar/CalendarExportTest.php`: valid token → 200 `text/calendar` with VEVENTs;
  `DTEND = end_date+1`; wrong/missing token → 404; imported (`source=ical`) blocks excluded.
- `tests/Feature/Calendar/CalendarImportTest.php` (with `Http::fake()` serving a sample `.ics`):
  syncFeed creates a `source=ical` PlaceBlocking with `end_date = DTEND-1`; those dates show in
  `unavailableDates`; re-sync is idempotent; a dropped VEVENT deletes its blocking; a fetch error sets
  `last_status=error` and leaves blocks intact; manual blocks untouched.
- `tests/Feature/Web/Host/CalendarSyncTest.php`: owner can add/remove a feed + rotate token; non-owner 403.

## Verification
- `composer require sabre/vobject`; `php artisan migrate`.
- `./vendor/bin/pint` + `php artisan test` green.
- Manual: open a place's Availability page → copy the export URL, open it in a browser (downloads `.ics`
  with the booked/blocked events) and subscribe to it in Google Calendar. Add an external `.ics` URL as a
  feed → "Sync now" → its dates appear blocked on the Calm calendar and in search/quote.
- Prod note: `composer install` picks up sabre/vobject; the hourly sync rides the existing scheduler worker.
