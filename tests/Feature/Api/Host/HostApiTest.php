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

function hostApiPlace(User $host, array $attrs = []): Place
{
    return Place::query()->create(array_merge([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Host Place '.fake()->unique()->numerify('###'),
        'description' => 'Desc.',
        'price' => 1000,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'max_guests' => 4,
        'status' => PlaceStatus::Active->value,
        'review_status' => PlaceReviewStatus::Approved->value,
    ], $attrs));
}

function hostApiBooking(Place $place, User $guest, array $attrs = []): Booking
{
    return Booking::query()->create(array_merge([
        'place_id' => $place->id,
        'guest_user_id' => $guest->id,
        'host_user_id' => $place->host_user_id,
        'booking_status' => BookingStatus::Confirmed->value,
        'start_date' => now()->addDays(3)->toDateString(),
        'end_date' => now()->addDays(4)->toDateString(),
        'guests' => 2,
        'booking_price' => 100000,
        'quantity' => 2,
        'booking_amount' => 200000,
        'commission_rate' => 10,
        'commission_amount' => 20000,
        'vat_rate' => 15,
        'vat_amount' => 30000,
        'total' => 230000,
        'payout_status' => 'not_paid',
    ], $attrs));
}

// ── Bookings ────────────────────────────────────────────────────────────────

it('returns bookings on the host\'s places with the guest + place', function (): void {
    $host = User::factory()->create(['phone' => '54000001']);
    $guest = User::factory()->create(['phone' => '54000002', 'name' => 'Sara Guest']);
    $place = hostApiPlace($host, ['title' => 'My Chalet']);
    hostApiBooking($place, $guest);

    // A booking on another host's place must not appear.
    $otherHost = User::factory()->create(['phone' => '54000003']);
    hostApiBooking(hostApiPlace($otherHost), $guest);

    $this->actingAs($host, 'api')
        ->getJson('/api/host/bookings')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.items.0.place.title', 'My Chalet')
        ->assertJsonPath('data.items.0.guest.name', 'Sara Guest')
        ->assertJsonPath('data.items.0.guest.phone', '54000002')
        ->assertJsonPath('data.items.0.pricing.total', 2300);
});

it('requires auth for host bookings', function (): void {
    $this->getJson('/api/host/bookings')->assertStatus(401);
});

// ── Listings ────────────────────────────────────────────────────────────────

it('returns all of the host\'s own listings including non-visible ones', function (): void {
    $host = User::factory()->create(['phone' => '54000010']);
    hostApiPlace($host, ['title' => 'Live One']);
    hostApiPlace($host, ['title' => 'Draft One', 'review_status' => PlaceReviewStatus::Draft->value]);

    // Another host's place excluded.
    hostApiPlace(User::factory()->create(['phone' => '54000011']));

    $res = $this->actingAs($host, 'api')
        ->getJson('/api/host/listings')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 2)
        ->assertJsonStructure(['data' => ['items' => [['id', 'title', 'status', 'review_status', 'likes_count', 'bookings_count', 'rating']]]]);

    $statuses = collect($res->json('data.items'))->pluck('review_status')->sort()->values()->all();
    expect($statuses)->toContain('approved')->toContain('draft');
});

it('requires auth for host listings', function (): void {
    $this->getJson('/api/host/listings')->assertStatus(401);
});

// ── Earnings ────────────────────────────────────────────────────────────────

it('totals the host\'s earnings split by payout status', function (): void {
    $host = User::factory()->create(['phone' => '54000020']);
    $guest = User::factory()->create(['phone' => '54000021']);
    $place = hostApiPlace($host);

    // Net per booking = booking_amount(200000) − commission(20000) = 180000 (1,800 SAR).
    hostApiBooking($place, $guest, ['payout_status' => 'paid']);
    hostApiBooking($place, $guest, ['payout_status' => 'not_paid']);
    // These must NOT count toward earnings.
    hostApiBooking($place, $guest, ['booking_status' => BookingStatus::PendingPayment->value]);
    hostApiBooking($place, $guest, ['booking_status' => BookingStatus::Expired->value]);

    $this->actingAs($host, 'api')
        ->getJson('/api/host/earnings')
        ->assertOk()
        ->assertJsonPath('data.currency', 'SAR')
        ->assertJsonPath('data.bookings_count', 2)
        ->assertJsonPath('data.total', 3600)
        ->assertJsonPath('data.total_minor', 360000)
        ->assertJsonPath('data.paid', 1800)
        ->assertJsonPath('data.paid_minor', 180000)
        ->assertJsonPath('data.not_paid', 1800)
        ->assertJsonPath('data.not_paid_minor', 180000);
});

it('returns zero earnings for a host with no confirmed bookings', function (): void {
    $host = User::factory()->create(['phone' => '54000030']);

    $this->actingAs($host, 'api')
        ->getJson('/api/host/earnings')
        ->assertOk()
        ->assertJsonPath('data.total', 0)
        ->assertJsonPath('data.paid', 0)
        ->assertJsonPath('data.not_paid', 0)
        ->assertJsonPath('data.bookings_count', 0);
});

it('requires auth for host earnings', function (): void {
    $this->getJson('/api/host/earnings')->assertStatus(401);
});
