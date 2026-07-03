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
    $this->host = User::factory()->create(['phone' => '515400001']);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function webCalSyncPlace(User $host): Place
{
    return Place::query()->create([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Web '.fake()->unique()->numerify('####'),
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

const WEB_SYNC_ICS = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:res-1@airbnb.com\r\nDTSTART;VALUE=DATE:20260710\r\nDTEND;VALUE=DATE:20260712\r\nSUMMARY:Airbnb (Not available)\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

it('shows the calendar-sync card with the export URL on the availability page', function (): void {
    $place = webCalSyncPlace($this->host);

    $this->actingAs($this->host, 'api')
        ->get("/my-places/{$place->id}/availability")
        ->assertOk()
        ->assertSee("/ical/places/{$place->id}/", false)
        ->assertSee($place->fresh()->calendar_token, false);
});

it('lets the owner connect and remove a feed', function (): void {
    $place = webCalSyncPlace($this->host);

    Http::fake(['feeds.test/*' => Http::response(WEB_SYNC_ICS)]);

    $this->actingAs($this->host, 'api')
        ->post("/my-places/{$place->id}/calendar-feeds", [
            'name' => 'Airbnb',
            'url' => 'https://feeds.test/airbnb.ics',
        ])
        ->assertRedirect(route('host.places.availability', $place));

    $feed = PlaceCalendarFeed::query()->where('place_id', $place->id)->sole();
    expect($feed->name)->toBe('Airbnb')
        ->and(PlaceBlocking::query()->where('calendar_feed_id', $feed->id)->count())->toBe(1);

    $this->actingAs($this->host, 'api')
        ->delete("/my-places/{$place->id}/calendar-feeds/{$feed->id}")
        ->assertRedirect(route('host.places.availability', $place));

    expect(PlaceCalendarFeed::query()->where('place_id', $place->id)->exists())->toBeFalse()
        ->and(PlaceBlocking::query()->where('place_id', $place->id)->exists())->toBeFalse();
});

it('rotates the export token', function (): void {
    $place = webCalSyncPlace($this->host);
    $oldToken = $place->ensureCalendarToken();

    $this->actingAs($this->host, 'api')
        ->post("/my-places/{$place->id}/calendar-token/rotate")
        ->assertRedirect(route('host.places.availability', $place));

    expect($place->fresh()->calendar_token)->not->toBe($oldToken);
});

it('blocks the manual unblock of an imported row on the web too', function (): void {
    $place = webCalSyncPlace($this->host);
    $feed = PlaceCalendarFeed::query()->create([
        'place_id' => $place->id, 'name' => 'Airbnb', 'url' => 'https://feeds.test/airbnb.ics',
    ]);
    $blocking = PlaceBlocking::query()->create([
        'place_id' => $place->id, 'start_date' => '2026-07-10', 'end_date' => '2026-07-11',
        'source' => 'ical', 'calendar_feed_id' => $feed->id, 'external_uid' => 'res-1@airbnb.com',
    ]);

    $this->actingAs($this->host, 'api')
        ->from("/my-places/{$place->id}/availability")
        ->delete("/my-places/{$place->id}/blockings/{$blocking->id}")
        ->assertSessionHasErrors(['blocking']);

    expect($blocking->fresh())->not->toBeNull();
});

it('forbids non-owners from managing sync', function (): void {
    $other = User::factory()->create(['phone' => '515400002']);
    $place = webCalSyncPlace($this->host);
    $feed = PlaceCalendarFeed::query()->create([
        'place_id' => $place->id, 'name' => 'Airbnb', 'url' => 'https://feeds.test/airbnb.ics',
    ]);

    $this->actingAs($other, 'api')
        ->post("/my-places/{$place->id}/calendar-feeds", ['name' => 'X', 'url' => 'https://feeds.test/x.ics'])
        ->assertForbidden();
    $this->actingAs($other, 'api')
        ->delete("/my-places/{$place->id}/calendar-feeds/{$feed->id}")
        ->assertForbidden();
    $this->actingAs($other, 'api')
        ->post("/my-places/{$place->id}/calendar-token/rotate")
        ->assertForbidden();
});
