<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Models\Booking;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceType;
use App\Models\User;

beforeEach(function (): void {
    $this->seed();
});

function dashboardPlace(User $host): Place
{
    return Place::query()->create([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Dashboard Test Chalet',
        'description' => 'Desc.',
        'price' => 1000,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'max_guests' => 4,
        'status' => PlaceStatus::Active->value,
        'review_status' => PlaceReviewStatus::Approved->value,
    ]);
}

function makeBooking(Place $place, User $guest, array $attrs = []): Booking
{
    return Booking::query()->create(array_merge([
        'place_id' => $place->id,
        'guest_user_id' => $guest->id,
        'host_user_id' => $place->host_user_id,
        'booking_status' => BookingStatus::Confirmed->value,
        'start_date' => now()->addDays(3)->toDateString(),
        'end_date' => now()->addDays(4)->toDateString(),
        'guests' => 2,
        'nights' => 2,
        'stay_amount' => 200000,
        'commission_rate' => 10,
        'commission_amount' => 20000,
        'guest_vat_rate' => 15,
        'guest_vat_amount' => 30000,
        'guest_total' => 230000,
        'payout_status' => 'not_paid',
    ], $attrs));
}

it('shows a guest their own bookings on /my-bookings', function (): void {
    $guest = User::factory()->create(['phone' => '517000001']);
    $host = User::factory()->create(['phone' => '517000002']);
    $place = dashboardPlace($host);
    makeBooking($place, $guest);

    $this->actingAs($guest, 'api')
        ->get('/my-bookings')
        ->assertOk()
        ->assertSee('Dashboard Test Chalet')
        ->assertSee('2,300.00');
});

it('shows the empty state on /my-bookings when the guest has no bookings', function (): void {
    $guest = User::factory()->create(['phone' => '517000003']);

    $this->actingAs($guest, 'api')
        ->get('/my-bookings')
        ->assertOk()
        ->assertDontSee('Dashboard Test Chalet');
});

it('does not show another guest\'s bookings on /my-bookings', function (): void {
    $guest = User::factory()->create(['phone' => '517000004']);
    $other = User::factory()->create(['phone' => '517000005']);
    $host = User::factory()->create(['phone' => '517000006']);
    $place = dashboardPlace($host);
    makeBooking($place, $other); // belongs to $other, not $guest

    $this->actingAs($guest, 'api')
        ->get('/my-bookings')
        ->assertOk()
        ->assertDontSee('Dashboard Test Chalet');
});

it('shows a host the bookings on their places on /bookings', function (): void {
    $host = User::factory()->create(['phone' => '517000007']);
    $guest = User::factory()->create(['phone' => '517000008', 'name' => 'Sara Guest']);
    $place = dashboardPlace($host);
    makeBooking($place, $guest);

    $this->actingAs($host, 'api')
        ->get('/bookings')
        ->assertOk()
        ->assertSee('Dashboard Test Chalet')
        ->assertSee('Sara Guest');
});

it('paginates the host /bookings page', function (): void {
    config(['pagination.per_page' => 1]);
    $host = User::factory()->create(['phone' => '517000020']);
    $guest = User::factory()->create(['phone' => '517000021']);
    $place = dashboardPlace($host);
    makeBooking($place, $guest);
    $this->travel(1)->minutes();
    $newer = makeBooking($place, $guest);

    // Page 1: only the newest booking + a pager to page 2 (proves it's paginated,
    // not the full collection).
    $this->actingAs($host, 'api')
        ->get('/bookings')
        ->assertOk()
        ->assertSee($newer->reference)
        ->assertSee('bookings?page=2');        // pager link rendered

    // Page 2: the older booking shows.
    $this->actingAs($host, 'api')
        ->get('/bookings?page=2')
        ->assertOk()
        ->assertSee('Dashboard Test Chalet');
});

it('does not show a host bookings on places they do not own on /bookings', function (): void {
    $host = User::factory()->create(['phone' => '517000009']);
    $otherHost = User::factory()->create(['phone' => '517000010']);
    $guest = User::factory()->create(['phone' => '517000011']);
    $place = dashboardPlace($otherHost);
    makeBooking($place, $guest);

    $this->actingAs($host, 'api')
        ->get('/bookings')
        ->assertOk()
        ->assertDontSee('Dashboard Test Chalet');
});

it('lets the guest open their booking detail', function (): void {
    $guest = User::factory()->create(['phone' => '517000012']);
    $host = User::factory()->create(['phone' => '517000013']);
    $place = dashboardPlace($host);
    $booking = makeBooking($place, $guest);

    $this->actingAs($guest, 'api')
        ->get("/bookings/{$booking->id}")
        ->assertOk()
        ->assertSee('Dashboard Test Chalet')
        ->assertSee('2,300.00'); // total the guest paid
});

it('lets the host open the booking detail with payout + guest name, but NOT the guest phone', function (): void {
    $host = User::factory()->create(['phone' => '517000014']);
    $guest = User::factory()->create(['phone' => '517000015', 'name' => 'Sara Guest']);
    $place = dashboardPlace($host);
    $booking = makeBooking($place, $guest);

    $this->actingAs($host, 'api')
        ->get("/bookings/{$booking->id}")
        ->assertOk()
        ->assertSee('Sara Guest')        // guest name
        ->assertDontSee('517000015')     // guest phone is hidden from the host (admin only)
        ->assertSee('1,770.00');         // payout = 2000 − 200 commission − 30 commission VAT
});

it('404s the booking detail for a user who is neither guest nor host', function (): void {
    $guest = User::factory()->create(['phone' => '517000016']);
    $host = User::factory()->create(['phone' => '517000017']);
    $stranger = User::factory()->create(['phone' => '517000018']);
    $place = dashboardPlace($host);
    $booking = makeBooking($place, $guest);

    $this->actingAs($stranger, 'api')
        ->get("/bookings/{$booking->id}")
        ->assertNotFound();
});
