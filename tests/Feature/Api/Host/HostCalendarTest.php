<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Models\Booking;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceBlocking;
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

function calPlace(User $host): Place
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

function calBooking(Place $place, User $guest, string $status, string $start, string $end): Booking
{
    return Booking::query()->create([
        'place_id' => $place->id,
        'guest_user_id' => $guest->id,
        'host_user_id' => $place->host_user_id,
        'booking_status' => $status,
        'start_date' => $start,
        'end_date' => $end,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'checkout_next_day' => false,
        'guests' => 2,
        'nights' => 1,
        'stay_amount' => 100000,
        'commission_rate' => 10,
        'commission_amount' => 10000,
        'vat_rate' => 15,
        'vat_amount' => 15000,
        'total_amount' => 115000,
        'payout_status' => 'not_paid',
        'confirmed_at' => now(),
    ]);
}

it('summarizes bookings per day across the window (occupancy = every day of the stay)', function (): void {
    $host = User::factory()->create(['phone' => '514000001']);
    $guest = User::factory()->create(['phone' => '514000002']);
    $place = calPlace($host);

    // 4-night stay Jul 3 → Jul 6 (inclusive).
    calBooking($place, $guest, BookingStatus::Confirmed->value, '2026-07-03', '2026-07-06');
    // A cancelled/expired one must NOT count.
    calBooking($place, $guest, BookingStatus::Expired->value, '2026-07-04', '2026-07-05');

    $this->actingAs($host, 'api')
        ->getJson('/api/host/calendar?from=2026-07-01&to=2026-07-31')
        ->assertOk()
        ->assertJsonPath('data.from', '2026-07-01')
        ->assertJsonPath('data.to', '2026-07-31')
        // occupied on every day of the stay
        ->assertJsonPath('data.days.2026-07-03.bookings', 1)
        ->assertJsonPath('data.days.2026-07-04.bookings', 1)
        ->assertJsonPath('data.days.2026-07-05.bookings', 1)
        ->assertJsonPath('data.days.2026-07-06.bookings', 1)
        // arrival / departure markers
        ->assertJsonPath('data.days.2026-07-03.check_ins', 1)
        ->assertJsonPath('data.days.2026-07-06.check_outs', 1)
        // a day outside the stay isn't in the sparse map
        ->assertJsonMissingPath('data.days.2026-07-10');
});

it('marks manual blocks (external stays false until calendar sync ships)', function (): void {
    $host = User::factory()->create(['phone' => '514000010']);
    $place = calPlace($host);

    PlaceBlocking::query()->create([
        'place_id' => $place->id,
        'start_date' => '2026-07-10',
        'end_date' => '2026-07-11',
        'reason' => 'Maintenance',
    ]);

    $this->actingAs($host, 'api')
        ->getJson('/api/host/calendar?from=2026-07-01&to=2026-07-31')
        ->assertOk()
        ->assertJsonPath('data.days.2026-07-10.manual_block', true)
        ->assertJsonPath('data.days.2026-07-11.manual_block', true)
        ->assertJsonPath('data.days.2026-07-10.external_block', false)
        ->assertJsonPath('data.days.2026-07-10.bookings', 0);
});

it('defaults the window to 12 months and clamps to 18', function (): void {
    $host = User::factory()->create(['phone' => '514000020']);

    // Defaults: from today, to +12 months.
    $this->actingAs($host, 'api')
        ->getJson('/api/host/calendar')
        ->assertOk()
        ->assertJsonPath('data.from', '2026-07-01')
        ->assertJsonPath('data.to', '2027-07-01');

    // A far `to` is clamped to from + 18 months.
    $this->actingAs($host, 'api')
        ->getJson('/api/host/calendar?from=2026-07-01&to=2030-01-01')
        ->assertOk()
        ->assertJsonPath('data.to', '2028-01-01');
});

it('filters by place and rejects a place the host does not own', function (): void {
    $host = User::factory()->create(['phone' => '514000030']);
    $guest = User::factory()->create(['phone' => '514000031']);
    $placeA = calPlace($host);
    $placeB = calPlace($host);
    calBooking($placeA, $guest, BookingStatus::Confirmed->value, '2026-07-03', '2026-07-04');
    calBooking($placeB, $guest, BookingStatus::Confirmed->value, '2026-07-20', '2026-07-21');

    // Filter to place A only.
    $this->actingAs($host, 'api')
        ->getJson("/api/host/calendar?from=2026-07-01&to=2026-07-31&place_id={$placeA->id}")
        ->assertOk()
        ->assertJsonPath('data.days.2026-07-03.bookings', 1)
        ->assertJsonMissingPath('data.days.2026-07-20');   // place B excluded

    // A place owned by someone else → 422.
    $otherPlace = calPlace(User::factory()->create(['phone' => '514000032']));
    $this->actingAs($host, 'api')
        ->getJson("/api/host/calendar?place_id={$otherPlace->id}")
        ->assertStatus(422);
});

it('only reflects the authenticated host\'s data', function (): void {
    $host = User::factory()->create(['phone' => '514000040']);
    $otherHost = User::factory()->create(['phone' => '514000041']);
    $guest = User::factory()->create(['phone' => '514000042']);
    calBooking(calPlace($otherHost), $guest, BookingStatus::Confirmed->value, '2026-07-03', '2026-07-04');

    $this->actingAs($host, 'api')
        ->getJson('/api/host/calendar?from=2026-07-01&to=2026-07-31')
        ->assertOk()
        ->assertJsonPath('data.days', []);   // nothing of the host's own
});

it('returns bookings + blockings occupying a specific day', function (): void {
    $host = User::factory()->create(['phone' => '514000050']);
    $guest = User::factory()->create(['phone' => '514000051']);
    $place = calPlace($host);

    // Stay spanning Jul 3 → Jul 6; querying a MID-stay day must return it.
    $booking = calBooking($place, $guest, BookingStatus::Confirmed->value, '2026-07-03', '2026-07-06');
    PlaceBlocking::query()->create([
        'place_id' => $place->id, 'start_date' => '2026-07-04', 'end_date' => '2026-07-04', 'reason' => 'Hold',
    ]);

    $this->actingAs($host, 'api')
        ->getJson('/api/host/calendar/day?date=2026-07-04')
        ->assertOk()
        ->assertJsonPath('data.date', '2026-07-04')
        ->assertJsonCount(1, 'data.bookings')
        ->assertJsonPath('data.bookings.0.id', $booking->id)
        ->assertJsonCount(1, 'data.blockings')
        ->assertJsonPath('data.blockings.0.source', 'manual')
        ->assertJsonPath('data.blockings.0.reason', 'Hold');

    // An empty day → both lists empty.
    $this->actingAs($host, 'api')
        ->getJson('/api/host/calendar/day?date=2026-07-20')
        ->assertOk()
        ->assertJsonCount(0, 'data.bookings')
        ->assertJsonCount(0, 'data.blockings');
});

it('requires authentication', function (): void {
    $this->getJson('/api/host/calendar')->assertStatus(401);
    $this->getJson('/api/host/calendar/day?date=2026-07-04')->assertStatus(401);
});
