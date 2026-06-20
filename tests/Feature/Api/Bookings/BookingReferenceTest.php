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

function refPlace(User $host): Place
{
    return Place::query()->create([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Ref place',
        'description' => 'x',
        'price' => 1000,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'max_guests' => 4,
        'status' => PlaceStatus::Active->value,
        'review_status' => PlaceReviewStatus::Approved->value,
    ]);
}

function refBooking(User $guest, Place $place): Booking
{
    return Booking::query()->create([
        'place_id' => $place->id,
        'guest_user_id' => $guest->id,
        'host_user_id' => $place->host_user_id,
        'booking_status' => BookingStatus::Confirmed->value,
        'start_date' => now()->addDays(3)->toDateString(),
        'end_date' => now()->addDays(4)->toDateString(),
        'guests' => 2,
        'booking_price' => 100000,
        'quantity' => 1,
        'booking_amount' => 100000,
        'commission_rate' => 10,
        'commission_amount' => 10000,
        'vat_rate' => 15,
        'vat_amount' => 15000,
        'total' => 115000,
        'payout_status' => 'not_paid',
    ]);
}

it('assigns a unique CB- reference on create', function (): void {
    $host = User::factory()->create(['phone' => '519100001']);
    $guest = User::factory()->create(['phone' => '519100002']);
    $place = refPlace($host);

    $a = refBooking($guest, $place);
    $b = refBooking($guest, $place);

    expect($a->reference)->toMatch('/^CB-[2-9A-HJKMNP-Z]{6}$/')
        ->and($b->reference)->toMatch('/^CB-[2-9A-HJKMNP-Z]{6}$/')
        ->and($a->reference)->not->toBe($b->reference);
});

it('exposes the reference on the bookings API', function (): void {
    $host = User::factory()->create(['phone' => '519100003']);
    $guest = User::factory()->create(['phone' => '519100004']);
    $booking = refBooking($guest, refPlace($host));

    $this->actingAs($guest, 'api')
        ->getJson('/api/bookings')
        ->assertOk()
        ->assertJsonPath('data.items.0.reference', $booking->reference);
});

it('shows the reference on the guest booking-detail page', function (): void {
    $host = User::factory()->create(['phone' => '519100005']);
    $guest = User::factory()->create(['phone' => '519100006']);
    $booking = refBooking($guest, refPlace($host));

    $this->actingAs($guest, 'api')
        ->get(route('user.bookings.show', $booking))
        ->assertOk()
        ->assertSee($booking->reference);
});
