<?php

declare(strict_types=1);

use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Jobs\SyncExternalCalendars;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceBlocking;
use App\Models\PlaceCalendarFeed;
use App\Models\PlaceType;
use App\Models\User;
use App\Services\Calendar\CalendarImportService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed();
    Carbon::setTestNow('2026-07-01 12:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function calImportPlace(User $host): Place
{
    return Place::query()->create([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Imp '.fake()->unique()->numerify('####'),
        'description' => 'Desc.',
        'price' => 1000,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'checkout_next_day' => false,
        'max_guests' => 4,
        'status' => PlaceStatus::Active->value,
        'review_status' => PlaceReviewStatus::Approved->value,
    ]);
}

function calImportFeed(Place $place): PlaceCalendarFeed
{
    return PlaceCalendarFeed::query()->create([
        'place_id' => $place->id,
        'name' => 'Airbnb',
        'url' => 'https://feeds.test/airbnb.ics',
    ]);
}

function airbnbIcs(array $events): string
{
    $body = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Airbnb Inc//Hosting Calendar 1.0//EN\r\n";
    foreach ($events as $e) {
        $body .= "BEGIN:VEVENT\r\n";
        if (isset($e['uid'])) {
            $body .= "UID:{$e['uid']}\r\n";
        }
        $body .= "DTSTART;VALUE=DATE:{$e['start']}\r\n";
        if (isset($e['end'])) {
            $body .= "DTEND;VALUE=DATE:{$e['end']}\r\n";
        }
        $body .= 'SUMMARY:'.($e['summary'] ?? 'Airbnb (Not available)')."\r\n";
        $body .= "END:VEVENT\r\n";
    }

    return $body."END:VCALENDAR\r\n";
}

it('imports events as ical blockings with inclusive end = DTEND − 1', function (): void {
    $place = calImportPlace(User::factory()->create(['phone' => '515200001']));
    $feed = calImportFeed($place);

    Http::fake(['feeds.test/*' => Http::response(airbnbIcs([
        ['uid' => 'res-1@airbnb.com', 'start' => '20260710', 'end' => '20260712'],
    ]))]);

    app(CalendarImportService::class)->syncFeed($feed);

    $blocking = PlaceBlocking::query()->where('calendar_feed_id', $feed->id)->sole();
    expect($blocking->source)->toBe('ical')
        ->and($blocking->external_uid)->toBe('res-1@airbnb.com')
        ->and($blocking->start_date->toDateString())->toBe('2026-07-10')
        ->and($blocking->end_date->toDateString())->toBe('2026-07-11') // exclusive DTEND − 1
        ->and($blocking->reason)->toBe('Airbnb (Not available)');

    expect($feed->fresh()->last_status)->toBe('ok')
        ->and($feed->fresh()->last_synced_at)->not->toBeNull();

    // The imported dates block availability everywhere — public calendar read:
    $this->getJson("/api/places/{$place->id}/unavailable-dates?from=2026-07-01&to=2026-07-31")
        ->assertOk()
        ->assertJsonPath('data.unavailable_dates', ['2026-07-10', '2026-07-11']);
});

it('is idempotent and mirrors external cancellations (vanished UID → freed dates)', function (): void {
    $place = calImportPlace(User::factory()->create(['phone' => '515200002']));
    $feed = calImportFeed($place);
    $service = app(CalendarImportService::class);

    $twoEvents = airbnbIcs([
        ['uid' => 'res-1@airbnb.com', 'start' => '20260710', 'end' => '20260712'],
        ['uid' => 'res-2@airbnb.com', 'start' => '20260720', 'end' => '20260721'],
    ]);
    // Third fetch: the Airbnb guest cancelled res-2 → it drops out of the feed.
    $oneEvent = airbnbIcs([
        ['uid' => 'res-1@airbnb.com', 'start' => '20260710', 'end' => '20260712'],
    ]);
    Http::fake(['feeds.test/*' => Http::sequence()
        ->push($twoEvents)
        ->push($twoEvents)
        ->push($oneEvent)]);

    $service->syncFeed($feed);
    $service->syncFeed($feed); // re-sync must not duplicate

    expect(PlaceBlocking::query()->where('calendar_feed_id', $feed->id)->count())->toBe(2);

    $service->syncFeed($feed);

    $remaining = PlaceBlocking::query()->where('calendar_feed_id', $feed->id)->get();
    expect($remaining)->toHaveCount(1)
        ->and($remaining->first()->external_uid)->toBe('res-1@airbnb.com');
});

it('leaves existing blocks intact when the fetch fails, and records the error', function (): void {
    $place = calImportPlace(User::factory()->create(['phone' => '515200003']));
    $feed = calImportFeed($place);
    $service = app(CalendarImportService::class);

    // First fetch succeeds; then the feed goes down — last-known availability
    // must survive the outage.
    Http::fake(['feeds.test/*' => Http::sequence()
        ->push(airbnbIcs([['uid' => 'res-1@airbnb.com', 'start' => '20260710', 'end' => '20260712']]))
        ->push('Server Error', 500)]);

    $service->syncFeed($feed);
    $service->syncFeed($feed);

    expect(PlaceBlocking::query()->where('calendar_feed_id', $feed->id)->count())->toBe(1)
        ->and($feed->fresh()->last_status)->toBe('error')
        ->and($feed->fresh()->last_error)->toContain('500');
});

it('never touches manual blocks and skips fully-past events', function (): void {
    $place = calImportPlace(User::factory()->create(['phone' => '515200004']));
    $feed = calImportFeed($place);

    $manual = PlaceBlocking::query()->create([
        'place_id' => $place->id, 'start_date' => '2026-07-05', 'end_date' => '2026-07-06', 'reason' => 'Mine',
    ]);

    Http::fake(['feeds.test/*' => Http::response(airbnbIcs([
        ['uid' => 'old@airbnb.com', 'start' => '20260601', 'end' => '20260603'], // fully past → skipped
    ]))]);

    app(CalendarImportService::class)->syncFeed($feed);

    expect(PlaceBlocking::query()->where('calendar_feed_id', $feed->id)->count())->toBe(0)
        ->and($manual->fresh())->not->toBeNull();
});

it('handles events without UID or DTEND via derived ids and single-day ranges', function (): void {
    $place = calImportPlace(User::factory()->create(['phone' => '515200005']));
    $feed = calImportFeed($place);
    $service = app(CalendarImportService::class);

    Http::fake(['feeds.test/*' => Http::response(airbnbIcs([
        ['start' => '20260715', 'summary' => 'Blocked day'], // no UID, no DTEND
    ]))]);

    $service->syncFeed($feed);
    $service->syncFeed($feed); // derived UID must stay stable across syncs

    $blocking = PlaceBlocking::query()->where('calendar_feed_id', $feed->id)->sole();
    expect($blocking->start_date->toDateString())->toBe('2026-07-15')
        ->and($blocking->end_date->toDateString())->toBe('2026-07-15')
        ->and($blocking->external_uid)->toStartWith('derived-');
});

it('syncs every feed via the scheduled job', function (): void {
    $place = calImportPlace(User::factory()->create(['phone' => '515200006']));
    $feed = calImportFeed($place);

    Http::fake(['feeds.test/*' => Http::response(airbnbIcs([
        ['uid' => 'res-9@airbnb.com', 'start' => '20260710', 'end' => '20260711'],
    ]))]);

    (new SyncExternalCalendars)->handle(app(CalendarImportService::class));

    expect(PlaceBlocking::query()->where('calendar_feed_id', $feed->id)->count())->toBe(1);
});

it('rejects non-http(s) urls without fetching', function (): void {
    $place = calImportPlace(User::factory()->create(['phone' => '515200007']));
    $feed = PlaceCalendarFeed::query()->create([
        'place_id' => $place->id, 'name' => 'Bad', 'url' => 'file:///etc/passwd',
    ]);

    app(CalendarImportService::class)->syncFeed($feed);

    expect($feed->fresh()->last_status)->toBe('error')
        ->and($feed->fresh()->last_error)->toContain('http');
});
