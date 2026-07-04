<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Enums\UserRole;
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
        // The guest's phone is not exposed to the host.
        ->assertJsonMissingPath('data.items.0.guest.phone')
        ->assertJsonPath('data.items.0.pricing.total', 2300);
});

it('searches host bookings by reference or guest phone', function (): void {
    $host = User::factory()->create(['phone' => '54001001']);
    $sara = User::factory()->create(['phone' => '512345678', 'name' => 'Sara']);
    $omar = User::factory()->create(['phone' => '598765432', 'name' => 'Omar']);
    $place = hostApiPlace($host);

    $saraBooking = hostApiBooking($place, $sara, ['reference' => 'CB-SARA01']);
    $omarBooking = hostApiBooking($place, $omar, ['reference' => 'CB-OMAR99']);

    // By (partial) reference.
    $this->actingAs($host, 'api')
        ->getJson('/api/host/bookings?q=SARA01')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.items.0.id', $saraBooking->id);

    // By guest phone — with a leading 0 / country code, still matches "5xxxxxxxx".
    $this->actingAs($host, 'api')
        ->getJson('/api/host/bookings?q=0598765432')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.items.0.id', $omarBooking->id);

    $this->actingAs($host, 'api')
        ->getJson('/api/host/bookings?q=966512345678')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.items.0.id', $saraBooking->id);

    // No match → empty.
    $this->actingAs($host, 'api')
        ->getJson('/api/host/bookings?q=NOPE')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 0);
});

it('scopes the search to the caller only — an admin cannot find another host\'s bookings here', function (): void {
    // This is a mobile host endpoint: it is ALWAYS scoped to the caller's own
    // host_user_id, with no admin bypass.
    $admin = User::factory()->create(['phone' => '54002001', 'role' => UserRole::Admin->value]);
    $otherHost = User::factory()->create(['phone' => '54002002']);
    $guest = User::factory()->create(['phone' => '512340000', 'name' => 'Guest']);

    hostApiBooking(hostApiPlace($otherHost), $guest, ['reference' => 'CB-FIND01']);

    // The admin has no bookings as a host, so searching another host's booking
    // by reference or by the guest's phone returns nothing.
    $this->actingAs($admin, 'api')
        ->getJson('/api/host/bookings?q=CB-FIND01')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 0);

    $this->actingAs($admin, 'api')
        ->getJson('/api/host/bookings?q=0512340000')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 0);
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
        ->assertJsonCount(2, 'data.items')
        // Listings are deliberately unpaginated — the app gets the full set.
        ->assertJsonMissingPath('data.pagination')
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

    // Net per booking = 200000 − commission 20000 − commission VAT 3000 = 177000 (1,770 SAR).
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
        ->assertJsonPath('data.total', 3540)
        ->assertJsonPath('data.total_minor', 354000)
        ->assertJsonPath('data.paid', 1770)
        ->assertJsonPath('data.paid_minor', 177000)
        ->assertJsonPath('data.not_paid', 1770)
        ->assertJsonPath('data.not_paid_minor', 177000);
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
