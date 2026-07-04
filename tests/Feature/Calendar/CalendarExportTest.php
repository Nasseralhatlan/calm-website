<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Models\Booking;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceBlocking;
use App\Models\PlaceCalendarFeed;
use App\Models\PlaceType;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed();
    Carbon::setTestNow('2026-07-01 12:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function calExportPlace(User $host): Place
{
    return Place::query()->create([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Cal '.fake()->unique()->numerify('####'),
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

it('serves bookings + manual blocks as all-day events with exclusive DTEND', function (): void {
    $host = User::factory()->create(['phone' => '515100001']);
    $guest = User::factory()->create(['phone' => '515100002']);
    $place = calExportPlace($host);
    $token = $place->ensureCalendarToken();

    $blocking = PlaceBlocking::query()->create([
        'place_id' => $place->id, 'start_date' => '2026-07-10', 'end_date' => '2026-07-11', 'reason' => 'Maintenance',
    ]);
    $booking = Booking::query()->create([
        'place_id' => $place->id, 'guest_user_id' => $guest->id, 'host_user_id' => $host->id,
        'booking_status' => BookingStatus::Confirmed->value,
        'start_date' => '2026-07-20', 'end_date' => '2026-07-21',
        'guests' => 2, 'nights' => 1, 'stay_amount' => 100000,
        'commission_rate' => 10, 'commission_amount' => 10000, 'vat_rate' => 15, 'vat_amount' => 15000,
        'total_amount' => 115000, 'payout_status' => 'not_paid', 'confirmed_at' => now(),
    ]);

    $response = $this->get("/ical/places/{$place->id}/{$token}.ics")
        ->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=utf-8');

    $ics = $response->getContent();

    expect($ics)->toContain('BEGIN:VCALENDAR')
        // Manual block: inclusive 10–11 → DTEND is the exclusive 12th.
        ->toContain("UID:block-{$blocking->id}@calm")
        ->toContain('SUMMARY:Blocked')
        ->toContain('DTSTART;VALUE=DATE:20260710')
        ->toContain('DTEND;VALUE=DATE:20260712')
        // Booking: 20–21 → DTEND 22. No guest PII, just "Reserved".
        ->toContain("UID:booking-{$booking->id}@calm")
        ->toContain('SUMMARY:Reserved')
        ->toContain('DTSTART;VALUE=DATE:20260720')
        ->toContain('DTEND;VALUE=DATE:20260722')
        ->not->toContain($guest->name);
});

it('never echoes imported (ical) blocks back out', function (): void {
    $host = User::factory()->create(['phone' => '515100003']);
    $place = calExportPlace($host);
    $token = $place->ensureCalendarToken();

    $feed = PlaceCalendarFeed::query()->create([
        'place_id' => $place->id, 'name' => 'Airbnb', 'url' => 'https://feeds.test/airbnb.ics',
    ]);
    PlaceBlocking::query()->create([
        'place_id' => $place->id, 'start_date' => '2026-07-25', 'end_date' => '2026-07-26',
        'source' => 'ical', 'calendar_feed_id' => $feed->id, 'external_uid' => 'abc@airbnb.com',
    ]);

    $ics = $this->get("/ical/places/{$place->id}/{$token}.ics")->assertOk()->getContent();

    expect($ics)->not->toContain('20260725');
});

it('404s on a wrong or missing token', function (): void {
    $host = User::factory()->create(['phone' => '515100004']);
    $place = calExportPlace($host);
    $place->ensureCalendarToken();

    $this->get("/ical/places/{$place->id}/wrong-token.ics")->assertNotFound();
});

it('404s when the place never minted a token', function (): void {
    $host = User::factory()->create(['phone' => '515100005']);
    $place = calExportPlace($host);

    $this->get("/ical/places/{$place->id}/anything.ics")->assertNotFound();
});
