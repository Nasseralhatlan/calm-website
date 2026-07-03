<?php

declare(strict_types=1);

use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceBlocking;
use App\Models\PlaceCalendarFeed;
use App\Models\PlaceType;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed();
    Carbon::setTestNow('2026-07-01 12:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function apiCalSyncPlace(User $host): Place
{
    return Place::query()->create([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Sync '.fake()->unique()->numerify('####'),
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

const API_SYNC_ICS = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:res-1@airbnb.com\r\nDTSTART;VALUE=DATE:20260710\r\nDTEND;VALUE=DATE:20260712\r\nSUMMARY:Airbnb (Not available)\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

it('returns the sync overview, minting the export token exactly once', function (): void {
    $host = User::factory()->create(['phone' => '515300001']);
    $place = apiCalSyncPlace($host);

    $first = $this->actingAs($host, 'api')
        ->getJson("/api/host/places/{$place->id}/calendar-sync")
        ->assertOk()
        ->assertJsonPath('data.place_id', $place->id)
        ->assertJsonPath('data.feeds', []);

    $exportUrl = $first->json('data.export_url');
    expect($exportUrl)->toContain("/ical/places/{$place->id}/")
        ->and($exportUrl)->toEndWith('.ics')
        ->and($place->fresh()->calendar_token)->not->toBeNull();

    // Second view reuses the same token — the pasted link never silently changes.
    $again = $this->actingAs($host, 'api')->getJson("/api/host/places/{$place->id}/calendar-sync");
    expect($again->json('data.export_url'))->toBe($exportUrl);

    // And the minted URL actually serves the feed.
    $this->get(parse_url($exportUrl, PHP_URL_PATH))->assertOk();
});

it('connects a feed, syncs it immediately, and blocks its dates', function (): void {
    $host = User::factory()->create(['phone' => '515300002']);
    $place = apiCalSyncPlace($host);

    Http::fake(['feeds.test/*' => Http::response(API_SYNC_ICS)]);

    $this->actingAs($host, 'api')
        ->postJson("/api/host/places/{$place->id}/calendar-feeds", [
            'name' => 'Airbnb',
            'url' => 'https://feeds.test/airbnb.ics',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Airbnb')
        ->assertJsonPath('data.last_status', 'ok');

    // Imported dates flow into the host calendar as external blocks.
    $this->actingAs($host, 'api')
        ->getJson("/api/host/calendar?from=2026-07-01&to=2026-07-31&place_id={$place->id}")
        ->assertOk()
        ->assertJsonPath('data.days.2026-07-10.external_block', true)
        ->assertJsonPath('data.days.2026-07-11.external_block', true);
});

it('validates the feed url and caps feeds per place', function (): void {
    $host = User::factory()->create(['phone' => '515300003']);
    $place = apiCalSyncPlace($host);

    $this->actingAs($host, 'api')
        ->postJson("/api/host/places/{$place->id}/calendar-feeds", ['name' => 'Bad', 'url' => 'not-a-url'])
        ->assertStatus(422)
        ->assertJsonStructure(['data' => ['errors' => ['url']]]);

    $this->actingAs($host, 'api')
        ->postJson("/api/host/places/{$place->id}/calendar-feeds", ['name' => 'Bad', 'url' => 'ftp://feeds.test/cal.ics'])
        ->assertStatus(422);

    foreach (range(1, 5) as $i) {
        PlaceCalendarFeed::query()->create([
            'place_id' => $place->id, 'name' => "Feed {$i}", 'url' => "https://feeds.test/{$i}.ics",
        ]);
    }

    Http::fake(['feeds.test/*' => Http::response(API_SYNC_ICS)]);
    $this->actingAs($host, 'api')
        ->postJson("/api/host/places/{$place->id}/calendar-feeds", ['name' => 'Sixth', 'url' => 'https://feeds.test/6.ics'])
        ->assertStatus(422)
        ->assertJsonStructure(['data' => ['errors' => ['url']]]);
});

it('removes a feed and frees every date it blocked', function (): void {
    $host = User::factory()->create(['phone' => '515300004']);
    $place = apiCalSyncPlace($host);

    $feed = PlaceCalendarFeed::query()->create([
        'place_id' => $place->id, 'name' => 'Airbnb', 'url' => 'https://feeds.test/airbnb.ics',
    ]);
    PlaceBlocking::query()->create([
        'place_id' => $place->id, 'start_date' => '2026-07-10', 'end_date' => '2026-07-11',
        'source' => 'ical', 'calendar_feed_id' => $feed->id, 'external_uid' => 'res-1@airbnb.com',
    ]);

    $this->actingAs($host, 'api')
        ->deleteJson("/api/host/places/{$place->id}/calendar-feeds/{$feed->id}")
        ->assertOk();

    expect(PlaceCalendarFeed::query()->whereKey($feed->id)->exists())->toBeFalse()
        ->and(PlaceBlocking::query()->where('place_id', $place->id)->exists())->toBeFalse();
});

it('re-syncs on demand via sync now', function (): void {
    $host = User::factory()->create(['phone' => '515300005']);
    $place = apiCalSyncPlace($host);

    PlaceCalendarFeed::query()->create([
        'place_id' => $place->id, 'name' => 'Airbnb', 'url' => 'https://feeds.test/airbnb.ics',
    ]);

    Http::fake(['feeds.test/*' => Http::response(API_SYNC_ICS)]);

    $this->actingAs($host, 'api')
        ->postJson("/api/host/places/{$place->id}/calendar-feeds/sync")
        ->assertOk()
        ->assertJsonPath('data.feeds.0.last_status', 'ok');

    expect(PlaceBlocking::query()->where('place_id', $place->id)->where('source', 'ical')->count())->toBe(1);
});

it('rotates the export token, killing the old URL instantly', function (): void {
    $host = User::factory()->create(['phone' => '515300006']);
    $place = apiCalSyncPlace($host);
    $oldToken = $place->ensureCalendarToken();

    $response = $this->actingAs($host, 'api')
        ->postJson("/api/host/places/{$place->id}/calendar-token/rotate")
        ->assertOk();

    $newUrl = $response->json('data.export_url');
    expect($newUrl)->not->toContain($oldToken);

    $this->get("/ical/places/{$place->id}/{$oldToken}.ics")->assertNotFound();
    $this->get(parse_url($newUrl, PHP_URL_PATH))->assertOk();
});

it('refuses to unblock an imported (ical) blocking via the manual endpoint', function (): void {
    $host = User::factory()->create(['phone' => '515300007']);
    $place = apiCalSyncPlace($host);

    $feed = PlaceCalendarFeed::query()->create([
        'place_id' => $place->id, 'name' => 'Airbnb', 'url' => 'https://feeds.test/airbnb.ics',
    ]);
    $blocking = PlaceBlocking::query()->create([
        'place_id' => $place->id, 'start_date' => '2026-07-10', 'end_date' => '2026-07-11',
        'source' => 'ical', 'calendar_feed_id' => $feed->id, 'external_uid' => 'res-1@airbnb.com',
    ]);

    $this->actingAs($host, 'api')
        ->deleteJson("/api/host/places/{$place->id}/blockings/{$blocking->id}")
        ->assertStatus(422)
        ->assertJsonStructure(['data' => ['errors' => ['blocking']]]);

    expect($blocking->fresh())->not->toBeNull();
});

it('is owner-only and requires authentication', function (): void {
    $host = User::factory()->create(['phone' => '515300008']);
    $other = User::factory()->create(['phone' => '515300009']);
    $place = apiCalSyncPlace($host);
    $feed = PlaceCalendarFeed::query()->create([
        'place_id' => $place->id, 'name' => 'Airbnb', 'url' => 'https://feeds.test/airbnb.ics',
    ]);

    $this->actingAs($other, 'api')->getJson("/api/host/places/{$place->id}/calendar-sync")->assertForbidden();
    $this->actingAs($other, 'api')
        ->postJson("/api/host/places/{$place->id}/calendar-feeds", ['name' => 'X', 'url' => 'https://feeds.test/x.ics'])
        ->assertForbidden();
    $this->actingAs($other, 'api')->deleteJson("/api/host/places/{$place->id}/calendar-feeds/{$feed->id}")->assertForbidden();
    $this->actingAs($other, 'api')->postJson("/api/host/places/{$place->id}/calendar-token/rotate")->assertForbidden();
});

it('rejects unauthenticated access', function (): void {
    $place = apiCalSyncPlace(User::factory()->create(['phone' => '515300010']));

    $this->getJson("/api/host/places/{$place->id}/calendar-sync")->assertStatus(401);
    $this->postJson("/api/host/places/{$place->id}/calendar-feeds", [])->assertStatus(401);
});
